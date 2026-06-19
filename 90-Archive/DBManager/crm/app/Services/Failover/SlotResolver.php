<?php

namespace App\Services\Failover;

use App\Models\PhoneSlot;

class SlotResolver
{
    public function resolve(PhoneSlot $slot): ResolvedSlot
    {
        $slot->loadMissing('entries.phoneNumber');

        if ($slot->pinned_number_entry_id) {
            $entry = $slot->entries->firstWhere('id', $slot->pinned_number_entry_id);
            if ($entry) {
                return new ResolvedSlot('pinned', $entry->phoneNumber->e164, $entry->id, true);
            }
        }

        $current = $slot->entries->firstWhere('id', $slot->current_number_entry_id);
        if ($current && $current->phoneNumber->status === 'active') {
            $state = $current->priority === 0 ? 'ok' : 'on_reserve';

            return new ResolvedSlot($state, $current->phoneNumber->e164, $current->id, true);
        }

        return match ($slot->exhaustion_policy) {
            'show_last' => new ResolvedSlot('exhausted', $slot->last_active_e164, null, $slot->last_active_e164 !== null),
            'emergency' => new ResolvedSlot('exhausted', $slot->emergency_number, null, $slot->emergency_number !== null),
            default => new ResolvedSlot('exhausted', null, null, false),
        };
    }
}
