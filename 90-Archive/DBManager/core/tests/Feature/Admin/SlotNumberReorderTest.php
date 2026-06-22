<?php

namespace Tests\Feature\Admin;

use App\Livewire\SlotPanel;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class SlotNumberReorderTest extends TestCase
{
    use RefreshDatabase, BuildsSlots;

    public function test_move_up_swaps_with_upper_neighbour(): void
    {
        $this->actingAs(User::factory()->create());

        [$slot, $entries] = $this->slotWithNumbers(['active', 'active', 'active']);
        // entries[0] priority=0, entries[1] priority=1, entries[2] priority=2

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('moveUp', $entries[1]->id);

        $this->assertSame(0, $entries[1]->fresh()->priority);
        $this->assertSame(1, $entries[0]->fresh()->priority);
        // entries[2] untouched
        $this->assertSame(2, $entries[2]->fresh()->priority);
    }

    public function test_move_down_swaps_with_lower_neighbour(): void
    {
        $this->actingAs(User::factory()->create());

        [$slot, $entries] = $this->slotWithNumbers(['active', 'active', 'active']);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('moveDown', $entries[1]->id);

        $this->assertSame(2, $entries[1]->fresh()->priority);
        $this->assertSame(1, $entries[2]->fresh()->priority);
        $this->assertSame(0, $entries[0]->fresh()->priority);
    }

    public function test_move_up_on_topmost_entry_does_nothing(): void
    {
        $this->actingAs(User::factory()->create());

        [$slot, $entries] = $this->slotWithNumbers(['active', 'active', 'active']);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('moveUp', $entries[0]->id);

        $this->assertSame(0, $entries[0]->fresh()->priority);
        $this->assertSame(1, $entries[1]->fresh()->priority);
        $this->assertSame(2, $entries[2]->fresh()->priority);
    }

    public function test_move_down_on_bottommost_entry_does_nothing(): void
    {
        $this->actingAs(User::factory()->create());

        [$slot, $entries] = $this->slotWithNumbers(['active', 'active', 'active']);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('moveDown', $entries[2]->id);

        $this->assertSame(0, $entries[0]->fresh()->priority);
        $this->assertSame(1, $entries[1]->fresh()->priority);
        $this->assertSame(2, $entries[2]->fresh()->priority);
    }

    public function test_move_up_creates_audit_log(): void
    {
        $this->actingAs(User::factory()->create());

        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('moveUp', $entries[1]->id);

        $this->assertTrue(
            AuditLog::where('action', 'slot.reordered')
                ->where('subject_type', 'phone_slot')
                ->where('subject_id', $slot->id)
                ->exists()
        );
    }

    public function test_move_down_creates_audit_log(): void
    {
        $this->actingAs(User::factory()->create());

        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('moveDown', $entries[0]->id);

        $this->assertTrue(
            AuditLog::where('action', 'slot.reordered')
                ->where('subject_type', 'phone_slot')
                ->where('subject_id', $slot->id)
                ->exists()
        );
    }
}
