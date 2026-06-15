<?php

namespace Tests\Feature\Admin;

use App\Livewire\ValueEditor;
use App\Models\DataValue;
use App\Models\PhoneSlot;
use App\Models\Site;
use App\Models\User;
use App\Models\ValueType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ValueEditorLinkedSlotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_edit_messenger_loads_linked_slot(): void
    {
        $site = Site::factory()->create();
        $dv   = DataValue::factory()->forSite($site)->ofType('messenger')->create([
            'key'     => 'm',
            'content' => ['value' => 'https://t.me/x', 'network' => 'telegram', 'linked_slot' => 'phone_ua_1'],
        ]);

        Livewire::test(ValueEditor::class)
            ->call('edit', $dv->id)
            ->assertSet('linkedSlot', 'phone_ua_1');
    }

    public function test_edit_non_messenger_linked_slot_empty(): void
    {
        $site = Site::factory()->create();
        $dv   = DataValue::factory()->forSite($site)->create(['key' => 'k', 'content' => ['value' => 'v']]);

        Livewire::test(ValueEditor::class)
            ->call('edit', $dv->id)
            ->assertSet('linkedSlot', '');
    }

    public function test_save_messenger_persists_linked_slot(): void
    {
        $site        = Site::factory()->create();
        $phoneType   = ValueType::firstOrCreate(['code' => 'phone'], ['name' => 'phone']);

        $slotDv = DataValue::factory()->forSite($site)->create([
            'key'           => 'phone_ua_1',
            'value_type_id' => $phoneType->id,
            'content'       => [],
        ]);
        PhoneSlot::create(['data_value_id' => $slotDv->id, 'return_mode' => 'auto', 'exhaustion_policy' => 'hide']);

        $dv = DataValue::factory()->forSite($site)->ofType('messenger')->create([
            'key'     => 'm',
            'content' => ['value' => 'https://t.me/x', 'network' => 'telegram'],
        ]);

        Livewire::test(ValueEditor::class)
            ->call('edit', $dv->id)
            ->set('linkedSlot', 'phone_ua_1')
            ->call('save');

        $this->assertSame('phone_ua_1', $dv->fresh()->content['linked_slot'] ?? null);
    }

    public function test_save_messenger_clears_linked_slot_when_empty(): void
    {
        $site = Site::factory()->create();
        $dv   = DataValue::factory()->forSite($site)->ofType('messenger')->create([
            'key'     => 'm',
            'content' => ['value' => 'https://t.me/x', 'network' => 'telegram', 'linked_slot' => 'phone_ua_1'],
        ]);

        Livewire::test(ValueEditor::class)
            ->call('edit', $dv->id)
            ->set('linkedSlot', '')
            ->call('save');

        $this->assertNull($dv->fresh()->content['linked_slot'] ?? null);
    }
}
