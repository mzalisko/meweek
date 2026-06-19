<?php

namespace Tests\Feature\Admin;

use App\Livewire\SlotPanel;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class SlotPanelTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSlots;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_opening_slot_shows_slot_settings_with_reserve_numbers_chain(): void
    {
        $site = Site::factory()->create();
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);
        $slot->dataValue->update(['key' => 'phone_ua_1', 'scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->assertSee('phone_ua_1')
            ->assertSee('гео-мітки')
            ->assertSee('Повернення')
            ->assertSee('Якщо всі впали')
            ->assertSee($entries[1]->phoneNumber->e164)
            ->assertSet('open', true);
    }

    public function test_closed_by_default(): void
    {
        Livewire::test(SlotPanel::class)->assertSet('open', false);
    }

    public function test_opening_exhausted_emergency_slot_shows_emergency_number(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['down', 'down']);
        $slot->dataValue->update([
            'key' => 'phone_ua_1',
            'scope_type' => 'site',
            'scope_id' => $site->id,
        ]);
        $slot->update([
            'exhaustion_policy' => 'emergency',
            'emergency_number' => '+380991112233',
        ]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->assertSee('Всі номери вичерпано')
            ->assertSee('Зараз показується аварійний номер')
            ->assertSee('+380991112233');
    }

    public function test_slot_can_be_hidden_and_restored(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active', 'active']);
        $slot->dataValue->update([
            'key' => 'phone_ua_1',
            'scope_type' => 'site',
            'scope_id' => $site->id,
        ]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('hideSlot');

        $this->assertSame('hidden', $slot->dataValue->fresh()->status);
        $this->assertTrue(DataValue::where('id', $slot->dataValue->id)->where('status', 'hidden')->exists());
        $this->assertTrue(
            AuditLog::where('action', 'slot.hidden')
                ->where('subject_id', $slot->dataValue->id)
                ->exists()
        );

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->call('showSlot');

        $this->assertSame('active', $slot->dataValue->fresh()->status);
        $this->assertTrue(
            AuditLog::where('action', 'slot.shown')
                ->where('subject_id', $slot->dataValue->id)
                ->exists()
        );
    }
}
