<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Services\Provisioning\SiteProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\BuildsSlots;
use Tests\TestCase;

class WebhookPublishesToBridgeTest extends TestCase
{
    use RefreshDatabase;
    use BuildsSlots;

    private const SECRET = 'mon-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.monitoring.secret' => self::SECRET,
            'services.bridge.ingest_url' => 'https://bridge.local/api/internal/publish',
            'services.bridge.publish_secret' => 'pub-secret',
        ]);
    }

    public function test_webhook_failover_pushes_affected_site_to_bridge(): void
    {
        Http::fake(['*' => Http::response(['stored_version' => 1], 200)]);

        $site = Site::factory()->create(['domain' => 'domen.ua']);
        app(SiteProvisioner::class)->issueToken($site);
        [$slot, $entries] = $this->slotWithNumbers(['active', 'active']);
        $slot->dataValue->update([
            'key' => 'phone_ua_1', 'scope_type' => 'site', 'scope_id' => $site->id,
        ]);

        $body = json_encode(['e164' => $entries[0]->phoneNumber->e164, 'status' => 'down']);
        $this->call('POST', '/api/monitoring/numbers', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => hash_hmac('sha256', $body, self::SECRET),
            'HTTP_ACCEPT' => 'application/json',
        ], $body)->assertOk();

        Http::assertSent(fn ($request) => $request->url() === 'https://bridge.local/api/internal/publish'
            && json_decode($request->body(), true)['domain'] === 'domen.ua');
    }
}
