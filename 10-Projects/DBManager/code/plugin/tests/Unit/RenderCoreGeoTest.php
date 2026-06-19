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

    public function test_multi_option_value_prioritizes_country_match(): void
    {
        $payload = ['values' => [
            ['key' => 'ROMANIA', 'state' => 'ok', 'value' => '1200', 'geo' => ['UA']],
            ['key' => 'ROMANIA', 'state' => 'ok', 'value' => '2000', 'geo' => ['WORLD', 'RU', 'BY']],
        ]];

        // UA visitor -> 1200
        $this->assertSame('<span>1200</span>', dbm_render_from_cache($payload, 'ROMANIA', ['country' => 'UA']));
        
        // RU visitor -> 2000
        $this->assertSame('<span>2000</span>', dbm_render_from_cache($payload, 'ROMANIA', ['country' => 'RU']));

        // PL visitor -> fallback WORLD -> 2000
        $this->assertSame('<span>2000</span>', dbm_render_from_cache($payload, 'ROMANIA', ['country' => 'PL']));
    }

    public function test_negative_geo_tag_hides_value(): void
    {
        $payload = ['values' => [
            ['key' => 'phone_world_no_ru', 'state' => 'ok', 'value' => '+1234567890', 'geo' => ['WORLD', '!RU', '!BY']],
        ]];

        // UA visitor -> shows because not excluded, matches WORLD
        $this->assertSame('<span>+1234567890</span>',
            dbm_render_from_cache($payload, 'phone_world_no_ru', ['country' => 'UA']));

        // RU visitor -> hidden due to !RU
        $this->assertSame('',
            dbm_render_from_cache($payload, 'phone_world_no_ru', ['country' => 'RU']));

        // BY visitor -> hidden due to !BY
        $this->assertSame('',
            dbm_render_from_cache($payload, 'phone_world_no_ru', ['country' => 'BY']));

        // PL visitor -> shows because matches WORLD
        $this->assertSame('<span>+1234567890</span>',
            dbm_render_from_cache($payload, 'phone_world_no_ru', ['country' => 'PL']));
    }

    public function test_negative_geo_tag_with_fallback_candidate(): void
    {
        $payload = ['values' => [
            ['key' => 'tg_chat', 'state' => 'ok', 'value' => 'https://t.me/world', 'geo' => ['WORLD', '!RU']],
            ['key' => 'tg_chat', 'state' => 'ok', 'value' => 'https://t.me/ru', 'geo' => ['RU']],
        ]];

        // UA visitor -> WORLD (https://t.me/world)
        $this->assertSame('<span class="css-val">https://t.me/world</span>',
            dbm_render_from_cache($payload, 'tg_chat', ['country' => 'UA', 'class' => 'css-val']));

        // RU visitor -> RU (https://t.me/ru)
        $this->assertSame('<span>https://t.me/ru</span>',
            dbm_render_from_cache($payload, 'tg_chat', ['country' => 'RU']));
    }
}
