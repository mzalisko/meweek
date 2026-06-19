<?php

namespace App\Services\Provisioning;

use App\Models\ApiToken;
use App\Models\Site;
use Illuminate\Support\Str;

class SiteProvisioner
{
    /** Згенерувати токен сайта; повертає СИРИЙ токен один раз (у БД лише хеш). */
    public function issueToken(Site $site, ?string $label = null): string
    {
        $raw = Str::random(48);

        ApiToken::create([
            'site_id' => $site->id,
            'token_hash' => hash('sha256', $raw),
            'push_secret' => Str::random(64),
            'label' => $label,
        ]);

        return $raw;
    }

    /**
     * Create a one-time listener-only connection package for the WordPress plugin.
     *
     * The raw site token is never shown to the plugin; it is only used by Core/Bridge
     * as a lookup hash. The plugin receives a per-site push secret and listens for
     * signed payloads on its own REST endpoint.
     *
     * @return array{connection_key:string, ping_url:string}
     */
    public function issuePluginConnection(Site $site, ?string $label = null): array
    {
        $rawToken = Str::random(48);
        $pushSecret = Str::random(64);
        $pingUrl = $this->normalizePingUrl($site->ping_url ?: $this->defaultPingUrl($site));

        ApiToken::create([
            'site_id' => $site->id,
            'token_hash' => hash('sha256', $rawToken),
            'push_secret' => $pushSecret,
            'label' => $label ?? 'WordPress listener',
        ]);

        $site->forceFill(['ping_url' => $pingUrl])->save();

        $payload = [
            'v' => 1,
            'mode' => 'listener',
            'site_id' => $site->id,
            'ping_url' => $pingUrl,
            'signing_secret' => $pushSecret,
            'shortcode' => 'dbm',
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $encoded = rtrim(strtr(base64_encode((string) $json), '+/', '-_'), '=');

        return [
            'connection_key' => 'DBM1.'.$encoded,
            'ping_url' => $pingUrl,
        ];
    }

    /** Хеш останнього чинного (невідкликаного) токена сайта або null. */
    public function activeTokenHash(Site $site): ?string
    {
        return ApiToken::where('site_id', $site->id)
            ->whereNull('revoked_at')
            ->latest('id')
            ->value('token_hash');
    }

    public function activePushSecret(Site $site): ?string
    {
        return ApiToken::where('site_id', $site->id)
            ->whereNull('revoked_at')
            ->latest('id')
            ->value('push_secret');
    }

    /** Відкликати всі чинні токени сайта; повертає кількість відкликаних. */
    public function revokeToken(Site $site): int
    {
        return ApiToken::where('site_id', $site->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private function defaultPingUrl(Site $site): string
    {
        $host = trim(preg_replace('#^https?://#i', '', trim($site->domain)), '/');
        $scheme = $this->isLocalHost($host) ? 'http' : 'https';

        return $scheme.'://'.$host.'/?rest_route=/dbm/v1/ping';
    }

    private function normalizePingUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return $url;
        }

        $parts = parse_url($url);
        $path = $parts['path'] ?? '';
        if (! str_ends_with($path, '/wp-json/dbm/v1/ping')) {
            return $url;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        if ($host === '') {
            return $url;
        }

        return $scheme.'://'.$host.$port.'/?rest_route=/dbm/v1/ping';
    }

    private function isLocalHost(string $host): bool
    {
        return $host === 'localhost'
            || str_starts_with($host, 'localhost:')
            || str_starts_with($host, '127.')
            || str_starts_with($host, '[::1]')
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.local');
    }
}
