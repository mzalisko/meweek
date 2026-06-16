<?php

namespace App\Services\Publishing;

use App\Models\DataValue;
use App\Models\Publication;
use App\Models\Site;
use App\Services\Failover\SlotResolver;
use Illuminate\Support\Collection;

class SitePayloadCompiler
{
    public function __construct(private SlotResolver $resolver) {}

    public function publish(Site $site): Publication
    {
        $payload = $this->compile($site);
        $version = (int) Publication::where('site_id', $site->id)->max('version') + 1;
        $payload['version'] = $version;

        return Publication::create([
            'site_id' => $site->id,
            'version' => $version,
            'payload' => $payload,
        ]);
    }

    public function compile(Site $site): array
    {
        $values = $this->effectiveValues($site);

        $items = [];
        foreach ($values as $value) {
            $item = $this->buildItem($value, $values);
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return [
            'site' => $site->domain,
            'version' => 0, // фінальне значення ставить publish()
            'generated_at' => now()->toIso8601String(),
            'values' => $items,
        ];
    }

    /** @return Collection<string, DataValue> ключ → значення; сайт перекриває групу */
    private function effectiveValues(Site $site): Collection
    {
        $with = ['type', 'geoTags', 'phoneSlot.entries.phoneNumber'];

        $group = $site->site_group_id
            ? DataValue::with($with)->where('status', 'active')
                ->where('scope_type', 'group')->where('scope_id', $site->site_group_id)->get()
            : collect();

        $own = DataValue::with($with)->where('status', 'active')
            ->where('scope_type', 'site')->where('scope_id', $site->id)->get();

        return $group->toBase()->keyBy('key')->merge($own->toBase()->keyBy('key'));
    }

    private function buildItem(DataValue $value, Collection $all): ?array
    {
        $geo = $value->geoTags->pluck('code')->all() ?: ['WORLD'];
        $base = ['key' => $value->key, 'type' => $value->type->code, 'geo' => $geo];

        return match ($value->type->code) {
            'phone' => $this->phoneItem($value, $base),
            'messenger' => $this->messengerItem($value, $base, $all),
            default => $base
                + ['value' => $value->content['value'] ?? null]
                + collect($value->content ?? [])->except('value')->all(),
        };
    }

    private function phoneItem(DataValue $value, array $base): array
    {
        $slot = $value->phoneSlot;
        if (! $slot) {
            return $base + ['state' => 'hidden', 'value' => null];
        }

        $resolved = $this->resolver->resolve($slot);

        return $base + [
            'state' => $resolved->visible ? $resolved->state : 'hidden',
            'value' => $resolved->visible ? $resolved->number : null,
        ];
    }

    private function messengerItem(DataValue $value, array $base, Collection $all): ?array
    {
        $content = $value->content ?? [];
        $linkedSlotRaw = $content['linked_slot'] ?? null;
        $linkedSlotStr = is_string($linkedSlotRaw) && $linkedSlotRaw !== '' ? $linkedSlotRaw : null;

        $network = $content['network'] ?? 'unknown';
        $base += [
            'network' => $network,
            'name' => $content['value'] ?? ($content['name'] ?? $value->key),
            'linked_slot' => null,
            'messenger_slot' => $content['messenger_slot'] ?? $linkedSlotStr ?? null,
            'pinned' => (bool) ($content['pinned'] ?? false),
            'return_mode' => $content['return_mode'] ?? 'auto',
        ];

        $messengers = $all->filter(fn (DataValue $dv) => $dv->type->code === 'messenger'
            && $dv->scope_type === $value->scope_type
            && $dv->scope_id === $value->scope_id);

        $groupKey = $content['messenger_slot'] ?? $linkedSlotStr ?? $value->key;
        $group = $messengers
            ->filter(fn (DataValue $dv) => ($dv->content['messenger_slot']
                ?? (is_string($dv->content['linked_slot'] ?? null) && ($dv->content['linked_slot'] ?? '') !== ''
                    ? $dv->content['linked_slot'] : null)
                ?? $dv->key) === $groupKey)
            ->sortBy(fn (DataValue $dv) => sprintf(
                '%010d_%010d',
                $dv->created_at?->getTimestamp() ?? 0,
                $dv->id
            ))
            ->values();

        $primary = $group->first();
        $primaryContent = $primary?->content ?? [];
        $policy = $primaryContent['exhaustion_policy'] ?? 'hide';
        $returnMode = $primaryContent['return_mode'] ?? 'auto';
        $storedCurrentId = $primaryContent['current_messenger_id'] ?? null;
        $current = $group
            ->first(fn (DataValue $dv) => (bool) ($dv->content['pinned'] ?? false)
                && ($dv->status ?? 'active') === 'active'
                && ($dv->content['enabled'] ?? true))
            ?? ($returnMode === 'sticky' && $storedCurrentId
                ? $group->first(fn (DataValue $dv) => (int) $dv->id === (int) $storedCurrentId
                    && ($dv->status ?? 'active') === 'active'
                    && ($dv->content['enabled'] ?? true))
                : null)
            ?? $group->first(fn (DataValue $dv) => ($dv->status ?? 'active') === 'active' && ($dv->content['enabled'] ?? true));
        $currentId = $current?->id;

        if (! $currentId) {
            if ($value->id !== $primary?->id) {
                return $base + [
                    'state' => 'hidden',
                    'value' => null,
                    'url' => null,
                    'is_current' => false,
                ];
            }

            return $base + match ($policy) {
                'emergency' => [
                    'state' => 'exhausted',
                    'value' => $primaryContent['emergency_value'] ?? null,
                    'url' => $primaryContent['emergency_url'] ?? $this->messengerUrlFromValue($primaryContent['emergency_value'] ?? null),
                    'is_current' => false,
                ],
                'last' => [
                    'state' => 'exhausted',
                    'value' => $primaryContent['last_active_value'] ?? ($primaryContent['value'] ?? null),
                    'url' => $primaryContent['last_active_url'] ?? ($primaryContent['url'] ?? null),
                    'is_current' => false,
                ],
                default => [
                    'state' => 'hidden',
                    'value' => null,
                    'url' => null,
                    'is_current' => false,
                ],
            };
        }

        if (($value->status ?? 'active') === 'hidden' || ($content['enabled'] ?? true) === false) {
            return $base + [
                'state' => 'hidden',
                'value' => null,
                'url' => $content['url'] ?? null,
            ];
        }

        return $base + [
            'state' => $value->id === $currentId ? 'ok' : 'on_reserve',
            'value' => $content['value'] ?? null,
            'url' => $content['url'] ?? null,
            'is_current' => $value->id === $currentId,
        ];
    }

    private function messengerUrlFromValue(?string $value): ?string
    {
        return $value && preg_match('/^https?:\/\//i', $value) ? $value : null;
    }
}
