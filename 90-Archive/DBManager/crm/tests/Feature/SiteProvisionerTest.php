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
        $this->assertNotEmpty($token->push_secret);
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

    public function test_issue_plugin_connection_returns_listener_key_without_central_url(): void
    {
        $site = Site::factory()->create(['domain' => 'example.test']);

        $connection = app(SiteProvisioner::class)->issuePluginConnection($site);

        $this->assertStringStartsWith('DBM1.', $connection['connection_key']);
        $this->assertSame('http://example.test/?rest_route=/dbm/v1/ping', $connection['ping_url']);
        $this->assertSame($connection['ping_url'], $site->fresh()->ping_url);

        $encoded = substr($connection['connection_key'], 5);
        $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
        $payload = json_decode(base64_decode(strtr($encoded, '-_', '+/'), true), true);

        $this->assertSame(1, $payload['v']);
        $this->assertSame('listener', $payload['mode']);
        $this->assertSame($site->id, $payload['site_id']);
        $this->assertSame($connection['ping_url'], $payload['ping_url']);
        $this->assertSame(app(SiteProvisioner::class)->activePushSecret($site), $payload['signing_secret']);
        $this->assertArrayNotHasKey('bridge_url', $payload);
        $this->assertArrayNotHasKey('site_token', $payload);
    }

    public function test_issue_plugin_connection_normalizes_wp_json_ping_url(): void
    {
        $site = Site::factory()->create([
            'domain' => 'domen.ua',
            'ping_url' => 'https://domen.ua/wp-json/dbm/v1/ping',
        ]);

        $connection = app(SiteProvisioner::class)->issuePluginConnection($site);

        $this->assertSame('https://domen.ua/?rest_route=/dbm/v1/ping', $connection['ping_url']);
        $this->assertSame($connection['ping_url'], $site->fresh()->ping_url);
    }

    public function test_revoke_token_marks_all_active_tokens_revoked(): void
    {
        $site = Site::factory()->create();
        $provisioner = app(SiteProvisioner::class);
        $provisioner->issueToken($site);
        $provisioner->issueToken($site);

        $count = $provisioner->revokeToken($site);

        $this->assertSame(2, $count);
        $this->assertNull($provisioner->activeTokenHash($site));
        $this->assertSame(0, ApiToken::where('site_id', $site->id)->whereNull('revoked_at')->count());
    }
}
