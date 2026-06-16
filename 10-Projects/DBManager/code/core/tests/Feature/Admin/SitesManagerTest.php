<?php

namespace Tests\Feature\Admin;

use App\Livewire\SitesManager;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SitesManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_screen_lists_active_groups_and_sites_hides_archived(): void
    {
        $this->actingAs(User::factory()->create());

        $group = SiteGroup::factory()->create(['name' => 'Бренд']);
        Site::factory()->for($group, 'group')->create(['domain' => 'live.test']);
        $archived = Site::factory()->for($group, 'group')->create(['domain' => 'old.test']);
        $archived->delete(); // soft

        Livewire::test(SitesManager::class)
            ->assertSee('Бренд')
            ->assertSee('live.test')
            ->assertDontSee('old.test');
    }
}
