<?php

namespace DBM\Tests\Unit;

use DBM\Config\Settings;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    private function connectionKey(array $payload): string
    {
        $json = json_encode(array_merge([
            'v' => 1,
            'mode' => 'listener',
            'site' => 'domen.ua',
            'ping_url' => 'https://domen.ua/wp-json/dbm/v1/ping',
            'signing_secret' => 'site-listener-secret-with-enough-length',
            'shortcode' => 'dbm',
        ], $payload), JSON_UNESCAPED_SLASHES);

        return 'DBM1.'.rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    public function test_from_array_has_neutral_defaults(): void
    {
        $s = Settings::fromArray([]);
        $this->assertSame('dbm', $s->shortcode);
        $this->assertSame('', $s->cssClass);
        $this->assertSame('', $s->signingSecret);
    }

    public function test_from_array_reads_values(): void
    {
        $s = Settings::fromArray([
            'signing_secret' => 'secret-token',
            'shortcode' => 'phone',
            'css_class' => 'c'
        ]);
        $this->assertSame('secret-token', $s->signingSecret);
        $this->assertSame('phone', $s->shortcode);
        $this->assertSame('c', $s->cssClass);
    }

    public function test_from_array_reads_connection_key(): void
    {
        $s = Settings::fromArray([
            'connection_key' => $this->connectionKey(['shortcode' => 'phone']),
            'signing_secret' => 'old-secret',
        ]);

        $this->assertSame('site-listener-secret-with-enough-length', $s->signingSecret);
        $this->assertSame('phone', $s->shortcode);
    }

    public function test_invalid_connection_key_is_ignored(): void
    {
        $s = Settings::fromArray([
            'connection_key' => 'bad',
            'signing_secret' => 'old-secret',
            'shortcode' => 'dbm',
        ]);

        $this->assertSame('old-secret', $s->signingSecret);
        $this->assertSame('dbm', $s->shortcode);
    }
}
