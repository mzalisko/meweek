<?php

namespace Tests\Feature\Admin;

use App\Livewire\ValuesGrid;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_switching_site_reloads_values(): void
    {
        $this->actingAs(User::factory()->create());
        $a = Site::factory()->create(['domain' => 'a.ua']);
        $b = Site::factory()->create(['domain' => 'b.ua']);
        DataValue::factory()->forSite($a)->create(['key' => 'only_a']);
        DataValue::factory()->forSite($b)->create(['key' => 'only_b']);

        Livewire::test(ValuesGrid::class, ['site' => $a->id])
            ->assertSee('only_a')->assertDontSee('only_b')
            ->set('site', $b->id)
            ->assertSee('only_b')->assertDontSee('only_a');
    }
}
