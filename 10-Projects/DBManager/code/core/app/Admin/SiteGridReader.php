<?php

namespace App\Admin;

use App\Models\DataValue;
use App\Models\Site;
use App\Services\Failover\SlotResolver;
use App\Support\PhoneFormatter;
use Illuminate\Support\Collection;

class SiteGridReader
{
    public function __construct(private SlotResolver $resolver) {}

    /**
     * @return array<string, array<int, array>>
     */
    public function forSite(Site $site): array
    {
        $ownValues = DataValue::with(['type', 'geoTags', 'phoneSlot.entries.phoneNumber'])
            ->whereIn('status', ['active', 'hidden'])
            ->where('scope_type', 'site')
            ->where('scope_id', $site->id)
            ->get();

        $rows = [];
        $messengerGroups = [];

        foreach ($ownValues as $value) {
            $scope = 'site';
            $src = ['kind' => 'current_site', 'label' => 'цього сайту', 'site_id' => (int) $site->id];

            if ($value->type->code === 'messenger') {
                $groupKey = $value->content['messenger_slot'] ?? $value->key;
                $messengerGroups[$groupKey] ??= ['scope' => $scope, 'source' => $src, 'values' => collect()];
                $messengerGroups[$groupKey]['values']->push($value);
                continue;
            }

            $rows[$value->type->code][] = $this->buildRow($value, $scope, $src);
        }

        $linkedMessengersByPhone = [];
        foreach ($messengerGroups as $group) {
            $row = $this->buildMessengerRow($group['values'], $group['scope'], $group['source']);
            $rows['messenger'][] = $row;

            $primary = $group['values']->first(fn (DataValue $v) => ! isset($v->content['messenger_slot']));
            $raw = $primary ? ($primary->content['linked_slot'] ?? null) : null;
            $linkedSlots = is_array($raw) ? $raw : (is_string($raw) && $raw !== '' ? [$raw] : []);

            foreach ($linkedSlots as $linkedSlot) {
                $linkedMessengersByPhone[$linkedSlot][] = [
                    'id' => $row['id'],
                    'network' => $row['network'] ?? 'msg',
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

    private function buildRow(DataValue $value, string $scope, array $source): array
    {
        $geo = $value->geoTags->pluck('code')->all() ?: ['WORLD'];
        $base = [
            'id' => $value->id,
            'key' => $value->key,
            'type' => $value->type->code,
            'geo' => $geo,
            'scope' => $scope,
            'source' => $source['kind'],
            'source_label' => $source['label'],
            'source_site_id' => $source['site_id'],
            'reserves' => 0,
            'state' => 'ok',
            'value' => $value->content['value'] ?? null,
            'url' => $value->content['url'] ?? null,
            'linked_slot' => null,
            'pinned' => (bool) ($value->content['pinned'] ?? false),
            'exhaustion_policy' => null,
        ];

        if ($value->type->code === 'price') {
            $base['prices'] = $value->content['prices'] ?? [];
            $base['state'] = ($value->status ?? 'active') === 'hidden' ? 'hidden' : 'ok';
            return $base;
        }

        $slot = $value->phoneSlot;
        if (! $slot) {
            return $base;
        }

        $resolved = $this->resolver->resolve($slot);
        $phoneFormat = trim((string) ($value->content['phone_format'] ?? ''));
        $display = fn (?string $number): ?string => PhoneFormatter::format($number, $phoneFormat);
        $numbers = $slot->entries
            ->sortBy('priority')
            ->map(fn ($entry) => [
                'entry_id' => $entry->id,
                'priority' => $entry->priority,
                'e164' => $entry->phoneNumber?->e164,
                'display_value' => $display($entry->phoneNumber?->e164),
                'status' => $entry->phoneNumber?->status,
                'is_current' => $entry->id === $resolved->entryId,
                'is_pinned' => $entry->id === $slot->pinned_number_entry_id,
            ])
            ->values()
            ->all();

        if (($value->status ?? 'active') === 'hidden') {
            return array_merge($base, [
                'state' => 'hidden',
                'value' => $resolved->number ?? $slot->last_active_e164,
                'display_value' => $display($resolved->number ?? $slot->last_active_e164),
                'phone_format' => $phoneFormat,
                'numbers' => $numbers,
                'entry_id' => $resolved->entryId,
                'reserves' => max(0, $slot->entries->count() - 1),
                'exhaustion_policy' => $slot->exhaustion_policy,
            ]);
        }

        return array_merge($base, [
            'reserves' => max(0, $slot->entries->count() - 1),
            'state' => $resolved->visible ? $resolved->state : 'hidden',
            'value' => $resolved->visible ? $resolved->number : null,
            'display_value' => $resolved->visible ? $display($resolved->number) : null,
            'phone_format' => $phoneFormat,
            'entry_id' => $resolved->entryId,
            'exhaustion_policy' => $slot->exhaustion_policy,
            'numbers' => $numbers,
        ]);
    }

    private function buildMessengerRow(Collection $group, string $scope, array $source): array
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
            ?? $groupMembers->first(fn (DataValue $dv) => ($dv->status ?? 'active') === 'active' && ($dv->content['enabled'] ?? true));

        $currentValue = $current
            ? (($current->content['value'] ?? ($current->content['name'] ?? $current->key)))
            : match ($policy) {
                'emergency' => $value->content['emergency_value'] ?? null,
                'last' => $value->content['last_active_value'] ?? ($value->content['value'] ?? ($value->content['name'] ?? $value->key)),
                default => null,
            };

        $base = [
            'id' => $value->id,
            'key' => $groupKey,
            'type' => $value->type->code,
            'geo' => $value->geoTags->pluck('code')->all() ?: ['WORLD'],
            'scope' => $scope,
            'source' => $source['kind'],
            'source_label' => $source['label'],
            'source_site_id' => $source['site_id'],
            'reserves' => 0,
            'state' => 'ok',
            'value' => $currentValue,
            'url' => $current?->content['url'] ?? ($value->content['url'] ?? null),
            'linked_slot' => (function ($raw) {
                if (is_array($raw)) {
                    return array_values(array_filter($raw));
                }

                return (is_string($raw) && $raw !== '') ? [$raw] : [];
            })($value->content['linked_slot'] ?? null),
            'pinned' => (bool) ($value->content['pinned'] ?? false),
            'exhaustion_policy' => $policy,
            'return_mode' => $returnMode,
            'emergency_value' => $value->content['emergency_value'] ?? null,
            'name' => $value->content['value'] ?? ($value->content['name'] ?? $value->key),
            'network' => $value->content['network'] ?? 'unknown',
            'enabled' => $value->content['enabled'] ?? true,
            'is_current' => $current?->id === $value->id,
            'group_key' => $groupKey,
            'chain_label' => '#1',
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

        if (! $current) {
            $base['state'] = in_array($policy, ['last', 'emergency'], true) ? 'exhausted' : 'hidden';

            return $base;
        }

        $base['state'] = match (true) {
            (bool) ($current->content['pinned'] ?? false) => 'pinned',
            $current?->id !== $value->id => 'on_reserve',
            default => 'ok',
        };
        $base['is_current'] = $current?->id === $value->id;
        $base['group_key'] = $groupKey;

        return $base;
    }
}
