<?php

namespace Tests\Feature\Admin;

use App\Livewire\SlotPanel;
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

    public function test_opening_slot_shows_number_chain(): void
    {
        $site = Site::factory()->create();
        [$slot, $entries] = $this->slotWithNumbers(['down', 'active']);
        $slot->dataValue->update(['key' => 'phone_ua_1', 'scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(SlotPanel::class)
            ->call('open', $slot->dataValue->id)
            ->assertSee('phone_ua_1')
            ->assertSee($entries[1]->phoneNumber->e164) // активний резерв показується
            ->assertSet('open', true);
    }

    public function test_closed_by_default(): void
    {
        Livewire::test(SlotPanel::class)->assertSet('open', false);
    }
}
