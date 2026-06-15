<?php

namespace Tests\Feature\Admin;

use App\Livewire\SlotPanel;
use App\Models\AuditLog;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class SlotPinTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSlots;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_pin_makes_number_current_and_audits(): void
    {
        $site = Site::factory()->create();
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('pin', $entries[1]->id);

        $this->assertSame($entries[1]->id, $slot->fresh()->pinned_number_entry_id);
        $this->assertTrue(AuditLog::where('action', 'slot.pinned')->exists());
    }

    public function test_pin_reloads_panel_state(): void
    {
        $site = Site::factory()->create();
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('pin', $entries[1]->id)
            ->assertSee($entries[1]->phoneNumber->e164);
    }

    public function test_pin_rejects_entry_not_belonging_to_slot(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active', 'active']);
        [$otherSlot, $otherEntries] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('pin', $otherEntries[0]->id);

        // pinned_number_entry_id must remain null (entry was rejected)
        $this->assertNull($slot->fresh()->pinned_number_entry_id);
    }

    public function test_unpin_clears_pinned_entry_and_audits(): void
    {
        $site = Site::factory()->create();
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('pin', $entries[1]->id)
            ->call('unpin');

        $this->assertNull($slot->fresh()->pinned_number_entry_id);
        $this->assertTrue(AuditLog::where('action', 'slot.unpinned')->exists());
    }
}
