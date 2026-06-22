<?php

namespace App\Services\Publishing;

use App\Models\Publication;
use App\Models\Site;
use App\Services\Provisioning\SiteProvisioner;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BridgePublisher
{
    public function __construct(private SiteProvisioner $provisioner) {}

    /** Надіслати останній payload сайта в DataBridge. true — прийнято, false — пропущено/помилка. */
    public function push(Publication $publication): bool
    {
        $site = Site::find($publication->site_id);
        if (! $site) {
            return false;
        }

        $tokenHash = $this->provisioner->activeTokenHash($site);
        $pushSecret = $this->provisioner->activePushSecret($site);
        if (! $tokenHash || ! $pushSecret) {
            return false; // сайт без активного підключення — публікувати нікуди
        }

        $url = (string) config('services.bridge.ingest_url');
        $secret = (string) config('services.bridge.publish_secret');
        if ($url === '' || $secret === '') {
            return false;
        }

        $body = json_encode([
            'domain' => $site->domain,
            'token_hash' => $tokenHash,
            'push_secret' => $pushSecret,
            'ping_url' => $site->ping_url,
            'version' => (int) $publication->version,
            'payload' => $publication->payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $signature = hash_hmac('sha256', $body, $secret);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Signature' => $signature,
            ])->withBody($body, 'application/json')->post($url);
        } catch (\Throwable $e) {
            Log::warning('Bridge push failed', ['site' => $site->domain, 'error' => $e->getMessage()]);

            return false;
        }

        if (! $response->successful()) {
            // Bridge доступний, але відмовив (auth/stale/5xx) — раніше ковталося мовчки.
            Log::warning('Bridge push rejected', ['site' => $site->domain, 'status' => $response->status()]);

            return false;
        }

        // Успішна доставка в бридж = з'єднання живе. Позначаємо сайт «онлайн»
        // для дашборду «втрачено зв'язок» (інакше last_seen_at ніхто не пише).
        $site->tokens()->whereNull('revoked_at')->update(['last_seen_at' => now()]);

        return true;
    }
}
