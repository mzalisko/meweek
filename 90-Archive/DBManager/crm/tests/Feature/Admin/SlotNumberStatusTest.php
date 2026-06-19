<?php

namespace Tests\Feature\Admin;

use App\Livewire\SlotPanel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class SlotNumberStatusTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSlots;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_restore_down_number_marks_active(): void
    {
        [$slot, $entries] = $this->slotWithNumbers(['down']);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('setNumberStatus', $entries[0]->id, 'active');

        $this->assertSame('active', $entries[0]->phoneNumber->fresh()->status);
    }

    public function test_deactivate_active_number_marks_down(): void
    {
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('setNumberStatus', $entries[0]->id, 'down');

        $this->assertSame('down', $entries[0]->phoneNumber->fresh()->status);
    }

    public function test_invalid_status_is_ignored(): void
    {
        [$slot, $entries] = $this->slotWithNumbers(['down']);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('setNumberStatus', $entries[0]->id, 'bogus');

        $this->assertSame('down', $entries[0]->phoneNumber->fresh()->status);
    }
}
