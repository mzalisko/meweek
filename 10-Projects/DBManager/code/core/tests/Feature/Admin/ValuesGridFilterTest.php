<?php

namespace Tests\Feature\Admin;

use App\Livewire\ValuesGrid;
use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ValuesGridFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_search_filters_by_key(): void
    {
        $site = Site::factory()->create();
        DataValue::factory()->forSite($site)->create(['key' => 'phone_ua_1']);
        DataValue::factory()->forSite($site)->create(['key' => 'price_basic']);

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->set('search', 'price')
            ->assertSee('price_basic')
            ->assertDontSee('phone_ua_1');
    }

    public function test_geo_filter_limits_rows(): void
    {
        $site = Site::factory()->create();
        $ua = DataValue::factory()->forSite($site)->create(['key' => 'k_ua']);
        $ua->geoTags()->attach(GeoTag::where('code', 'UA')->first());
        DataValue::factory()->forSite($site)->create(['key' => 'k_world']); // WORLD за замовчуванням

        Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->set('geo', 'UA')
            ->assertSee('k_ua')
            ->assertDontSee('k_world');
    }
}
