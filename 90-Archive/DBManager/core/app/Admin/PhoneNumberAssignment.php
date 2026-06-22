<?php

namespace App\Admin;

use App\Models\NumberEntry;
use App\Models\PhoneNumber;

class PhoneNumberAssignment
{
    public function assign(NumberEntry $entry, string $e164): NumberEntry
    {
        $entry->loadMissing('phoneNumber');

        $currentNumberId = (int) $entry->phone_number_id;
        $existing = PhoneNumber::where('e164', $e164)->first();

        if ($existing && (int) $existing->id !== $currentNumberId) {
            $entry->update(['phone_number_id' => $existing->id]);

            return $entry->fresh(['slot.dataValue', 'phoneNumber']);
        }

        $isShared = NumberEntry::where('phone_number_id', $currentNumberId)
            ->whereKeyNot($entry->id)
            ->exists();

        if ($isShared) {
            $target = $existing ?: PhoneNumber::create([
                'e164' => $e164,
                'status' => 'active',
            ]);
            $entry->update(['phone_number_id' => $target->id]);

            return $entry->fresh(['slot.dataValue', 'phoneNumber']);
        }

        $entry->phoneNumber->update(['e164' => $e164]);

        return $entry->fresh(['slot.dataValue', 'phoneNumber']);
    }
}
