<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\SiteGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_belongs_to_group(): void
    {
        $group = SiteGroup::factory()->create(['name' => 'Brand A']);
        $site = Site::factory()->for($group, 'group')->create(['domain' => 'domen.ua']);

        $this->assertSame('Brand A', $site->group->name);
        $this->assertTrue($group->sites->contains($site));
    }

    public function test_site_can_exist_without_group(): void
    {
        $site = Site::factory()->create(['site_group_id' => null]);

        $this->assertNull($site->group);
    }
}
