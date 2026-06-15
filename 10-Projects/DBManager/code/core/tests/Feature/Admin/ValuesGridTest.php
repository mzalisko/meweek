<?php

namespace Tests\Feature\Admin;

use App\Livewire\ValuesGrid;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ValuesGridTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_shows_site_values_grouped_by_type(): void
    {
        $site = Site::factory()->create();
        DataValue::factory()->forSite($site)->create(['key' => 'price_basic', 'content' => ['value' => '1200']]);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->assertSee('price_basic')
            ->assertSee('1200');
    }

    public function test_defaults_to_first_site_when_none_given(): void
    {
        $site = Site::factory()->create(['domain' => 'domen.ua']);
        DataValue::factory()->forSite($site)->create(['key' => 'k1']);

        Livewire::test(ValuesGrid::class)
            ->assertSee('domen.ua')
            ->assertSee('k1');
    }
}
