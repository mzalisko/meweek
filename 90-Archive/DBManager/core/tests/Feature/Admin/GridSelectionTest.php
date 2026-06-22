<?php

namespace Tests\Feature\Admin;

use App\Livewire\ValuesGrid;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GridSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_toggle_select_adds_and_removes_ids(): void
    {
        $site = Site::factory()->create();
        $a = DataValue::factory()->forSite($site)->create(['key' => 'a']);
        $b = DataValue::factory()->forSite($site)->create(['key' => 'b']);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->call('toggleSelect', $a->id)
            ->assertSet('selected', [$a->id])
            ->call('toggleSelect', $b->id)
            ->assertSet('selected', [$a->id, $b->id])
            ->call('toggleSelect', $a->id)
            ->assertSet('selected', [$b->id]);
    }

    public function test_clear_selection(): void
    {
        $site = Site::factory()->create();
        $a = DataValue::factory()->forSite($site)->create(['key' => 'a']);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->call('toggleSelect', $a->id)
            ->call('clearSelection')
            ->assertSet('selected', []);
    }
}
