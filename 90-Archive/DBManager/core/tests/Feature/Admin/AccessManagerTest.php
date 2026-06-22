<?php

namespace Tests\Feature\Admin;

use App\Livewire\AccessManager;
use App\Livewire\ValuesGrid;
use App\Models\Site;
use App\Models\SiteGroup;
use App\Models\User;
use App\Models\UserSiteAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AccessManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_creates_user_with_site_permissions_and_visible_temporary_password(): void
    {
        $admin = User::factory()->create();
        $site = Site::factory()->create();

        $this->actingAs($admin);

        $component = Livewire::test(AccessManager::class)
            ->call('startCreate');

        $generatedPassword = $component->get('password');
        $this->assertNotEmpty($generatedPassword);

        $component
            ->set('name', 'Менеджер RO')
            ->set('email', 'manager@example.test')
            ->set('role', 'manager')
            ->set("sitePermissions.{$site->id}.can_view", true)
            ->set("sitePermissions.{$site->id}.can_edit", true)
            ->set("sitePermissions.{$site->id}.can_delete", true)
            ->set("sitePermissions.{$site->id}.can_publish", true)
            ->call('saveUser');

        $user = User::where('email', 'manager@example.test')->sole();
        $plainPassword = $component->get('visiblePassword');

        $this->assertSame('manager', $user->role);
        $this->assertSame($generatedPassword, $plainPassword);
        $this->assertTrue(Hash::check($plainPassword, $user->password));
        $this->assertDatabaseHas('user_site_access', [
            'user_id' => $user->id,
            'site_id' => $site->id,
            'can_view' => true,
            'can_edit' => true,
            'can_delete' => true,
            'can_publish' => true,
        ]);
    }

    public function test_superadmin_can_override_generated_password_before_creating_user(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin);

        $component = Livewire::test(AccessManager::class)
            ->call('startCreate')
            ->set('name', 'Власний пароль')
            ->set('email', 'custom-password@example.test')
            ->set('password', 'manual-secret')
            ->call('saveUser');

        $user = User::where('email', 'custom-password@example.test')->sole();

        $this->assertSame('manual-secret', $component->get('visiblePassword'));
        $this->assertTrue(Hash::check('manual-secret', $user->password));
    }

    public function test_user_table_and_side_panel_render_after_actions(): void
    {
        $admin = User::factory()->create();
        $viewer = User::factory()->viewer()->create();
        Site::factory()->create();

        $this->actingAs($admin);

        Livewire::test(AccessManager::class)
            ->assertSeeHtml('w-full min-w-full table-fixed')
            ->call('startCreate')
            ->assertSet('panelOpen', true)
            ->assertSeeHtml('fixed right-0 top-0 bottom-0')
            ->assertSeeHtml('can_delete')
            ->call('closePanel')
            ->call('selectUser', $viewer->id)
            ->assertSet('panelOpen', true)
            ->assertSeeHtml('fixed right-0 top-0 bottom-0');
    }

    public function test_superadmin_resets_password_and_revokes_sessions(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->viewer()->create(['remember_token' => 'old-token']);

        DB::table('sessions')->insert([
            'id' => 'session-to-kill',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Feature test',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(AccessManager::class)
            ->call('resetPassword', $user->id);

        $plainPassword = $component->get('visiblePassword');

        $this->assertNotEmpty($plainPassword);
        $this->assertTrue(Hash::check($plainPassword, $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'session-to-kill']);
        $this->assertNotSame('old-token', $user->fresh()->remember_token);
    }

    public function test_superadmin_logout_revokes_sessions_and_rotates_remember_token(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->viewer()->create(['remember_token' => 'old-token']);

        DB::table('sessions')->insert([
            'id' => 'session-to-revoke',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Feature test',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin);

        Livewire::test(AccessManager::class)
            ->call('logoutUser', $user->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'session-to-revoke']);
        $this->assertNotSame('old-token', $user->fresh()->remember_token);
    }

    public function test_group_preset_creates_single_group_rule_for_many_sites(): void
    {
        $admin = User::factory()->create();
        $group = SiteGroup::factory()->create();
        Site::factory()->count(3)->for($group, 'group')->create();

        $this->actingAs($admin);

        Livewire::test(AccessManager::class)
            ->call('startCreate')
            ->set('name', 'Груповий менеджер')
            ->set('email', 'group-manager@example.test')
            ->set('role', 'manager')
            ->call('applyGroupPreset', $group->id, 'publish')
            ->call('saveUser');

        $user = User::where('email', 'group-manager@example.test')->sole();

        $this->assertDatabaseHas('user_site_group_access', [
            'user_id' => $user->id,
            'site_group_id' => $group->id,
            'can_view' => true,
            'can_edit' => true,
            'can_delete' => true,
            'can_publish' => true,
        ]);
        $this->assertDatabaseCount('user_site_access', 0);
    }

    public function test_permission_matrix_has_no_group_preset_buttons(): void
    {
        $admin = User::factory()->create();
        $group = SiteGroup::factory()->create(['name' => 'Бренд']);
        Site::factory()->for($group, 'group')->create(['domain' => 'live.test']);

        $this->actingAs($admin);

        Livewire::test(AccessManager::class)
            ->call('startCreate')
            ->set('role', 'manager')
            ->assertDontSeeHtml('applyGroupPreset') // групові пресети прибрано
            ->assertSee('live.test')                // матриця сайтів лишилась
            ->assertSeeHtml('sitePermissions');     // per-site чекбокси лишились
    }

    public function test_log_access_matrix_is_persisted(): void
    {
        $admin = User::factory()->create();
        $site = Site::factory()->create(['domain' => 'logs.test']);

        $this->actingAs($admin);

        Livewire::test(AccessManager::class)
            ->call('startCreate')
            ->set('name', 'Log Manager')
            ->set('email', 'logs@example.test')
            ->set('role', 'manager')
            ->set('password', 'secret123')
            ->set('canViewUserLogs', true)
            ->set('canViewSystemLogs', true)
            ->set("sitePermissions.{$site->id}.can_view_failover", true)
            ->call('saveUser')
            ->assertHasNoErrors();

        $user = User::where('email', 'logs@example.test')->firstOrFail();

        $this->assertTrue((bool) $user->can_view_user_logs);
        $this->assertTrue((bool) $user->can_view_system_logs);
        $this->assertDatabaseHas('user_site_access', [
            'user_id' => $user->id,
            'site_id' => $site->id,
            'can_view' => true,
            'can_view_failover' => true,
        ]);
    }

    public function test_superadmin_deletes_user_and_revokes_sessions(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->viewer()->create();

        DB::table('sessions')->insert([
            'id' => 'deleted-user-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Feature test',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin);

        Livewire::test(AccessManager::class)
            ->call('deleteUser', $user->id)
            ->assertSet('panelOpen', false);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'deleted-user-session']);
    }

    public function test_superadmin_cannot_demote_last_active_superadmin(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $this->actingAs($admin);

        Livewire::test(AccessManager::class)
            ->call('toggleUserActive', User::where('email', 'admin@dbmanager.local')->value('id'))
            ->assertSet('panelOpen', false);

        Livewire::test(AccessManager::class)
            ->call('selectUser', $admin->id)
            ->set('role', 'viewer')
            ->call('saveUser')
            ->assertHasErrors(['selectedUserId']);

        $this->assertSame('superadmin', $admin->fresh()->role);
    }

    public function test_manager_cannot_open_access_manager(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get(route('admin.access'))
            ->assertForbidden();
    }

    public function test_values_grid_starts_on_first_accessible_site_for_manager(): void
    {
        $blocked = Site::factory()->create();
        $allowed = Site::factory()->create();
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

        Livewire::test(ValuesGrid::class, ['site' => $blocked->id])
            ->assertSet('site', $allowed->id);
    }
}
