<?php

namespace Tests\Feature\Admin;

use App\Livewire\SitesManager;
use App\Models\AuditLog;
use App\Models\DataValue;
use App\Models\NumberEntry;
use App\Models\PhoneSlot;
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

    public function test_create_site_persists_and_audits(): void
    {
        $this->actingAs(User::factory()->create());
        $group = SiteGroup::factory()->create();

        Livewire::test(SitesManager::class)
            ->call('startCreateSite', $group->id)
            ->assertSet('siteGroupId', $group->id)
            ->set('siteName', 'Новий сайт')
            ->set('siteDomain', 'new.test')
            ->set('siteCountryHint', 'UA')
            ->call('saveSite')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sites', [
            'name' => 'Новий сайт',
            'domain' => 'new.test',
            'country_hint' => 'UA',
            'site_group_id' => $group->id,
        ]);
        $this->assertTrue(AuditLog::where('action', 'site.created')->exists());
    }

    public function test_create_child_site_persists_parent_site(): void
    {
        $this->actingAs(User::factory()->create());
        $group = SiteGroup::factory()->create();
        $parent = Site::factory()->for($group, 'group')->create(['domain' => 'main.test']);

        Livewire::test(SitesManager::class)
            ->call('startCreateSite', $group->id)
            ->set('siteName', 'Сателіт')
            ->set('siteDomain', 'child.test')
            ->set('parentSiteId', $parent->id)
            ->call('saveSite')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sites', [
            'domain' => 'child.test',
            'parent_site_id' => $parent->id,
            'site_group_id' => $group->id,
        ]);
    }

    public function test_edit_site_persists_and_audits(): void
    {
        $this->actingAs(User::factory()->create());
        $site = Site::factory()->create(['domain' => 'old.test', 'name' => 'Старий']);

        Livewire::test(SitesManager::class)
            ->call('editSite', $site->id)
            ->assertSet('siteDomain', 'old.test')
            ->set('siteName', 'Оновлений')
            ->call('saveSite')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sites', ['id' => $site->id, 'name' => 'Оновлений']);
        $this->assertTrue(AuditLog::where('action', 'site.updated')->exists());
    }

    public function test_site_domain_must_be_unique(): void
    {
        $this->actingAs(User::factory()->create());
        Site::factory()->create(['domain' => 'taken.test']);

        Livewire::test(SitesManager::class)
            ->call('startCreateSite')
            ->set('siteName', 'Дубль')
            ->set('siteDomain', 'taken.test')
            ->call('saveSite')
            ->assertHasErrors('siteDomain');

        $this->assertDatabaseMissing('sites', ['name' => 'Дубль']);
    }

    public function test_archive_and_restore_site_audits(): void
    {
        $this->actingAs(User::factory()->create());
        $site = Site::factory()->create();

        Livewire::test(SitesManager::class)->call('archiveSite', $site->id);
        $this->assertSoftDeleted('sites', ['id' => $site->id]);
        $this->assertTrue(AuditLog::where('action', 'site.archived')->exists());

        Livewire::test(SitesManager::class)->call('restoreSite', $site->id);
        $this->assertDatabaseHas('sites', ['id' => $site->id, 'deleted_at' => null]);
        $this->assertTrue(AuditLog::where('action', 'site.restored')->exists());
    }

    public function test_archived_site_can_be_purged_with_exact_domain_confirmation(): void
    {
        $this->actingAs(User::factory()->create());
        $site = Site::factory()->create(['domain' => 'purge.test']);

        Livewire::test(SitesManager::class)->call('archiveSite', $site->id);

        Livewire::test(SitesManager::class)
            ->call('confirmPurgeSite', $site->id)
            ->set('purgingSiteConfirmation', 'purge.test')
            ->call('purgeSite')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
        $this->assertTrue(AuditLog::where('action', 'site.purged')->exists());
    }

    public function test_site_row_links_to_value_management(): void
    {
        $this->actingAs(User::factory()->create());
        $site = Site::factory()->create(['domain' => 'manage.test']);

        Livewire::test(SitesManager::class)
            ->assertSeeHtml('href="'.route('admin.site', ['site' => $site->id]).'"');
    }

    public function test_clone_parent_data(): void
    {
        $this->actingAs(User::factory()->create());

        $parent = Site::factory()->create(['domain' => 'parent.test']);
        $child = Site::factory()->create(['domain' => 'sat.test', 'parent_site_id' => $parent->id]);

        $parentValue = DataValue::create([
            'key' => 'phone_main',
            'value_type_id' => \App\Models\ValueType::firstOrCreate(['code' => 'phone'], ['name' => 'Phone'])->id,
            'scope_type' => 'site',
            'scope_id' => $parent->id,
            'content' => null,
            'status' => 'active',
        ]);
        $geo = \App\Models\GeoTag::firstOrCreate(['code' => 'WORLD'], ['name' => 'World']);
        $parentValue->geoTags()->sync([$geo->id]);

        $slot = PhoneSlot::create([
            'data_value_id' => $parentValue->id,
            'return_mode' => 'auto',
            'exhaustion_policy' => 'hide',
        ]);

        $phone = \App\Models\PhoneNumber::create(['e164' => '+380441112233', 'label' => 'Main', 'status' => 'active']);
        NumberEntry::create(['phone_slot_id' => $slot->id, 'phone_number_id' => $phone->id, 'priority' => 0]);

        Livewire::test(SitesManager::class)
            ->set('editingSiteId', $child->id)
            ->set('parentSiteId', $parent->id)
            ->assertSet('editingSiteId', $child->id)
            ->call('cloneParentData')
            ->assertHasNoErrors();

        $childValue = DataValue::where('scope_type', 'site')->where('scope_id', $child->id)->where('key', 'phone_main')->first();
        $this->assertNotNull($childValue);
        $this->assertSame('phone_main', $childValue->key);
        $this->assertCount(1, $childValue->geoTags);
        $this->assertNotNull($childValue->phoneSlot);
        $this->assertCount(1, $childValue->phoneSlot->entries);
        $this->assertSame('+380441112233', $childValue->phoneSlot->entries->first()->phoneNumber->e164);
    }

    public function test_searching_group_name_reveals_its_sites_and_satellites(): void
    {
        $this->actingAs(User::factory()->create());

        $group = SiteGroup::factory()->create(['name' => 'Brand A']);
        $parent = Site::factory()->for($group, 'group')->create(['domain' => 'parent.test']);
        Site::factory()->for($group, 'group')->create(['domain' => 'sat.test', 'parent_site_id' => $parent->id]);

        Livewire::test(SitesManager::class)
            ->set('siteSearch', 'Bran')
            ->assertSee('Brand A')
            ->assertSee('parent.test')
            ->assertSee('sat.test')
            ->assertSee('2 сайт(ів)')
            ->assertDontSee('Немає сайтів у групі');
    }

    public function test_groups_render_collapsible_accordion_scaffold(): void
    {
        $this->actingAs(User::factory()->create());

        $group = SiteGroup::factory()->create(['name' => 'Бренд']);
        Site::factory()->for($group, 'group')->create(['domain' => 'live.test']);

        Livewire::test(SitesManager::class)
            ->assertSeeHtml('sectionKeys') // обгортка-акордеон
            ->assertSeeHtml("@click=\"toggle('group-{$group->id}')\"")   // кнопка-перемикач
            ->assertSeeHtml("x-show=\"isOpen('group-{$group->id}')")             // тіло групи згортається
            ->assertSee('live.test');                   // сайт лишається в DOM
    }

    public function test_toggle_favorites_for_groups_and_sites(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $group = SiteGroup::factory()->create(['name' => 'Улюблена група']);
        $site = Site::factory()->create(['domain' => 'fav.test']);

        // Спочатку зірочки пусті (немає в улюблених)
        Livewire::test(SitesManager::class)
            ->assertDontSee('★')
            ->assertSee('☆');

        // Додаємо групу до улюблених
        Livewire::test(SitesManager::class)
            ->call('toggleFavorite', 'group', $group->id)
            ->assertDispatched('toast', message: 'Додано до улюблених');

        $this->assertDatabaseHas('user_favorites', [
            'user_id' => $user->id,
            'favorable_type' => SiteGroup::class,
            'favorable_id' => $group->id,
        ]);

        // Додаємо сайт до улюблених
        Livewire::test(SitesManager::class)
            ->call('toggleFavorite', 'site', $site->id)
            ->assertDispatched('toast', message: 'Додано до улюблених');

        $this->assertDatabaseHas('user_favorites', [
            'user_id' => $user->id,
            'favorable_type' => Site::class,
            'favorable_id' => $site->id,
        ]);

        // Перевіряємо відображення зафарбованих зірочок
        Livewire::test(SitesManager::class)
            ->assertSee('★');

        // Видаляємо з улюблених
        Livewire::test(SitesManager::class)
            ->call('toggleFavorite', 'group', $group->id)
            ->assertDispatched('toast', message: 'Вилучено з улюблених');

        $this->assertDatabaseMissing('user_favorites', [
            'user_id' => $user->id,
            'favorable_type' => SiteGroup::class,
            'favorable_id' => $group->id,
        ]);
    }
}
