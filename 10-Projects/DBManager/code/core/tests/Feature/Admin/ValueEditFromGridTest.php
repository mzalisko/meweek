<?php

namespace Tests\Feature\Admin;

use App\Livewire\ValuesGrid;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\User;
use App\Models\ValueType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class ValueEditFromGridTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSlots;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_editValue_dispatches_edit_value_event(): void
    {
        $site = Site::factory()->create();
        $dv = DataValue::factory()->forSite($site)->ofType('price')->create(['key' => 'price_basic']);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->call('editValue', $dv->id)
            ->assertDispatched('edit-value', valueId: $dv->id);
    }

    public function test_addValue_dispatches_open_value_editor_event(): void
    {
        $site = Site::factory()->create();

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->call('addValue')
            ->assertDispatched('open-value-editor', siteId: $site->id);
    }

    public function test_phone_rows_still_use_openSlot(): void
    {
        // Phone rows keep openSlot — no regression
        $site = Site::factory()->create();
        $phoneType = ValueType::firstOrCreate(['code' => 'phone'], ['name' => 'Телефон']);
        $dv = DataValue::factory()->forSite($site)->create([
            'key'           => 'phone_main',
            'value_type_id' => $phoneType->id,
        ]);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->call('openSlot', $dv->id)
            ->assertDispatched('open-slot', dataValueId: $dv->id);
    }

    public function test_phone_pencil_starts_inline_number_edit(): void
    {
        $site = Site::factory()->create();
        [$slot, $entries] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['key' => 'phone_main', 'scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->call('startInlinePhoneEdit', $entries[0]->id)
            ->assertSet('editingPhoneEntryId', $entries[0]->id)
            ->assertSet('editingPhoneNumber', $entries[0]->phoneNumber->e164);
    }

    public function test_inline_phone_edit_updates_current_number_and_audits(): void
    {
        $site = Site::factory()->create();
        [$slot, $entries] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['key' => 'phone_main', 'scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->call('startInlinePhoneEdit', $entries[0]->id)
            ->assertSet('editingPhoneEntryId', $entries[0]->id)
            ->set('editingPhoneNumber', '+380999888777')
            ->call('saveInlinePhoneNumber')
            ->assertSet('editingPhoneEntryId', null)
            ->assertDispatched('toast');

        $this->assertSame('+380999888777', $entries[0]->phoneNumber->fresh()->e164);
        $this->assertTrue(AuditLog::where('action', 'number.edited')->exists());
    }

    public function test_inline_phone_edit_validates_e164(): void
    {
        $site = Site::factory()->create();
        [$slot, $entries] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->call('startInlinePhoneEdit', $entries[0]->id)
            ->set('editingPhoneNumber', 'не номер')
            ->call('saveInlinePhoneNumber')
            ->assertHasErrors('editingPhoneNumber');
    }

    public function test_inline_phone_remove_deletes_entry_and_audits(): void
    {
        $site = Site::factory()->create();
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->call('startInlinePhoneEdit', $entries[1]->id)
            ->call('removeInlinePhoneNumber', $entries[1]->id)
            ->assertSet('editingPhoneEntryId', null)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('number_entries', ['id' => $entries[1]->id]);
        $this->assertTrue(AuditLog::where('action', 'number.removed')->exists());
    }

    public function test_phone_chain_does_not_show_reserves_in_grid(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active', 'active']);
        $slot->dataValue->update(['key' => 'phone_main', 'scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->assertSee('#1 основний')
            ->assertDontSee('#1.1 резерв');
    }

    public function test_down_phone_status_is_rendered_in_ukrainian(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['down', 'active']);
        $slot->dataValue->update(['key' => 'phone_main', 'scope_type' => 'site', 'scope_id' => $site->id]);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->assertSee('● неактивний')
            ->assertDontSee('>down<', false);
    }

    public function test_exhausted_emergency_slot_shows_fallback_badge_in_grid(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['down', 'down']);
        $slot->dataValue->update(['key' => 'phone_main', 'scope_type' => 'site', 'scope_id' => $site->id]);
        $slot->update([
            'exhaustion_policy' => 'emergency',
            'emergency_number' => '+380991112233',
        ]);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->assertSee('аварійний')
            ->assertSee('+380991112233');
    }
}
