<?php

namespace Tests\Feature\Admin;

use App\Livewire\AuditManager;
use App\Models\AuditLog;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSiteAccess;
use App\Services\Failover\FailoverEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class AuditManagerTabsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSlots;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_failover_tab_shows_site_and_switch_details(): void
    {
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);
        $slot->dataValue->update(['key' => 'phone_main']);
        Site::find($slot->dataValue->scope_id)->update(['domain' => 'failover.test']);

        app(FailoverEngine::class)->markNumberDown($entries[0]->phoneNumber, 'webhook');

        $log = AuditLog::where('action', 'failover.switch')
            ->orderByDesc('id')
            ->first();

        $this->assertSame('down', $log->new['trigger_status']);
        $this->assertSame('failover.test', $log->new['sites'][0]['domain']);
        $this->assertSame($entries[0]->phoneNumber->e164, $log->new['trigger_number']);
        $this->assertSame($entries[1]->phoneNumber->e164, $log->new['number']);

        Livewire::test(AuditManager::class)
            ->set('activeTab', 'failover')
            ->assertSee('Failover аудит')
            ->assertSee('failover.test')
            ->assertSee('phone_main')
            ->assertSee($entries[0]->phoneNumber->e164)
            ->assertSee($entries[1]->phoneNumber->e164);
    }

    public function test_failover_events_are_not_rendered_in_system_tab(): void
    {
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);
        Site::find($slot->dataValue->scope_id)->update(['domain' => 'hidden-from-system.test']);

        app(FailoverEngine::class)->markNumberDown($entries[0]->phoneNumber, 'webhook');

        Livewire::test(AuditManager::class)
            ->set('activeTab', 'systems')
            ->assertSee('Системні логи')
            ->assertDontSee('hidden-from-system.test')
            ->assertDontSee($entries[1]->phoneNumber->e164);
    }

    public function test_user_events_have_dedicated_tab(): void
    {
        AuditLog::create([
            'actor_type' => 'user',
            'actor_id' => auth()->id(),
            'action' => 'user.login',
            'subject_type' => 'User',
            'subject_id' => auth()->id(),
            'new' => ['ip' => '127.0.0.10'],
        ]);

        Livewire::test(AuditManager::class)
            ->set('activeTab', 'users')
            ->assertSee('Користувачі і доступи')
            ->assertSee('Вхід у систему')
            ->assertSee('127.0.0.10');

        Livewire::test(AuditManager::class)
            ->set('activeTab', 'systems')
            ->assertDontSee('127.0.0.10');
    }

    public function test_failover_tab_is_limited_to_sites_with_failover_log_permission(): void
    {
        $allowed = Site::factory()->create(['domain' => 'allowed.test']);
        $blocked = Site::factory()->create(['domain' => 'blocked.test']);

        [$allowedSlot, $allowedEntries] = $this->slotWithNumbers(['active', 'active']);
        $allowedSlot->dataValue->update([
            'key' => 'allowed_phone',
            'scope_type' => 'site',
            'scope_id' => $allowed->id,
        ]);

        [$blockedSlot, $blockedEntries] = $this->slotWithNumbers(['active', 'active']);
        $blockedSlot->dataValue->update([
            'key' => 'blocked_phone',
            'scope_type' => 'site',
            'scope_id' => $blocked->id,
        ]);

        app(FailoverEngine::class)->markNumberDown($allowedEntries[0]->phoneNumber, 'webhook');
        app(FailoverEngine::class)->markNumberDown($blockedEntries[0]->phoneNumber, 'webhook');

        $manager = User::factory()->manager()->create();
        UserSiteAccess::create([
            'user_id' => $manager->id,
            'site_id' => $allowed->id,
            'can_view' => true,
            'can_edit' => false,
            'can_delete' => false,
            'can_publish' => false,
            'can_view_history' => false,
            'can_view_failover' => true,
        ]);

        $this->actingAs($manager);

        Livewire::test(AuditManager::class)
            ->set('activeTab', 'failover')
            ->assertSee('allowed.test')
            ->assertSee('allowed_phone')
            ->assertDontSee('blocked.test')
            ->assertDontSee('blocked_phone');
    }
}
