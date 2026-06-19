<?php

namespace Tests\Feature;

use App\Models\PublishedSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.data.signing_secret' => 'sign']);
    }

    private function site(): PublishedSite
    {
        return PublishedSite::factory()->create([
            'token_hash' => hash('sha256', 'tok'),
            'version' => 9,
            'payload' => ['site' => 'd.ua', 'version' => 9, 'values' => []],
        ]);
    }

    public function test_matching_version_returns_304_without_body(): void
    {
        $this->site();

        $response = $this->getJson('/api/v1/data', [
            'X-Site-Token' => 'tok',
            'If-None-Match' => '"9"',
        ]);

        $response->assertStatus(304);
        $this->assertSame('', $response->getContent());
        $response->assertHeader('ETag', '"9"');
    }

    public function test_stale_version_returns_200_with_fresh_payload(): void
    {
        $this->site();

        $response = $this->getJson('/api/v1/data', [
            'X-Site-Token' => 'tok',
            'If-None-Match' => '"7"',
        ]);

        $response->assertOk();
        $this->assertSame(9, $response->json('version'));
    }
}
