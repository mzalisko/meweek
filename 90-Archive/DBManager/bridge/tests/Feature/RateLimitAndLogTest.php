<?php

namespace Tests\Feature;

use App\Models\PublishedSite;
use App\Models\RequestLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitAndLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.data.signing_secret' => 'sign']);
    }

    public function test_serve_request_is_logged(): void
    {
        PublishedSite::factory()->create([
            'token_hash' => hash('sha256', 'tok'), 'version' => 1,
            'payload' => ['site' => 'd.ua', 'version' => 1, 'values' => []],
        ]);

        $this->getJson('/api/v1/data', ['X-Site-Token' => 'tok'])->assertOk();

        $log = RequestLog::where('path', 'api/v1/data')->first();
        $this->assertNotNull($log);
        $this->assertSame(200, $log->status);
        $this->assertSame(hash('sha256', 'tok'), $log->token_hash);
    }

    public function test_unauthorized_request_is_logged_with_401(): void
    {
        $this->getJson('/api/v1/data', ['X-Site-Token' => 'nope'])->assertStatus(401);

        $this->assertTrue(RequestLog::where('path', 'api/v1/data')->where('status', 401)->exists());
    }

    public function test_serve_is_rate_limited(): void
    {
        PublishedSite::factory()->create([
            'token_hash' => hash('sha256', 'tok'), 'version' => 1,
            'payload' => ['site' => 'd.ua', 'version' => 1, 'values' => []],
        ]);

        $hit429 = false;
        for ($i = 0; $i < 125; $i++) {
            $status = $this->getJson('/api/v1/data', ['X-Site-Token' => 'tok'])->status();
            if ($status === 429) {
                $hit429 = true;
                break;
            }
        }
        $this->assertTrue($hit429, 'Очікували 429 у межах ліміту');
    }
}
