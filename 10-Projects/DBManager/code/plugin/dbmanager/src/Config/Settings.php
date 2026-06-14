<?php

namespace DBM\Config;

class Settings
{
    public function __construct(
        public string $bridgeUrl,
        public string $siteToken,
        public string $signingSecret,
        public string $pingSecret,
        public string $shortcode,
        public string $cssClass,
    ) {}

    public static function fromArray(array $a): self
    {
        return new self(
            bridgeUrl: (string) ($a['bridge_url'] ?? ''),
            siteToken: (string) ($a['site_token'] ?? ''),
            signingSecret: (string) ($a['signing_secret'] ?? ''),
            pingSecret: (string) ($a['ping_secret'] ?? ''),
            shortcode: (string) ($a['shortcode'] ?? '') ?: 'dbm',
            cssClass: (string) ($a['css_class'] ?? ''),
        );
    }
}
