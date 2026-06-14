<?php

namespace DBM\Admin;

class SnippetBuilder
{
    public function __construct(private string $shortcode) {}

    /** @return array{shortcode:string, tel:string, php:string} */
    public function forKey(string $key): array
    {
        return [
            'shortcode' => '[' . $this->shortcode . ' key="' . $key . '"]',
            'tel' => '[' . $this->shortcode . ' key="' . $key . '" format="tel"]',
            'php' => "dbm_get('" . $key . "')",
        ];
    }
}
