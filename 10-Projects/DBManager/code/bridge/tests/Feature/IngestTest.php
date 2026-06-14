<?php

namespace Tests\Feature;

use App\Jobs\DeliverPingJob;
use App\Models\PublishedSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IngestTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'publish-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.publish.secret' => self::SECRET]);
    }

    private function body(array $over = []): array
    {
        return array_merge([
            'domain' => 'domen.ua',
            'token_hash' => hash('sha256', 'raw-token'),
            'ping_url' => 'https://domen.ua/wp-json/dbm/v1/ping',
            'version' => 1,
            'payload' => ['site' => 'domen.ua', 'version' => 1, 'values' => []],
        ], $over);
    }

    private function postSigned(array $body, ?string $secret = null)
    {
        $json = json_encode($body);

        return $this->call('POST', '/api/internal/publish', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => hash_hmac('sha256', $json, $secret ?? self::SECRET),
            'HTTP_ACCEPT' => 'application/json',
        ], $json);
    }

    public function test_invalid_signature_rejected(): void
    {
        Queue::fake();
        $this->postSigned($this->body(), 'wrong')->assertStatus(401);
        $this->assertSame(0, PublishedSite::count());
    }

    public function test_ingest_stores_site_and_dispatches_ping(): void
    {
        Queue::fake();

        $this->postSigned($this->body())->assertOk();

        $site = PublishedSite::where('domain', 'domen.ua')->first();
        $this->assertNotNull($site);
        $this->assertSame(1, $site->version);
        $this->assertSame('domen.ua', $site->payload['site']);
        Queue::assertPushed(DeliverPingJob::class);
    }

    public function test_ingest_is_monotonic_ignores_older_version(): void
    {
        Queue::fake();
        $this->postSigned($this->body(['version' => 5,
            'payload' => ['site' => 'domen.ua', 'version' => 5, 'values' => []]]))->assertOk();

        $this->postSigned($this->body(['version' => 3,
            'payload' => ['site' => 'domen.ua', 'version' => 3, 'values' => []]]))
            ->assertStatus(409);

        $this->assertSame(5, PublishedSite::where('domain', 'domen.ua')->first()->version);
    }

    public function test_ingest_updates_existing_to_newer_version(): void
    {
        Queue::fake();
        $this->postSigned($this->body(['version' => 1]))->assertOk();
        $this->postSigned($this->body(['version' => 2,
            'payload' => ['site' => 'domen.ua', 'version' => 2, 'values' => []]]))->assertOk();

        $this->assertSame(2, PublishedSite::where('domain', 'domen.ua')->first()->version);
        $this->assertSame(1, PublishedSite::where('domain', 'domen.ua')->count());
    }

    public function test_ingest_rejects_equal_version_and_keeps_payload(): void
    {
        Queue::fake();
        $this->postSigned($this->body(['version' => 5,
            'payload' => ['site' => 'domen.ua', 'version' => 5, 'values' => []]]))->assertOk();

        // Рівна версія — межа `<=`: 409, payload не перезаписується.
        $this->postSigned($this->body(['version' => 5,
            'payload' => ['site' => 'domen.ua', 'version' => 5, 'values' => [['key' => 'мутація']]]]))
            ->assertStatus(409);

        $this->assertSame([], PublishedSite::where('domain', 'domen.ua')->first()->payload['values']);
    }
}
