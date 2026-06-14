<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Services\Provisioning\SiteProvisioner;
use App\Services\Publishing\BridgePublisher;
use App\Services\Publishing\SitePayloadCompiler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BridgePublisherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.bridge.ingest_url' => 'https://bridge.local/api/internal/publish',
            'services.bridge.publish_secret' => 'pub-secret',
        ]);
    }

    public function test_push_sends_signed_payload_with_token_and_ping_url(): void
    {
        Http::fake(['*' => Http::response(['stored_version' => 3], 200)]);

        $site = Site::factory()->create(['domain' => 'domen.ua', 'ping_url' => 'https://domen.ua/ping']);
        $raw = app(SiteProvisioner::class)->issueToken($site);
        $publication = app(SitePayloadCompiler::class)->publish($site);

        $ok = app(BridgePublisher::class)->push($publication);

        $this->assertTrue($ok);
        Http::assertSent(function ($request) use ($site, $raw, $publication) {
            $body = $request->body();
            $expectedSig = hash_hmac('sha256', $body, 'pub-secret');
            $data = json_decode($body, true);

            return $request->url() === 'https://bridge.local/api/internal/publish'
                && $request->hasHeader('X-Signature', $expectedSig)
                && $data['domain'] === 'domen.ua'
                && $data['token_hash'] === hash('sha256', $raw)
                && $data['ping_url'] === 'https://domen.ua/ping'
                && $data['version'] === $publication->version
                && $data['payload']['site'] === 'domen.ua';
        });
    }

    public function test_push_returns_false_when_site_has_no_token(): void
    {
        Http::fake();
        $site = Site::factory()->create();
        $publication = app(SitePayloadCompiler::class)->publish($site);

        $ok = app(BridgePublisher::class)->push($publication);

        $this->assertFalse($ok);
        Http::assertNothingSent();
    }

    public function test_push_returns_false_on_bridge_error(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $site = Site::factory()->create();
        app(SiteProvisioner::class)->issueToken($site);
        $publication = app(SitePayloadCompiler::class)->publish($site);

        $ok = app(BridgePublisher::class)->push($publication);

        $this->assertFalse($ok);
    }
}
