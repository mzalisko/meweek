<?php

namespace App\Admin;

use App\Models\DataValue;
use App\Models\Site;
use App\Models\ValueType;
use App\Services\Failover\SlotResolver;
use Illuminate\Support\Collection;

class SiteGridReader
{
    public function __construct(private SlotResolver $resolver) {}

    /**
     * Повертає рядки гріда для сайта, згруповані за типом.
     *
     * @return array<string, array<int, array>> тип → рядки
     */
    public function forSite(Site $site): array
    {
        $with = ['type', 'geoTags', 'phoneSlot.entries.phoneNumber'];

        $group = $site->site_group_id
            ? DataValue::with($with)
                ->whereIn('status', ['active', 'hidden'])
                ->where('scope_type', 'group')
                ->where('scope_id', $site->site_group_id)
                ->get()
            : collect();

        $own = DataValue::with($with)
            ->whereIn('status', ['active', 'hidden'])
            ->where('scope_type', 'site')
            ->where('scope_id', $site->id)
            ->get();

        $ownKeys = $own->pluck('key')->flip();

        // Власні значення сайта перекривають групові (те саме, що SitePayloadCompiler::effectiveValues)
        /** @var Collection<string, DataValue> $effective */
        $effective = $group->toBase()->keyBy('key')->merge($own->toBase()->keyBy('key'));

        $rows = [];
        $messengerGroups = [];
        foreach ($effective as $value) {
            $scope = $ownKeys->has($value->key) ? 'site' : 'group';
            if ($value->type->code === 'messenger') {
                $groupKey = $value->content['linked_slot'] ?? $value->key;
                $messengerGroups[$groupKey] ??= ['scope' => $scope, 'values' => collect()];
                $messengerGroups[$groupKey]['values']->push($value);
                continue;
            }

            $row = $this->buildRow($value, $scope, $effective);
            $rows[$value->type->code][] = $row;
        }

        foreach ($messengerGroups as $group) {
            $rows['messenger'][] = $this->buildMessengerRow($group['values'], $group['scope'], $effective);
        }

        return $rows;
    }

    private function buildRow(DataValue $value, string $scope, Collection $all): array
    {
        $geo = $value->geoTags->pluck('code')->all() ?: ['WORLD'];

        $base = [
            'id'               => $value->id,
            'key'              => $value->key,
            'type'             => $value->type->code,
            'geo'              => $geo,
            'scope'            => $scope,
            'reserves'         => 0,
            'state'            => 'ok',
            'value'            => $value->content['value'] ?? null,
            'url'              => $value->content['url'] ?? null,
            'linked_slot'      => $value->content['linked_slot'] ?? null,
            'pinned'           => (bool) ($value->content['pinned'] ?? false),
            'exhaustion_policy'=> null,
            'messengers'       => [],
        ];

        if ($value->type->code === 'messenger') {
            return $this->buildMessengerRow($value, $base, $all);
        }

        $slot = $value->phoneSlot;
        if ($slot) {
            $base['messengers'] = $this->linkedMessengers($value);

            if (($value->status ?? 'active') === 'hidden') {
                $base['state'] = 'hidden';
                $base['value'] = null;
                $base['numbers'] = [];
                $base['entry_id'] = null;
                $base['reserves'] = 0;
                $base['exhaustion_policy'] = $slot->exhaustion_policy;

                return $base;
            }

            $resolved = $this->resolver->resolve($slot);

            // Резерви = кількість записів після поточного (entries - 1)
            $base['reserves'] = max(0, $slot->entries->count() - 1);
            $base['state']    = $resolved->visible ? $resolved->state : 'hidden';
            $base['value']    = $resolved->visible ? $resolved->number : null;
            $base['entry_id'] = $resolved->entryId;
            $base['exhaustion_policy'] = $slot->exhaustion_policy;
            $base['numbers']  = $slot->entries
                ->sortBy('priority')
                ->map(fn ($entry) => [
                    'entry_id' => $entry->id,
                    'priority' => $entry->priority,
                    'e164' => $entry->phoneNumber?->e164,
                    'status' => $entry->phoneNumber?->status,
                    'is_current' => $entry->id === $resolved->entryId,
                    'is_pinned' => $entry->id === $slot->pinned_number_entry_id,
                ])
                ->values()
                ->all();
        }

        return $base;
    }

    private function buildMessengerRow(Collection $group, string $scope, Collection $all): array
    {
        $first = $group->first();
        $groupKey = $first ? ($first->content['linked_slot'] ?? $first->key) : '';

        $groupMembers = $group->sortBy(fn (DataValue $dv) => sprintf(
            '%d_%d_%010d_%010d',
            (bool) ($dv->content['pinned'] ?? false) ? 0 : 1,
            ($dv->status ?? 'active') === 'active' && ($dv->content['enabled'] ?? true) ? 0 : 1,
            $dv->created_at?->getTimestamp() ?? 0,
            $dv->id
        ))->values();

        /** @var DataValue $value */
        $value = $groupMembers
            ->first(fn (DataValue $dv) => ($dv->status ?? 'active') === 'active' && ($dv->content['enabled'] ?? true))
            ?? $groupMembers->first();

        $content = $value->content ?? [];
        $base = [
            'id'               => $value->id,
            'key'              => $value->key,
            'type'             => $value->type->code,
            'geo'              => $value->geoTags->pluck('code')->all() ?: ['WORLD'],
            'scope'            => $scope,
            'reserves'         => 0,
            'state'            => 'ok',
            'value'            => $content['value'] ?? ($content['name'] ?? $value->key),
            'url'              => $content['url'] ?? null,
            'linked_slot'      => $content['linked_slot'] ?? null,
            'pinned'           => (bool) ($content['pinned'] ?? false),
            'exhaustion_policy'=> null,
            'messengers'       => [],
            'name'             => $content['value'] ?? ($content['name'] ?? $value->key),
            'network'          => $content['network'] ?? 'unknown',
            'enabled'          => $content['enabled'] ?? true,
            'is_current'       => true,
            'group_key'        => $groupKey,
            'chain_label'      => '#1',
        ];

        $base['reserves'] = max(0, $groupMembers->count() - 1);
        $base['reserve_rows'] = $groupMembers
            ->filter(fn (DataValue $dv) => $dv->id !== $value->id)
            ->values()
            ->map(function (DataValue $dv, int $index) use ($value) {
                $content = $dv->content ?? [];

                return [
                    'id' => $dv->id,
                    'key' => $dv->key,
                    'label' => '#1.' . ($index + 1),
                    'name' => $content['value'] ?? ($content['name'] ?? $dv->key),
                    'network' => $content['network'] ?? 'unknown',
                    'value' => $content['value'] ?? null,
                    'url' => $content['url'] ?? null,
                    'state' => (($dv->status ?? 'active') === 'active' && ($content['enabled'] ?? true)) ? (($dv->id === $value->id) ? 'ok' : 'on_reserve') : 'hidden',
                    'pinned' => (bool) ($content['pinned'] ?? false),
                ];
            })
            ->all();

        if (($value->status ?? 'active') === 'hidden' || ! $base['enabled']) {
            $base['state'] = 'hidden';
            $base['value'] = null;
            return $base;
        }

        $base['state'] = 'ok';
        $base['is_current'] = true;
        $base['group_key'] = $groupKey;

        return $base;
    }

    private function linkedMessengers(DataValue $slotValue): array
    {
        $messengerTypeId = ValueType::where('code', 'messenger')->value('id');
        if (! $messengerTypeId) {
            return [];
        }

        return DataValue::with(['geoTags', 'type'])
            ->where('value_type_id', $messengerTypeId)
            ->where('scope_type', $slotValue->scope_type)
            ->where('scope_id', $slotValue->scope_id)
            ->get()
            ->filter(fn (DataValue $dv) => ($dv->content['linked_slot'] ?? null) === $slotValue->key)
            ->map(fn (DataValue $dv) => [
                'id' => $dv->id,
                'key' => $dv->key,
                'name' => $dv->content['value'] ?? ($dv->content['name'] ?? $dv->key),
                'network' => $dv->content['network'] ?? 'unknown',
                'url' => $dv->content['url'] ?? null,
                'linked_slot' => $dv->content['linked_slot'] ?? null,
                'enabled' => $dv->content['enabled'] ?? true,
                'geo' => $dv->geoTags->pluck('code')->all() ?: ['WORLD'],
            ])
            ->values()
            ->all();
    }
}
