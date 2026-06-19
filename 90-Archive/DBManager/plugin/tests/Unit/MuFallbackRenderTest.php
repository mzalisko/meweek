<?php

namespace DBM\Tests\Unit;

use PHPUnit\Framework\TestCase;

class MuFallbackRenderTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../shared/render-core.php';
    }

    public function test_renders_from_cache_payload(): void
    {
        $cache = ['version' => 4, 'values' => [
            ['key' => 'phone_ua_1', 'state' => 'ok', 'value' => '+380441234567'],
        ]];

        $this->assertSame(
            '<span>+380441234567</span>',
            dbm_render_from_cache($cache, 'phone_ua_1', [])
        );
    }

    public function test_missing_key_renders_empty(): void
    {
        $this->assertSame('', dbm_render_from_cache(['values' => []], 'x', []));
    }
}
