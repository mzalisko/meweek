<?php

namespace App\Services\Publishing;

use App\Models\DataValue;
use App\Models\Publication;
use App\Models\Site;
use App\Services\Failover\SlotResolver;
use App\Admin\SiteHierarchy;
use App\Support\PhoneFormatter;
use Illuminate\Support\Collection;

class SitePayloadCompiler
{
    private const TYPE_ORDER = [
        'phone' => 0,
        'messenger' => 1,
        'price' => 2,
        'address' => 3,
        'social' => 4,
        'text' => 5,
    ];

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
            if ($value->type->code === 'price') {
                $priceItems = $this->buildPriceItems($value);
                foreach ($priceItems as $pi) {
                    $items[] = $pi;
                }
            } else {
                $item = $this->buildItem($value, $values);
                if ($item !== null) {
                    $items[] = $item;
                }
            }
        }

        return [
            'site_id' => $site->id,
            'version' => 0,
            'generated_at' => now()->toIso8601String(),
            'values' => $items,
        ];
    }

    private function buildPriceItems(DataValue $value): array
    {
        $prices = $value->content['prices'] ?? [];
        if (empty($prices)) {
            return [
                [
                    'key' => $value->key,
                    'type' => 'price',
                    'geo' => ['WORLD'],
                    'value' => null,
                    'state' => ($value->status ?? 'active') === 'hidden' ? 'hidden' : 'ok',
                ]
            ];
        }

        $items = [];
        foreach ($prices as $price) {
            $items[] = [
                'key' => $value->key,
                'type' => 'price',
                'geo' => $price['geo'] ?? ['WORLD'],
                'value' => $price['value'],
                'label' => $price['label'] ?? null,
                'state' => ($value->status ?? 'active') === 'hidden' ? 'hidden' : 'ok',
            ];
        }

        return $items;
    }

    /** @return Collection<string, DataValue> */
    private function effectiveValues(Site $site): Collection
    {
        $with = ['type', 'geoTags', 'phoneSlot.entries.phoneNumber'];

        return DataValue::with($with)
            // Приховані значення (status='hidden') теж публікуємо — зі state='hidden',
            // щоб плагін показав їх як «скрыто» (адмінка) і сховав від відвідувачів (фронт),
            // а не щоб вони зникали з payload.
            ->whereIn('status', ['active', 'hidden'])
            ->where('scope_type', 'site')
            ->where('scope_id', $site->id)
            ->get()
            ->sortBy(fn (DataValue $value) => sprintf(
                '%02d_%s_%010d',
                self::TYPE_ORDER[$value->type?->code] ?? 99,
                strtolower($value->key),
                $value->id
            ))
            ->keyBy('key')
            ->toBase();
    }

    private function buildItem(DataValue $value, Collection $all): ?array
    {
        $geo = $value->geoTags->pluck('code')->all() ?: ['WORLD'];
        $base = ['key' => $value->key, 'type' => $value->type->code, 'geo' => $geo];

        return match ($value->type->code) {
            'phone' => $this->phoneItem($value, $base),
            'messenger' => $this->messengerItem($value, $base, $all),
            'social' => $this->socialItem($value, $base),
            'address' => $this->addressItem($value, $base),
            default => $base
                + ['state' => ($value->status ?? 'active') === 'hidden' ? 'hidden' : 'ok']
                + ['value' => $value->content['value'] ?? null]
                + collect($value->content ?? [])->except('value')->all(),
        };
    }

    private function socialItem(DataValue $value, array $base): array
    {
        $content = $value->content ?? [];

        return $base + [
            'state'   => ($value->status ?? 'active') === 'hidden' ? 'hidden' : 'ok',
            'network' => $content['network'] ?? null,
            'value'   => $content['value'] ?? null,
            'url'     => $content['url'] ?? null,
        ];
    }

    /** Структурована адреса: явний allow-list ключів (без витоку службових полів content у payload). */
    private function addressItem(DataValue $value, array $base): array
    {
        $content = $value->content ?? [];

        return $base + [
            'state'    => ($value->status ?? 'active') === 'hidden' ? 'hidden' : 'ok',
            'value'    => $content['value'] ?? null,
            'country'  => $content['country'] ?? null,
            'region'   => $content['region'] ?? null,
            'city'     => $content['city'] ?? null,
            'street'   => $content['street'] ?? null,
            'postcode' => $content['postcode'] ?? null,
        ];
    }

    private function phoneItem(DataValue $value, array $base): array
    {
        if (($value->status ?? 'active') === 'hidden') {
            return $base + ['state' => 'hidden', 'value' => null, 'display_value' => null, 'phone_format' => null];
        }

        $slot = $value->phoneSlot;
        if (! $slot) {
            return $base + ['state' => 'hidden', 'value' => null];
        }

        $resolved = $this->resolver->resolve($slot);
        $raw = $resolved->visible ? $resolved->number : null;
        $format = trim((string) ($value->content['phone_format'] ?? ''));

        return $base + [
            'state' => $resolved->visible ? $resolved->state : 'hidden',
            'value' => $raw,
            'display_value' => PhoneFormatter::format($raw, $format),
            'phone_format' => $format !== '' ? $format : null,
        ];
    }

    private function messengerItem(DataValue $value, array $base, Collection $all): ?array
    {
        $content = $value->content ?? [];
        
        $messengers = $all->filter(fn (DataValue $dv) => $dv->type->code === 'messenger'
            && $dv->scope_type === $value->scope_type
            && $dv->scope_id === $value->scope_id);

        $groupKey = $this->getMessengerGroupKey($value, $messengers);
        $group = $messengers
            ->filter(fn (DataValue $dv) => $this->getMessengerGroupKey($dv, $messengers) === $groupKey)
            ->sortBy(fn (DataValue $dv) => sprintf(
                '%010d_%010d',
                $dv->created_at?->getTimestamp() ?? 0,
                $dv->id
            ))
            ->values();

        $primary = $group->first();
        
        // If this DataValue is NOT the primary messenger of the group, do not compile it.
        // It will be compiled as part of the primary messenger slot's payload.
        if ($primary && $value->id !== $primary->id) {
            return null;
        }

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

        // Base properties for the primary messenger slot key
        $linkedSlotRaw = $content['linked_slot'] ?? null;
        $linkedSlotStr = null;
        if (is_array($linkedSlotRaw)) {
            $linkedSlotStr = count($linkedSlotRaw) > 0 ? (string) $linkedSlotRaw[0] : null;
        } elseif (is_string($linkedSlotRaw) && $linkedSlotRaw !== '') {
            $linkedSlotStr = $linkedSlotRaw;
        }

        $network = $content['network'] ?? 'unknown';
        $base += [
            'network' => $network,
            'name' => $content['value'] ?? ($content['name'] ?? $value->key),
            'linked_slot' => $linkedSlotStr,
            'messenger_slot' => $content['messenger_slot'] ?? $linkedSlotStr ?? null,
            'pinned' => (bool) ($content['pinned'] ?? false),
            'return_mode' => $content['return_mode'] ?? 'auto',
        ];

        if (! $currentId) {
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

        // If the active/current messenger itself is hidden or disabled, hide the slot
        $currentContent = $current->content ?? [];
        if (($current->status ?? 'active') === 'hidden' || ($currentContent['enabled'] ?? true) === false) {
            return $base + [
                'state' => 'hidden',
                'value' => null,
                'url' => $currentContent['url'] ?? null,
            ];
        }

        // If the active messenger is different from the primary slot, override base properties
        if ($current->id !== $primary->id) {
            $currentLinkedSlotRaw = $currentContent['linked_slot'] ?? null;
            $currentLinkedSlotStr = null;
            if (is_array($currentLinkedSlotRaw)) {
                $currentLinkedSlotStr = count($currentLinkedSlotRaw) > 0 ? (string) $currentLinkedSlotRaw[0] : null;
            } elseif (is_string($currentLinkedSlotRaw) && $currentLinkedSlotRaw !== '') {
                $currentLinkedSlotStr = $currentLinkedSlotRaw;
            }

            $base['network'] = $currentContent['network'] ?? 'unknown';
            $base['name'] = $currentContent['value'] ?? ($currentContent['name'] ?? $current->key);
            $base['linked_slot'] = $currentLinkedSlotStr ?? $linkedSlotStr;
        }

        return $base + [
            'state' => $current->id === $primary->id ? 'ok' : 'on_reserve',
            'value' => $currentContent['value'] ?? null,
            'url' => $currentContent['url'] ?? null,
            'is_current' => true,
        ];
    }

    private function messengerUrlFromValue(?string $value): ?string
    {
        return $value && preg_match('/^https?:\/\//i', $value) ? $value : null;
    }

    private function getMessengerGroupKey(DataValue $value, Collection $allMessengers): string
    {
        $content = $value->content ?? [];
        $messengerSlot = $content['messenger_slot'] ?? null;
        if ($messengerSlot) {
            $parent = $allMessengers->first(fn (DataValue $dv) => $dv->key === $messengerSlot);
            if ($parent) {
                $parentContent = $parent->content ?? [];
                $parentLinkedSlotRaw = $parentContent['linked_slot'] ?? null;
                $parentLinkedSlotStr = null;
                if (is_array($parentLinkedSlotRaw)) {
                    $parentLinkedSlotStr = count($parentLinkedSlotRaw) > 0 ? (string) $parentLinkedSlotRaw[0] : null;
                } elseif (is_string($parentLinkedSlotRaw) && $parentLinkedSlotRaw !== '') {
                    $parentLinkedSlotStr = $parentLinkedSlotRaw;
                }
                return $parentLinkedSlotStr ?? $parent->key;
            }
            return $messengerSlot;
        }

        $linkedSlotRaw = $content['linked_slot'] ?? null;
        $linkedSlotStr = null;
        if (is_array($linkedSlotRaw)) {
            $linkedSlotStr = count($linkedSlotRaw) > 0 ? (string) $linkedSlotRaw[0] : null;
        } elseif (is_string($linkedSlotRaw) && $linkedSlotRaw !== '') {
            $linkedSlotStr = $linkedSlotRaw;
        }

        return $linkedSlotStr ?? $value->key;
    }
}
