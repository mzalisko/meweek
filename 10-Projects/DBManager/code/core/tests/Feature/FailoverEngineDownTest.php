<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Services\Failover\FailoverEngine;
use App\Services\Failover\SlotResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class FailoverEngineDownTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSlots;

    public function test_down_switches_slot_to_first_active_reserve(): void
    {
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active', 'active']);

        app(FailoverEngine::class)->markNumberDown($entries[0]->phoneNumber);

        $resolved = app(SlotResolver::class)->resolve($slot->fresh());
        $this->assertSame('on_reserve', $resolved->state);
        $this->assertSame($entries[1]->phoneNumber->e164, $resolved->number);
    }

    public function test_down_skips_dead_reserves(): void
    {
        [$slot, $entries] = $this->slotWithNumbers(['active', 'down', 'active']);

        app(FailoverEngine::class)->markNumberDown($entries[0]->phoneNumber);

        $resolved = app(SlotResolver::class)->resolve($slot->fresh());
        $this->assertSame($entries[2]->phoneNumber->e164, $resolved->number);
    }

    public function test_down_writes_audit_and_warning_incident(): void
    {
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);

        app(FailoverEngine::class)->markNumberDown($entries[0]->phoneNumber, 'webhook');

        $this->assertTrue(AuditLog::where('action', 'number.down')->exists());
        $this->assertTrue(
            AuditLog::where('action', 'failover.switch')
                ->where('subject_type', 'phone_slot')
                ->where('subject_id', $slot->id)
                ->exists()
        );
        $this->assertTrue(
            Incident::where('kind', 'failover')->where('severity', 'warning')->exists()
        );
    }

    public function test_exhaustion_creates_single_critical_incident(): void
    {
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);
        $engine = app(FailoverEngine::class);

        $engine->markNumberDown($entries[0]->phoneNumber);
        $engine->markNumberDown($entries[1]->phoneNumber);
        // повторний сигнал по вже мертвому номеру не плодить інциденти
        $engine->markNumberDown($entries[1]->phoneNumber->fresh());

        $resolved = app(SlotResolver::class)->resolve($slot->fresh());
        $this->assertFalse($resolved->visible);
        $this->assertSame(
            1,
            Incident::where('kind', 'slot_exhausted')->where('severity', 'critical')->count()
        );
    }

    public function test_shared_number_down_switches_all_its_slots(): void
    {
        [$slotA, $entriesA] = $this->slotWithNumbers(['active', 'active']);
        [$slotB] = $this->slotWithNumbers(['active', 'active']);
        // спільний номер: основний номер слота A додається в B як резерв із пріоритетом 2
        \App\Models\NumberEntry::factory()->for($slotB, 'slot')->create([
            'priority' => 2,
            'phone_number_id' => $entriesA[0]->phone_number_id,
        ]);

        $affected = app(FailoverEngine::class)->markNumberDown($entriesA[0]->phoneNumber);

        $this->assertTrue($affected->pluck('id')->contains($slotA->id));
        // слот B показує свій №0, тож його вивід не змінився і він не "уражений"
        $this->assertFalse($affected->pluck('id')->contains($slotB->id));
    }

    public function test_pinned_slot_does_not_switch(): void
    {
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);
        $slot->update(['pinned_number_entry_id' => $entries[0]->id]);

        $affected = app(FailoverEngine::class)->markNumberDown($entries[0]->phoneNumber);

        $resolved = app(SlotResolver::class)->resolve($slot->fresh());
        $this->assertSame('pinned', $resolved->state);
        $this->assertSame($entries[0]->phoneNumber->e164, $resolved->number);
        $this->assertFalse($affected->pluck('id')->contains($slot->id));
    }
}
