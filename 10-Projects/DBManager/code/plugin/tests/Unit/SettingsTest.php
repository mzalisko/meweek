<?php

namespace DBM\Tests\Unit;

use DBM\Config\Settings;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
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
}
