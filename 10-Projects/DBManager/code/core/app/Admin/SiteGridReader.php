<?php

namespace App\Admin;

use App\Models\DataValue;
use App\Models\Site;
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
                $groupKey = $value->content['messenger_slot'] ?? $value->key;
                $messengerGroups[$groupKey] ??= ['scope' => $scope, 'values' => collect()];
                $messengerGroups[$groupKey]['values']->push($value);
                continue;
            }

            $row = $this->buildRow($value, $scope, $effective);
            $rows[$value->type->code][] = $row;
        }

        $linkedMessengersByPhone = [];
        foreach ($messengerGroups as $groupKey => $group) {
            $row = $this->buildMessengerRow($group['values'], $group['scope']);
            $rows['messenger'][] = $row;

            $primary = $group['values']->first(fn (DataValue $v) => ! isset($v->content['messenger_slot']));
            $raw = $primary ? ($primary->content['linked_slot'] ?? null) : null;
            $linkedSlots = is_array($raw) ? $raw : (is_string($raw) && $raw !== '' ? [$raw] : []);
            foreach ($linkedSlots as $linkedSlot) {
                $linkedMessengersByPhone[$linkedSlot][] = [
                    'id'          => $row['id'],
                    'network'     => $row['network'] ?? 'msg',
                    'linked_slot' => $linkedSlot,
                ];
            }
        }

        if (isset($rows['phone'])) {
            foreach ($rows['phone'] as &$phoneRow) {
                $phoneRow['messengers'] = $linkedMessengersByPhone[$phoneRow['key']] ?? [];
            }
            unset($phoneRow);
            usort($rows['phone'], fn ($a, $b) => strcmp($a['key'], $b['key']));
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
            'linked_slot'      => null,
            'pinned'           => (bool) ($value->content['pinned'] ?? false),
            'exhaustion_policy'=> null,
        ];

        $slot = $value->phoneSlot;
        if ($slot) {
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

    private function buildMessengerRow(Collection $group, string $scope): array
    {
        $first = $group->first();
        $groupKey = $first ? ($first->content['messenger_slot'] ?? $first->key) : '';
        $policy = $first->content['exhaustion_policy'] ?? 'hide';
        $returnMode = $first->content['return_mode'] ?? 'auto';
        $currentMessengerId = $first->content['current_messenger_id'] ?? null;

        $groupMembers = $group->sortBy(fn (DataValue $dv) => sprintf(
            '%010d_%010d',
            $dv->created_at?->getTimestamp() ?? 0,
            $dv->id
        ))->values();

        /** @var DataValue $value */
        $value = $groupMembers->first();
        $current = $groupMembers
            ->first(fn (DataValue $dv) => (bool) ($dv->content['pinned'] ?? false)
                && ($dv->status ?? 'active') === 'active'
                && ($dv->content['enabled'] ?? true))
            ?? ($returnMode === 'sticky' && $currentMessengerId
                ? $groupMembers->first(fn (DataValue $dv) => (int) $dv->id === (int) $currentMessengerId
                    && ($dv->status ?? 'active') === 'active'
                    && ($dv->content['enabled'] ?? true))
                : null)
            ?? $groupMembers
                ->first(fn (DataValue $dv) => ($dv->status ?? 'active') === 'active' && ($dv->content['enabled'] ?? true));
        $hasVisible = (bool) $current;

        $content = $value->content ?? [];
        $currentContent = $current?->content ?? [];
        $currentValue = $current
            ? ($currentContent['value'] ?? ($currentContent['name'] ?? $current->key))
            : match ($policy) {
                'emergency' => $content['emergency_value'] ?? null,
                'last' => $content['last_active_value'] ?? ($content['value'] ?? ($content['name'] ?? $value->key)),
                default => null,
            };
        $base = [
            'id'               => $value->id,
            'key'              => $groupKey,
            'type'             => $value->type->code,
            'geo'              => $value->geoTags->pluck('code')->all() ?: ['WORLD'],
            'scope'            => $scope,
            'reserves'         => 0,
            'state'            => 'ok',
            'value'            => $currentValue,
            'url'              => $currentContent['url'] ?? ($content['url'] ?? null),
            'linked_slot'      => (function ($raw) {
                if (is_array($raw)) {
                    return array_values(array_filter($raw));
                }
                return (is_string($raw) && $raw !== '') ? [$raw] : [];
            })($content['linked_slot'] ?? null),
            'pinned'           => (bool) ($content['pinned'] ?? false),
            'exhaustion_policy'=> $policy,
            'return_mode'      => $returnMode,
            'emergency_value'  => $content['emergency_value'] ?? null,
            'name'             => $content['value'] ?? ($content['name'] ?? $value->key),
            'network'          => $content['network'] ?? 'unknown',
            'enabled'          => $content['enabled'] ?? true,
            'is_current'       => $current?->id === $value->id,
            'group_key'        => $groupKey,
            'chain_label'      => '#1',
        ];

        $base['reserves'] = max(0, $groupMembers->count() - 1);
        $base['reserve_rows'] = $groupMembers
            ->filter(fn (DataValue $dv) => $dv->id !== $value->id)
            ->values()
            ->map(function (DataValue $dv, int $index) use ($current) {
                $content = $dv->content ?? [];

                return [
                    'id' => $dv->id,
                    'key' => $dv->key,
                    'label' => '#1.' . ($index + 1),
                    'name' => $content['value'] ?? ($content['name'] ?? $dv->key),
                    'network' => $content['network'] ?? 'unknown',
                    'value' => $content['value'] ?? null,
                    'url' => $content['url'] ?? null,
                    'state' => (($dv->status ?? 'active') === 'active' && ($content['enabled'] ?? true))
                        ? (($dv->id === $current?->id) ? 'on_reserve' : 'ok')
                        : 'hidden',
                    'pinned' => (bool) ($content['pinned'] ?? false),
                    'is_current' => $dv->id === $current?->id,
                ];
            })
            ->all();

        if (! $hasVisible) {
            $base['state'] = in_array($policy, ['last', 'emergency'], true) ? 'exhausted' : 'hidden';
            return $base;
        }

        $base['state'] = match (true) {
            (bool) ($currentContent['pinned'] ?? false) => 'pinned',
            $current?->id !== $value->id => 'on_reserve',
            default => 'ok',
        };
        $base['is_current'] = $current?->id === $value->id;
        $base['group_key'] = $groupKey;

        return $base;
    }
}
