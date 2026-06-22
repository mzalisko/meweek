<?php

namespace Tests\Feature\Admin;

use App\Livewire\SlotPanel;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class SlotBehaviorTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSlots;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_set_return_mode_sticky_updates_slot_and_recomputes(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('setReturnMode', 'sticky');

        $this->assertSame('sticky', $slot->fresh()->return_mode);
    }

    public function test_set_return_mode_auto_updates_slot(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active'], ['return_mode' => 'sticky']);
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('setReturnMode', 'auto');

        $this->assertSame('auto', $slot->fresh()->return_mode);
    }

    public function test_set_return_mode_invalid_value_leaves_unchanged(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active'], ['return_mode' => 'auto']);
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('setReturnMode', 'invalid');

        $this->assertSame('auto', $slot->fresh()->return_mode);
    }

    public function test_set_exhaustion_policy_last_updates_slot(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('setExhaustionPolicy', 'last');

        $this->assertSame('last', $slot->fresh()->exhaustion_policy);
    }

    public function test_set_exhaustion_policy_emergency_updates_slot(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('setExhaustionPolicy', 'emergency');

        $this->assertSame('emergency', $slot->fresh()->exhaustion_policy);
    }

    public function test_set_exhaustion_policy_invalid_value_leaves_unchanged(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active'], ['exhaustion_policy' => 'hide']);
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('setExhaustionPolicy', 'bogus');

        $this->assertSame('hide', $slot->fresh()->exhaustion_policy);
    }

    public function test_set_return_mode_reloads_panel(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('setReturnMode', 'sticky')
            ->assertSee('sticky');
    }
}
