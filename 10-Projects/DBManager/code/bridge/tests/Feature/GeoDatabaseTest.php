<?php

namespace Tests\Feature;

use App\Models\PublishedSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeoDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private const PUSH = 'push-secret-at-least-thirty-two-chars-xx';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.publish.secret' => 'pub',
            'services.data.signing_secret' => 'sign',
        ]);
    }

    private function ingest(string $bytes, ?string $secret = null)
    {
        return $this->call('POST', '/api/internal/geodb', [], [], [], [
            'CONTENT_TYPE' => 'application/octet-stream',
            'HTTP_X_SIGNATURE' => hash_hmac('sha256', $bytes, $secret ?? 'pub'),
            'HTTP_X_GEODB_SHA256' => hash('sha256', $bytes),
        ], $bytes);
    }

    public function test_ingest_rejects_bad_signature(): void
    {
        $this->ingest('AABB', 'wrong')->assertStatus(401);
    }

    public function test_ingest_stores_and_serve_returns_signed_bytes_with_etag(): void
    {
        $bytes = random_bytes(32);
        $this->ingest($bytes)->assertOk();

        PublishedSite::factory()->create(['token_hash' => hash('sha256', 'tok'), 'push_secret' => self::PUSH]);

        $response = $this->call('GET', '/api/v1/geodb', [], [], [], [
            'HTTP_X_SITE_TOKEN' => 'tok',
        ]);

        $response->assertOk();
        $this->assertSame($bytes, $response->getContent());
        $response->assertHeader('ETag', '"' . hash('sha256', $bytes) . '"');
        // Geo підпис — per-site push_secret сайта (саме його перевіряє плагін).
        $response->assertHeader('X-Signature', hash_hmac('sha256', $bytes, self::PUSH));
    }

    public function test_serve_returns_304_when_sha_matches(): void
    {
        $bytes = random_bytes(16);
        $this->ingest($bytes)->assertOk();
        PublishedSite::factory()->create(['token_hash' => hash('sha256', 'tok')]);

        $this->call('GET', '/api/v1/geodb', [], [], [], [
            'HTTP_X_SITE_TOKEN' => 'tok',
            'HTTP_IF_NONE_MATCH' => '"' . hash('sha256', $bytes) . '"',
        ])->assertStatus(304);
    }

    public function test_serve_unknown_token_unauthorized(): void
    {
        $this->call('GET', '/api/v1/geodb', [], [], [], ['HTTP_X_SITE_TOKEN' => 'nope'])
            ->assertStatus(401);
    }
}
