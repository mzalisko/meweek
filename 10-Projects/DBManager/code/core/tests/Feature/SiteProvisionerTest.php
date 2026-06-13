<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Site;
use App\Services\Provisioning\SiteProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteProvisionerTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_token_returns_raw_and_stores_hash(): void
    {
        $site = Site::factory()->create();

        $raw = app(SiteProvisioner::class)->issueToken($site, 'основний');

        $this->assertNotEmpty($raw);
        $token = ApiToken::where('site_id', $site->id)->first();
        $this->assertSame(hash('sha256', $raw), $token->token_hash);
        $this->assertSame('основний', $token->label);
        $this->assertNull($token->revoked_at);
    }

    public function test_active_token_hash_returns_latest_non_revoked(): void
    {
        $site = Site::factory()->create();
        $provisioner = app(SiteProvisioner::class);

        $first = $provisioner->issueToken($site);
        ApiToken::where('site_id', $site->id)->update(['revoked_at' => now()]);
        $second = $provisioner->issueToken($site);

        $this->assertSame(hash('sha256', $second), $provisioner->activeTokenHash($site));
    }

    public function test_active_token_hash_null_when_none(): void
    {
        $site = Site::factory()->create();

        $this->assertNull(app(SiteProvisioner::class)->activeTokenHash($site));
    }
}
