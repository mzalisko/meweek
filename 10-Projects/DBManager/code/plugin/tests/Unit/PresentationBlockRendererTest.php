<?php

namespace DBM\Tests\Unit;

use DBM\Wp\PresentationBlockRenderer;
use PHPUnit\Framework\TestCase;

class PresentationBlockRendererTest extends TestCase
{
    public function test_renders_current_payload_as_interactive_groups(): void
    {
        $html = (new PresentationBlockRenderer())->render([
            'site_id' => 123,
            'version' => 50,
            'values' => [
                ['geo' => ['WORLD'], 'key' => 'phone_ro_1', 'type' => 'phone', 'state' => 'ok', 'value' => '+48888888888', 'display_value' => '+48 888 888 888'],
                ['geo' => ['WORLD', 'UA'], 'key' => 'test', 'type' => 'messenger', 'state' => 'ok', 'network' => 'Telegram', 'value' => 'https://example.test/chat'],
                ['geo' => ['UA', 'RU'], 'key' => 'price_ro', 'type' => 'price', 'label' => 'UA', 'state' => 'ok', 'value' => '1200'],
            ],
        ], ['country' => 'UA']);

        $this->assertStringContainsString('ID: 123', $html);
        $this->assertStringContainsString('data-dbm-filter="phone"', $html);
        $this->assertStringContainsString('data-dbm-filter="messenger"', $html);
        $this->assertStringContainsString('data-dbm-filter="price"', $html);
        $this->assertStringContainsString('+48 888 888 888', $html);
        $this->assertStringContainsString('href="tel:+48888888888"', $html);
        $this->assertStringContainsString('Telegram', $html);
        $this->assertStringContainsString('1200', $html);
        $this->assertLessThan(strpos($html, 'Telegram'), strpos($html, '+48888888888'));
        $this->assertLessThan(strpos($html, '1200'), strpos($html, 'Telegram'));
    }

    public function test_renders_social_address_and_text_types(): void
    {
        $html = (new PresentationBlockRenderer())->render([
            'site_id' => 7,
            'values' => [
                ['geo' => ['WORLD'], 'key' => 'social_ig', 'type' => 'social', 'state' => 'ok', 'network' => 'Instagram', 'value' => '@brand', 'url' => 'https://instagram.com/brand'],
                ['geo' => ['WORLD'], 'key' => 'addr_main', 'type' => 'address', 'state' => 'ok', 'value' => 'Украина, Киев, ул. Крещатик 1', 'city' => 'Киев', 'country' => 'Украина'],
                ['geo' => ['WORLD'], 'key' => 'note', 'type' => 'text', 'state' => 'ok', 'label' => 'График', 'value' => 'Пн-Пт 9-18'],
            ],
        ], ['country' => 'UA']);

        // Нові типи отримали власні вкладки-фільтри й секції.
        $this->assertStringContainsString('data-dbm-filter="social"', $html);
        $this->assertStringContainsString('data-dbm-filter="address"', $html);
        $this->assertStringContainsString('data-dbm-filter="text"', $html);

        // Соцмережа: мережа як заголовок, нікнейм як значення, безпечне http(s)-посилання.
        $this->assertStringContainsString('Instagram', $html);
        $this->assertStringContainsString('@brand', $html);
        $this->assertStringContainsString('href="https://instagram.com/brand"', $html);

        // Адреса: дзеркало value показується, місто йде заголовком картки.
        $this->assertStringContainsString('Украина, Киев, ул. Крещатик 1', $html);
        $this->assertStringContainsString('Киев', $html);

        // Текст: лейбл як заголовок, значення видиме.
        $this->assertStringContainsString('Пн-Пт 9-18', $html);
    }

    public function test_social_type_rejects_unsafe_link(): void
    {
        $html = (new PresentationBlockRenderer())->render([
            'values' => [
                ['key' => 'social_bad', 'type' => 'social', 'state' => 'ok', 'network' => 'X', 'value' => 'handle', 'url' => 'javascript:alert(1)'],
            ],
        ]);

        $this->assertStringNotContainsString('href="javascript:alert(1)"', $html);
    }

    public function test_skips_hidden_values_and_does_not_create_unsafe_links(): void
    {
        $html = (new PresentationBlockRenderer())->render([
            'values' => [
                ['key' => 'hidden_phone', 'type' => 'phone', 'state' => 'hidden', 'value' => '+380000000000'],
                ['key' => 'bad_link', 'type' => 'messenger', 'state' => 'ok', 'network' => 'Chat', 'url' => 'javascript:alert(1)', 'value' => 'javascript:alert(1)'],
            ],
        ]);

        $this->assertStringNotContainsString('+380000000000', $html);
        $this->assertStringNotContainsString('href="javascript:alert(1)"', $html);
        $this->assertStringContainsString('javascript:alert(1)', $html);
    }

    public function test_russian_translation_and_geo_exclusion(): void
    {
        $html = (new PresentationBlockRenderer())->render([
            'values' => [
                ['geo' => ['WORLD', '!UA'], 'key' => 'phone_world', 'type' => 'phone', 'state' => 'ok', 'value' => '+1234567890'],
                ['geo' => ['WORLD'], 'key' => 'phone_pl', 'type' => 'phone', 'state' => 'ok', 'value' => '+9876543210'],
            ],
        ], ['country' => 'UA']);

        // Russian UI assertions
        $this->assertStringContainsString('Данные еще не доставлены', (new PresentationBlockRenderer())->render([]));
        $this->assertStringContainsString('Текущие данные', $html);
        $this->assertStringContainsString('Номера', $html);

        // Geo-exclusion filter assertions for UA visitor:
        // phone_world is excluded because of !UA
        $this->assertStringNotContainsString('+1234567890', $html);
        // phone_pl is shown because WORLD matches UA (no exclusion)
        $this->assertStringContainsString('+9876543210', $html);
    }
}
