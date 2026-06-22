<?php

namespace Tests\Feature\Admin;

use App\Livewire\SlotPanel;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class SlotNumberRemoveTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSlots;

    public function test_remove_number_deletes_entry_and_logs_audit(): void
    {
        $this->actingAs(User::factory()->create());
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);

        $entryToRemove = $entries[1];
        $e164 = $entryToRemove->phoneNumber->e164;

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('removeNumber', $entryToRemove->id);

        $slot->refresh();
        $this->assertSame(1, $slot->entries()->count());
        $this->assertDatabaseMissing('number_entries', ['id' => $entryToRemove->id]);

        $this->assertTrue(
            AuditLog::where('action', 'number.removed')
                ->where('subject_type', 'phone_slot')
                ->where('subject_id', $slot->id)
                ->exists()
        );

        $log = AuditLog::where('action', 'number.removed')->first();
        $this->assertSame($e164, $log->old['e164'] ?? null);
    }

    public function test_remove_number_does_not_delete_shared_phone_number(): void
    {
        $this->actingAs(User::factory()->create());
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);

        $entryToRemove = $entries[0];
        $phoneNumberId = $entryToRemove->phone_number_id;

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('removeNumber', $entryToRemove->id);

        $this->assertDatabaseHas('phone_numbers', ['id' => $phoneNumberId]);
    }

    public function test_remove_entry_belonging_to_different_slot_is_ignored(): void
    {
        $this->actingAs(User::factory()->create());
        [$slot,] = $this->slotWithNumbers(['active', 'active']);
        [$otherSlot, $otherEntries] = $this->slotWithNumbers(['active']);

        $foreignEntry = $otherEntries[0];

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('removeNumber', $foreignEntry->id);

        // Foreign slot's entry must still exist
        $this->assertDatabaseHas('number_entries', ['id' => $foreignEntry->id]);
        // No audit log created
        $this->assertFalse(AuditLog::where('action', 'number.removed')->exists());
        // Our slot still has 2 entries
        $slot->refresh();
        $this->assertSame(2, $slot->entries()->count());
    }
}
