<?php

namespace Tests\Feature\Admin;

use App\Admin\SiteGridReader;
use App\Livewire\ValuesGrid;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class SlotOpenFromGridTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSlots;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_openSlot_dispatches_open_slot_event(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);

        $dvId = $slot->dataValue->id;

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->call('openSlot', $dvId)
            ->assertDispatched('open-slot', dataValueId: $dvId);
    }

    public function test_phone_rows_include_id_in_grid_reader(): void
    {
        $site = Site::factory()->create();
        [$slot] = $this->slotWithNumbers(['active']);
        $slot->dataValue->update(['scope_type' => 'site', 'scope_id' => $site->id]);

        $dvId = $slot->dataValue->id;

        $rows = app(SiteGridReader::class)->forSite($site);
        $phone = collect($rows['phone'] ?? [])->first();

        $this->assertNotNull($phone, 'phone row must exist');
        $this->assertArrayHasKey('id', $phone);
        $this->assertSame($dvId, $phone['id']);
    }
}
