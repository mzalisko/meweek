<?php

namespace DBM\Config;

class Settings
{
    public function __construct(
        public string $signingSecret,
        public string $shortcode,
        public string $cssClass,
    ) {}

    public static function fromArray(array $a): self
    {
        $decoded = self::decodeConnectionKey((string) ($a['connection_key'] ?? '')) ?? [];
        $shortcode = (string) ($decoded['shortcode'] ?? ($a['shortcode'] ?? ''));

        return new self(
            signingSecret: (string) ($decoded['signing_secret'] ?? ($a['signing_secret'] ?? '')),
            shortcode: $shortcode !== '' ? $shortcode : 'dbm',
            cssClass: (string) ($a['css_class'] ?? ''),
        );
    }

    public static function decodeConnectionKey(string $key): ?array
    {
        $key = preg_replace('/\s+/', '', trim($key)) ?? '';
        if ($key === '') {
            return null;
        }

        if (! str_starts_with($key, 'DBM1.')) {
            return null;
        }

        $encoded = substr($key, 5);
        $padding = strlen($encoded) % 4;
        if ($padding > 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }

        $json = base64_decode(strtr($encoded, '-_', '+/'), true);
        if (! is_string($json)) {
            return null;
        }

        $payload = json_decode($json, true);
        if (! is_array($payload)) {
            return null;
        }

        if (($payload['v'] ?? null) !== 1 || ($payload['mode'] ?? null) !== 'listener') {
            return null;
        }

        $secret = (string) ($payload['signing_secret'] ?? '');
        if (strlen($secret) < 32) {
            return null;
        }

        return [
            'site_id' => (int) ($payload['site_id'] ?? 0),
            'ping_url' => (string) ($payload['ping_url'] ?? ''),
            'signing_secret' => $secret,
            'shortcode' => (string) ($payload['shortcode'] ?? ''),
        ];
    }
}
