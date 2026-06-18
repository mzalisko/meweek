<?php

namespace Tests\Feature\Admin;

use App\Livewire\ValuesGrid;
use App\Models\DataValue;
use App\Models\GeoTag;
use App\Models\Site;
use App\Models\SiteGroup;
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

    public function test_site_is_not_auto_selected_when_opening_without_parameter(): void
    {
        Site::factory()->create(['domain' => 'first.test']);
        Site::factory()->create(['domain' => 'second.test']);

        Livewire::test(ValuesGrid::class)
            ->assertSee('Оберіть сайт')
            ->assertDontSee('first.test')
            ->assertDontSee('second.test');
    }

    public function test_site_is_loaded_from_query_string(): void
    {
        $site = Site::factory()->create(['domain' => 'ua.test']);
        DataValue::factory()->forSite($site)->create(['key' => 'ua_phone']);

        Livewire::withQueryParams(['site' => $site->id])
            ->test(ValuesGrid::class)
            ->assertSee('ua_phone')
            ->assertDontSee('Оберіть сайт');
    }

    public function test_group_query_populates_site_switcher_without_selecting_a_site(): void
    {
        $group = SiteGroup::factory()->create(['name' => 'Brand A']);
        $otherGroup = SiteGroup::factory()->create(['name' => 'Brand B']);
        Site::factory()->create(['site_group_id' => $group->id, 'domain' => 'domen.ua']);
        Site::factory()->create(['site_group_id' => $group->id, 'domain' => 'domen.ro']);
        Site::factory()->create(['site_group_id' => $otherGroup->id, 'domain' => 'other.test']);

        $this->get(route('admin.site', ['group' => $group->id]))
            ->assertOk()
            ->assertSee('Brand A')
            ->assertSee('domen.ua')
            ->assertSee('domen.ro')
            ->assertSee('data-site-select', false)
            ->assertSee('data-group-id="'.$group->id.'"', false)
            ->assertSee('Оберіть сайт');
    }

    public function test_group_and_site_query_loads_selected_site_values(): void
    {
        $group = SiteGroup::factory()->create(['name' => 'Brand A']);
        $site = Site::factory()->create(['site_group_id' => $group->id, 'domain' => 'domen.ua']);
        DataValue::factory()->forSite($site)->create(['key' => 'ua_phone']);

        $this->get(route('admin.site', ['group' => $group->id, 'site' => $site->id]))
            ->assertOk()
            ->assertSee('Brand A')
            ->assertSee('domen.ua')
            ->assertSee('ua_phone')
            ->assertDontSee('Стартовий екран сайту');
    }

    public function test_livewire_switching_group_and_site_keeps_site_data(): void
    {
        $group = SiteGroup::factory()->create(['name' => 'Brand A']);
        $site = Site::factory()->create(['site_group_id' => $group->id, 'domain' => 'domen.ua']);
        DataValue::factory()->forSite($site)->create(['key' => 'ua_phone']);

        Livewire::test(ValuesGrid::class)
            ->set('group', $group->id)
            ->assertSet('group', $group->id)
            ->assertSet('site', null)
            ->set('site', $site->id)
            ->assertSet('group', $group->id)
            ->assertSet('site', $site->id)
            ->assertSee('ua_phone');
    }

    public function test_grid_refreshes_when_value_editor_saves_value(): void
    {
        $site = Site::factory()->create(['domain' => 'ua.test']);

        $component = Livewire::test(ValuesGrid::class, ['site' => $site->id])
            ->assertDontSee('new_value');

        DataValue::factory()->forSite($site)->create(['key' => 'new_value']);

        $component
            ->dispatch('value-saved')
            ->assertSee('new_value');
    }
}
