<?php

namespace App\Jobs;

use App\Models\PublishedSite;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DeliverPingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 8;

    public function __construct(public int $publishedSiteId) {}

    /** Наростаючий backoff: 1хв → 5хв → 30хв → 2г → 6г → 12г → 24г. */
    public function backoff(): array
    {
        return [60, 300, 1800, 7200, 21600, 43200, 86400];
    }

    public function handle(): void
    {
        $site = PublishedSite::find($this->publishedSiteId);

        if (! $site || ! $site->ping_url) {
            return; // сайт зник або не має URL для пінга — нічого робити
        }

        $secret = (string) $site->push_secret;
        if ($secret === '') {
            return; // сайт ще не має listener-секрета — нічого доставляти
        }

        $body = json_encode($site->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();

        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $response = $this->postPayload($site->ping_url, $body, $signature, $timestamp);

        if ($this->looksLikeHtml($response->body())) {
            $fallbackUrl = $this->plainWordPressRestUrl($site->ping_url);
            if ($fallbackUrl !== null && $fallbackUrl !== $site->ping_url) {
                $response = $this->postPayload($fallbackUrl, $body, $signature, $timestamp);
            }
        }

        if ($response->failed() || $this->looksLikeHtml($response->body())) {
            throw new \RuntimeException("Ping failed for {$site->domain}: HTTP {$response->status()}");
        }
    }

    private function postPayload(string $url, string $body, string $signature, string $timestamp): \Illuminate\Http\Client\Response
    {
        return Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Signature' => $signature,
            'X-Timestamp' => $timestamp,
        ])->withBody($body, 'application/json')->post($url);
    }

    private function plainWordPressRestUrl(string $url): ?string
    {
        if (! str_contains($url, '/wp-json/dbm/v1/ping')) {
            return null;
        }

        return preg_replace('#/wp-json/dbm/v1/ping/?$#', '/?rest_route=/dbm/v1/ping', $url) ?: null;
    }

    private function looksLikeHtml(string $body): bool
    {
        $body = ltrim($body);

        return str_starts_with($body, '<!DOCTYPE html')
            || str_starts_with($body, '<html');
    }
}
