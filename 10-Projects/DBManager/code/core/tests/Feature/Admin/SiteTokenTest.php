<?php

namespace Tests\Feature\Admin;

use App\Livewire\SitesManager;
use App\Models\ApiToken;
use App\Models\AuditLog;
use App\Models\Publication;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_token_reveals_raw_once_and_audits(): void
    {
        $this->actingAs(User::factory()->create());
        $site = Site::factory()->create();

        Livewire::test(SitesManager::class)
            ->call('editSite', $site->id)
            ->call('issueToken')
            ->assertSet('visibleToken', fn ($value) => is_string($value) && str_starts_with($value, 'DBM1.'));

        $this->assertSame(1, ApiToken::where('site_id', $site->id)->whereNull('revoked_at')->count());
        $this->assertTrue(
            AuditLog::where('action', 'token.issued')->where('subject_id', $site->id)->exists()
        );
    }

    public function test_revoke_token_clears_visible_and_audits(): void
    {
        $this->actingAs(User::factory()->create());
        $site = Site::factory()->create();
        ApiToken::factory()->for($site)->create();

        Livewire::test(SitesManager::class)
            ->call('editSite', $site->id)
            ->call('issueToken')
            ->call('revokeToken')
            ->assertSet('visibleToken', null);

        $this->assertSame(0, ApiToken::where('site_id', $site->id)->whereNull('revoked_at')->count());
        $this->assertTrue(
            AuditLog::where('action', 'token.revoked')->where('subject_id', $site->id)->exists()
        );
    }

    public function test_rotate_token_revokes_old_and_issues_new(): void
    {
        $this->actingAs(User::factory()->create());
        $site = Site::factory()->create();
        $old = ApiToken::factory()->for($site)->create();

        Livewire::test(SitesManager::class)
            ->call('editSite', $site->id)
            ->call('rotateToken')
            ->assertSet('visibleToken', fn ($value) => is_string($value) && str_starts_with($value, 'DBM1.'));

        $this->assertNotNull($old->fresh()->revoked_at);
        $this->assertSame(1, ApiToken::where('site_id', $site->id)->whereNull('revoked_at')->count());
        $this->assertTrue(
            AuditLog::where('action', 'token.rotated')->where('subject_id', $site->id)->exists()
        );
    }

    public function test_close_panel_clears_visible_token(): void
    {
        $this->actingAs(User::factory()->create());
        $site = Site::factory()->create();

        Livewire::test(SitesManager::class)
            ->call('editSite', $site->id)
            ->call('issueToken')
            ->call('closePanel')
            ->assertSet('visibleToken', null);
    }

    public function test_panel_shows_connection_status_with_version(): void
    {
        $this->actingAs(User::factory()->create());
        $site = Site::factory()->create();
        ApiToken::factory()->for($site)->create(['last_seen_at' => now()->subMinutes(5)]);
        Publication::create(['site_id' => $site->id, 'version' => 7, 'payload' => ['x' => 1]]);

        Livewire::test(SitesManager::class)
            ->call('editSite', $site->id)
            ->assertSee('Версія 7');
    }
}
