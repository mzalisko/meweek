<?php

namespace Tests\Feature\Admin;

use App\Livewire\ValuesGrid;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\User;
use App\Models\ValueType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ValueEditFromGridTest extends TestCase
{
    use RefreshDatabase;

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
}
