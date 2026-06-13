<?php

namespace Tests\Feature;

use App\Jobs\DeliverPingJob;
use App\Models\PublishedSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliverPingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ping.secret' => 'ping-secret']);
    }

    public function test_ping_posts_signed_payload_to_site(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $site = PublishedSite::factory()->create([
            'domain' => 'domen.ua',
            'ping_url' => 'https://domen.ua/wp-json/dbm/v1/ping',
            'version' => 5,
        ]);

        (new DeliverPingJob($site->id))->handle();

        Http::assertSent(function ($request) {
            $body = $request->body();
            $expected = hash_hmac('sha256', $body, 'ping-secret');

            return $request->url() === 'https://domen.ua/wp-json/dbm/v1/ping'
                && $request->hasHeader('X-Signature', $expected)
                && json_decode($body, true)['version'] === 5
                && json_decode($body, true)['domain'] === 'domen.ua';
        });
    }

    public function test_ping_without_url_is_noop(): void
    {
        Http::fake();
        $site = PublishedSite::factory()->create(['ping_url' => null]);

        (new DeliverPingJob($site->id))->handle();

        Http::assertNothingSent();
    }

    public function test_failed_ping_throws_for_retry(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $site = PublishedSite::factory()->create([
            'ping_url' => 'https://domen.ua/ping', 'version' => 1,
        ]);

        $this->expectException(\RuntimeException::class);
        (new DeliverPingJob($site->id))->handle();
    }

    public function test_backoff_schedule_is_exponential(): void
    {
        $job = new DeliverPingJob(1);

        $this->assertSame([60, 300, 1800, 7200, 21600, 43200, 86400], $job->backoff());
        $this->assertSame(8, $job->tries);
    }

    public function test_missing_site_is_noop(): void
    {
        Http::fake();

        (new DeliverPingJob(999999))->handle();

        Http::assertNothingSent();
    }
}
