<?php

namespace DBM\Tests\Unit;

use PHPUnit\Framework\TestCase;

class RenderCoreFormatTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../shared/render-core.php';
    }

    private function payload(): array
    {
        return ['values' => [
            ['key' => 'phone_ua_1', 'type' => 'phone', 'state' => 'ok', 'value' => '+380441234567'],
            ['key' => 'vb', 'type' => 'messenger', 'state' => 'ok', 'value' => '+380671112233',
             'url' => 'viber://chat?number=%2B380671112233'],
        ]];
    }

    public function test_tel_format_wraps_in_tel_link(): void
    {
        $this->assertSame(
            '<a class="val" href="tel:+380441234567">+380441234567</a>',
            dbm_render_from_cache($this->payload(), 'phone_ua_1', ['format' => 'tel', 'class' => 'val'])
        );
    }

    public function test_messenger_link_uses_url(): void
    {
        $html = dbm_render_from_cache($this->payload(), 'vb', ['format' => 'link']);
        $this->assertStringContainsString('href="viber://chat?number=%2B380671112233"', $html);
    }
}
