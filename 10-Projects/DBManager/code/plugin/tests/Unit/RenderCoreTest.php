<?php

namespace DBM\Tests\Unit;

use PHPUnit\Framework\TestCase;

class RenderCoreTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../shared/render-core.php';
    }

    private function payload(): array
    {
        return ['version' => 4, 'values' => [
            ['key' => 'phone_ua_1', 'type' => 'phone', 'state' => 'ok', 'value' => '+380441234567'],
            ['key' => 'gone', 'type' => 'phone', 'state' => 'exhausted', 'value' => ''],
        ]];
    }

    public function test_renders_plain_value_in_neutral_span(): void
    {
        $this->assertSame('<span>+380441234567</span>',
            dbm_render_from_cache($this->payload(), 'phone_ua_1', []));
    }

    public function test_applies_configured_css_class(): void
    {
        $this->assertSame('<span class="val">+380441234567</span>',
            dbm_render_from_cache($this->payload(), 'phone_ua_1', ['class' => 'val']));
    }

    public function test_exhausted_state_renders_nothing(): void
    {
        $this->assertSame('', dbm_render_from_cache($this->payload(), 'gone', []));
    }

    public function test_unknown_key_renders_nothing(): void
    {
        $this->assertSame('', dbm_render_from_cache($this->payload(), 'nope', []));
    }

    public function test_value_is_html_escaped(): void
    {
        $payload = ['values' => [['key' => 'x', 'state' => 'ok', 'value' => '<b>&"']]];
        $this->assertStringNotContainsString('<b>', dbm_render_from_cache($payload, 'x', []));
    }
}
