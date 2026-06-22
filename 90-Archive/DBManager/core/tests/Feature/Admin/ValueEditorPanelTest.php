<?php

namespace Tests\Feature\Admin;

use App\Livewire\SlotPanel;
use App\Livewire\ValueEditor;
use App\Models\DataValue;
use App\Models\PhoneSlot;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ValueEditorPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_edit_value_dispatches_close_slot_panel(): void
    {
        $site = Site::factory()->create();
        $dv   = DataValue::factory()->forSite($site)->create(['key' => 'k']);

        Livewire::test(ValueEditor::class)
            ->call('edit', $dv->id)
            ->assertDispatched('close-slot-panel');
    }

    public function test_create_for_dispatches_close_slot_panel(): void
    {
        $site = Site::factory()->create();

        Livewire::test(ValueEditor::class)
            ->call('createFor', $site->id)
            ->assertDispatched('close-slot-panel');
    }

    public function test_close_editor_panel_event_closes_editor(): void
    {
        $site = Site::factory()->create();

        Livewire::test(ValueEditor::class)
            ->call('createFor', $site->id)
            ->assertSet('open', true)
            ->dispatch('close-editor-panel')
            ->assertSet('open', false);
    }

    public function test_slot_panel_open_dispatches_close_editor_panel(): void
    {
        $site = Site::factory()->create();
        $dv   = DataValue::factory()->forSite($site)->ofType('phone')->create(['key' => 'p', 'content' => []]);
        PhoneSlot::create(['data_value_id' => $dv->id, 'return_mode' => 'auto', 'exhaustion_policy' => 'hide']);

        Livewire::test(SlotPanel::class)
            ->dispatch('open-slot', dataValueId: $dv->id)
            ->assertDispatched('close-editor-panel');
    }
}
