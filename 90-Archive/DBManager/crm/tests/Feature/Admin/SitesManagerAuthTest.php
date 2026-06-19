<?php

namespace Tests\Feature\Admin;

use App\Models\Site;
use App\Models\SiteGroup;
use App\Models\User;
use App\Models\UserSiteAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitesManagerAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_open_sites_screen(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('admin.sites'))->assertOk();
    }

    public function test_manager_with_single_site_access_sees_its_group_without_sibling_sites(): void
    {
        $group = SiteGroup::factory()->create(['name' => 'Brand A']);
        $allowed = Site::factory()->for($group, 'group')->create(['domain' => 'allowed.test']);
        Site::factory()->for($group, 'group')->create(['domain' => 'hidden.test']);
        Site::factory()->create(['domain' => 'other.test']);

        $manager = User::factory()->manager()->create();
        UserSiteAccess::create([
            'user_id' => $manager->id,
            'site_id' => $allowed->id,
            'can_view' => true,
            'can_edit' => false,
            'can_delete' => false,
            'can_publish' => false,
        ]);

        $this->actingAs($manager);

        $this->get(route('admin.sites'))
            ->assertOk()
            ->assertSee('Brand A')
            ->assertSee('allowed.test')
            ->assertDontSee('hidden.test')
            ->assertDontSee('other.test')
            ->assertDontSee('Створити сайт')
            ->assertDontSee('Створити групу');
    }
}
