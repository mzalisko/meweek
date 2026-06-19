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

    public function test_ping_posts_signed_payload_to_site(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $site = PublishedSite::factory()->create([
            'domain' => 'domen.ua',
            'push_secret' => 'site-listener-secret-with-enough-length',
            'ping_url' => 'https://domen.ua/wp-json/dbm/v1/ping',
            'version' => 5,
            'payload' => ['site' => 'domen.ua', 'version' => 5, 'values' => [['key' => 'phone']]],
        ]);

        (new DeliverPingJob($site->id))->handle();

        Http::assertSent(function ($request) {
            $body = $request->body();
            $timestamp = $request->header('X-Timestamp')[0] ?? '';
            $expected = hash_hmac('sha256', $timestamp.'.'.$body, 'site-listener-secret-with-enough-length');
            $json = json_decode($body, true);

            return $request->url() === 'https://domen.ua/wp-json/dbm/v1/ping'
                && $request->hasHeader('X-Signature', $expected)
                && $timestamp !== ''
                && $json['version'] === 5
                && $json['site'] === 'domen.ua'
                && $json['values'][0]['key'] === 'phone';
        });
    }

    public function test_wp_json_html_response_falls_back_to_plain_rest_route(): void
    {
        Http::fake([
            'https://domen.ua/wp-json/dbm/v1/ping' => Http::response('<!DOCTYPE html><html></html>', 200),
            'https://domen.ua/?rest_route=/dbm/v1/ping' => Http::response(['accepted' => true], 200),
        ]);
        $site = PublishedSite::factory()->create([
            'domain' => 'domen.ua',
            'push_secret' => 'site-listener-secret-with-enough-length',
            'ping_url' => 'https://domen.ua/wp-json/dbm/v1/ping',
            'version' => 5,
            'payload' => ['site' => 'domen.ua', 'version' => 5, 'values' => []],
        ]);

        (new DeliverPingJob($site->id))->handle();

        Http::assertSent(fn ($request) => $request->url() === 'https://domen.ua/wp-json/dbm/v1/ping');
        Http::assertSent(fn ($request) => $request->url() === 'https://domen.ua/?rest_route=/dbm/v1/ping');
    }

    public function test_ping_without_url_is_noop(): void
    {
        Http::fake();
        $site = PublishedSite::factory()->create(['ping_url' => null]);

        (new DeliverPingJob($site->id))->handle();

        Http::assertNothingSent();
    }

    public function test_missing_push_secret_is_noop(): void
    {
        Http::fake();
        $site = PublishedSite::factory()->create([
            'push_secret' => null,
            'ping_url' => 'https://domen.ua/ping',
            'version' => 1,
        ]);

        (new DeliverPingJob($site->id))->handle();

        Http::assertNothingSent();
    }

    public function test_failed_ping_throws_for_retry(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $site = PublishedSite::factory()->create([
            'push_secret' => 'site-listener-secret-with-enough-length',
            'ping_url' => 'https://domen.ua/ping',
            'version' => 1,
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
