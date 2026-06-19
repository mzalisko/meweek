<?php

namespace Tests\Feature;

use App\Models\PublishedSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BridgeModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_site_stores_payload_and_version(): void
    {
        $site = PublishedSite::factory()->create([
            'domain' => 'domen.ua',
            'version' => 7,
            'payload' => ['site' => 'domen.ua', 'version' => 7, 'values' => []],
        ]);

        $fresh = $site->fresh();
        $this->assertSame(7, $fresh->version);
        $this->assertSame('domen.ua', $fresh->payload['site']);
    }

    public function test_token_hash_is_unique(): void
    {
        PublishedSite::factory()->create(['token_hash' => 'abc']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        PublishedSite::factory()->create(['token_hash' => 'abc']);
    }
}
