<?php

namespace Tests\Feature\Admin;

use App\Livewire\SitesManager;
use App\Models\AuditLog;
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

    public function test_create_group_persists_and_audits(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(SitesManager::class)
            ->call('startCreateGroup')
            ->set('groupName', 'Нова група')
            ->call('saveGroup')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('site_groups', ['name' => 'Нова група']);
        $this->assertTrue(AuditLog::where('action', 'group.created')->exists());
    }

    public function test_rename_group_persists_and_audits(): void
    {
        $this->actingAs(User::factory()->create());
        $group = SiteGroup::factory()->create(['name' => 'Старе ім’я']);

        Livewire::test(SitesManager::class)
            ->call('editGroup', $group->id)
            ->assertSet('groupName', 'Старе ім’я')
            ->set('groupName', 'Нове ім’я')
            ->call('saveGroup')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('site_groups', ['id' => $group->id, 'name' => 'Нове ім’я']);
        $this->assertTrue(AuditLog::where('action', 'group.updated')->exists());
    }

    public function test_archive_group_cascades_to_sites_and_restore_brings_back(): void
    {
        $this->actingAs(User::factory()->create());

        $group = SiteGroup::factory()->create();
        $site = Site::factory()->for($group, 'group')->create();

        Livewire::test(SitesManager::class)->call('archiveGroup', $group->id);

        $this->assertSoftDeleted('site_groups', ['id' => $group->id]);
        $this->assertSoftDeleted('sites', ['id' => $site->id]);
        $this->assertTrue(AuditLog::where('action', 'group.archived')->exists());
        $this->assertTrue(AuditLog::where('action', 'site.archived')->exists());

        Livewire::test(SitesManager::class)->call('restoreGroup', $group->id);

        $this->assertDatabaseHas('site_groups', ['id' => $group->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('sites', ['id' => $site->id, 'deleted_at' => null]);
        $this->assertTrue(AuditLog::where('action', 'group.restored')->exists());
    }
}
