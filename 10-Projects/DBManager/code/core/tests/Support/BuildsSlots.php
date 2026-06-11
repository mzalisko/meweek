<?php

namespace Tests\Support;

use App\Models\NumberEntry;
use App\Models\PhoneNumber;
use App\Models\PhoneSlot;
use App\Services\Failover\FailoverEngine;

trait BuildsSlots
{
    /**
     * Створює слот з ланцюгом номерів за статусами: ['active','down',...]
     * (індекс масиву = priority). Повертає [слот, NumberEntry[]].
     */
    protected function slotWithNumbers(array $statuses, array $slotAttrs = []): array
    {
        $slot = PhoneSlot::factory()->create($slotAttrs);
        $entries = [];
        foreach ($statuses as $priority => $status) {
            $entries[] = NumberEntry::factory()->for($slot, 'slot')->create([
                'priority' => $priority,
                'phone_number_id' => PhoneNumber::factory()->create(['status' => $status])->id,
            ]);
        }
        app(FailoverEngine::class)->recompute($slot->fresh());

        return [$slot->fresh(), $entries];
    }
}
