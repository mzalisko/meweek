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
        return new self(
            signingSecret: (string) ($a['signing_secret'] ?? ''),
            shortcode: (string) ($a['shortcode'] ?? '') ?: 'dbm',
            cssClass: (string) ($a['css_class'] ?? ''),
        );
    }
}
