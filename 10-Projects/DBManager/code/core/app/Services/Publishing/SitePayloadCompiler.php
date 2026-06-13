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

        if (($content['enabled'] ?? true) === false) {
            return null;
        }

        $network = $content['network'] ?? 'unknown';
        $base += ['network' => $network];

        if (! isset($content['linked_slot'])) {
            return $base + ['state' => 'ok', 'value' => $content['value'] ?? null, 'url' => $content['url'] ?? null];
        }

        $slotValue = $all->get($content['linked_slot']);
        $slot = $slotValue?->phoneSlot;
        if (! $slot) {
            return $base + ['state' => 'hidden', 'value' => null, 'url' => null];
        }

        $resolved = $this->resolver->resolve($slot);
        if (! $resolved->visible) {
            return $base + ['state' => 'hidden', 'value' => null, 'url' => null];
        }

        $digits = $resolved->number ? ltrim($resolved->number, '+') : null;
        $url = $digits === null ? null : match ($network) {
            'viber' => 'viber://chat?number=%2B' . $digits,
            'whatsapp' => 'https://wa.me/' . $digits,
            default => null,
        };

        return $base + ['state' => 'ok', 'value' => $resolved->number, 'url' => $url];
    }
}
