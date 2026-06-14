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

        $secret = (string) config('services.ping.secret');
        if ($secret === '') {
            // Без секрета не шлемо пінг із порожнім ключем — fail-closed.
            throw new \RuntimeException('Ping secret is not configured');
        }

        $body = json_encode([
            'domain' => $site->domain,
            'version' => (int) $site->version,
        ], JSON_UNESCAPED_SLASHES);

        $signature = hash_hmac('sha256', $body, $secret);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Signature' => $signature,
        ])->withBody($body, 'application/json')->post($site->ping_url);

        if ($response->failed()) {
            throw new \RuntimeException("Ping failed for {$site->domain}: HTTP {$response->status()}");
        }
    }
}
