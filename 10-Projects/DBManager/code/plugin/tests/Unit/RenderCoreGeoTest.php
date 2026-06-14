<?php

namespace DBM\Tests\Unit;

use PHPUnit\Framework\TestCase;

class RenderCoreGeoTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../shared/render-core.php';
    }

    private function payload(): array
    {
        return ['values' => [
            ['key' => 'phone_ua_1', 'state' => 'ok', 'value' => '+380441234567', 'geo' => ['UA']],
            ['key' => 'tg_brand', 'state' => 'ok', 'value' => 'https://t.me/brand', 'geo' => ['WORLD']],
        ]];
    }

    public function test_country_specific_value_shown_for_matching_country(): void
    {
        $this->assertStringContainsString('+380441234567',
            dbm_render_from_cache($this->payload(), 'phone_ua_1', ['country' => 'UA']));
    }

    public function test_country_specific_value_hidden_for_other_country(): void
    {
        $this->assertSame('',
            dbm_render_from_cache($this->payload(), 'phone_ua_1', ['country' => 'PL']));
    }

    public function test_world_value_shown_for_any_country(): void
    {
        $this->assertStringContainsString('t.me/brand',
            dbm_render_from_cache($this->payload(), 'tg_brand', ['country' => 'PL']));
    }

    public function test_unknown_country_defaults_to_world_only(): void
    {
        // Без країни (дефолт WORLD): універсальні значення видно, країнні — ні.
        $this->assertSame('', dbm_render_from_cache($this->payload(), 'phone_ua_1', []));
        $this->assertStringContainsString('t.me/brand',
            dbm_render_from_cache($this->payload(), 'tg_brand', []));
    }
}
