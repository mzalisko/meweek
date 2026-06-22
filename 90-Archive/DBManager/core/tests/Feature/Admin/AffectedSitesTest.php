<?php

namespace Tests\Feature\Admin;

use App\Admin\AffectedSites;
use App\Models\DataValue;
use App\Models\Site;
use App\Models\SiteGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffectedSitesTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_scope_returns_single_site(): void
    {
        $site = Site::factory()->create();
        $child = Site::factory()->create(['parent_site_id' => $site->id]);
        $value = DataValue::factory()->forSite($site)->create();

        $sites = app(AffectedSites::class)->for($value);

        $this->assertSame([$site->id, $child->id], $sites->pluck('id')->sort()->values()->all());
    }

    public function test_group_scope_returns_all_group_sites(): void
    {
        $group = SiteGroup::factory()->create();
        $a = Site::factory()->for($group, 'group')->create();
        $b = Site::factory()->for($group, 'group')->create();
        $value = DataValue::factory()->forGroup($group)->create();

        $ids = app(AffectedSites::class)->for($value)->pluck('id')->sort()->values()->all();

        $this->assertSame([$a->id, $b->id], $ids);
    }
}
