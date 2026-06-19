<?php

namespace {
    // Stub WordPress functions in global namespace for tests
    if (! function_exists('add_shortcode')) {
        global $test_shortcodes;
        $test_shortcodes = [];

        function add_shortcode(string $tag, callable $callback): void
        {
            global $test_shortcodes;
            $test_shortcodes[$tag] = $callback;
        }
    }

    if (! function_exists('shortcode_atts')) {
        function shortcode_atts(array $pairs, array $atts): array
        {
            $out = [];
            foreach ($pairs as $name => $default) {
                if (array_key_exists($name, $atts)) {
                    $out[$name] = $atts[$name];
                } else {
                    $out[$name] = $default;
                }
            }
            return $out;
        }
    }

    if (! function_exists('get_option')) {
        global $test_options;
        $test_options = [];

        function get_option(string $option, $default = false)
        {
            global $test_options;
            return isset($test_options[$option]) ? $test_options[$option] : $default;
        }
    }

    if (! function_exists('update_option')) {
        function update_option(string $option, $value): void
        {
            global $test_options;
            $test_options[$option] = $value;
        }
    }

    if (! function_exists('esc_html')) {
        function esc_html(string $text): string
        {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
    }

    if (! function_exists('esc_attr')) {
        function esc_attr(string $text): string
        {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }
    }

    if (! function_exists('esc_url')) {
        function esc_url(string $url): string
        {
            return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        }
    }
}

namespace DBM\Tests\Unit {

    use DBM\Config\Settings;
    use DBM\Wp\ShortcodeController;
    use PHPUnit\Framework\TestCase;

    class ShortcodesTest extends TestCase
    {
        protected function setUp(): void
        {
            global $test_shortcodes, $test_options;
            $test_shortcodes = [];
            $test_options = [];
        }

        private function setupCache(array $values): void
        {
            global $test_options;
            $test_options['dbm_cache'] = [
                'site' => 'testsite.ua',
                'version' => 12,
                'values' => $values
            ];
        }

        public function test_price_shortcode_picks_correct_country_value(): void
        {
            $this->setupCache([
                [
                    'key' => 'romania',
                    'type' => 'price',
                    'geo' => ['UA'],
                    'value' => '100',
                    'label' => 'грн',
                    'state' => 'ok'
                ],
                [
                    'key' => 'romania',
                    'type' => 'price',
                    'geo' => ['RO'],
                    'value' => '15',
                    'label' => 'RON',
                    'state' => 'ok'
                ],
                [
                    'key' => 'romania',
                    'type' => 'price',
                    'geo' => ['WORLD'],
                    'value' => '5',
                    'label' => 'EUR',
                    'state' => 'ok'
                ]
            ]);

            $settings = new Settings('', 'dbm', '');
            global $test_shortcodes;
            
            // 1. Test for UA visitor
            $controllerUA = new ShortcodeController($settings, 'UA');
            $controllerUA->register();
            $priceCallbackUA = $test_shortcodes['dbm_price'];

            $htmlUA = $priceCallbackUA(['key' => 'romania']);
            $this->assertSame('<span>100</span>', $htmlUA);

            // 2. Test for RO visitor
            $controllerRO = new ShortcodeController($settings, 'RO');
            $controllerRO->register();
            $priceCallbackRO = $test_shortcodes['dbm_price'];
            
            $htmlRO = $priceCallbackRO(['key' => 'romania']);
            $this->assertSame('<span>15</span>', $htmlRO);

            // 3. Test for PL visitor (WORLD fallback)
            $controllerPL = new ShortcodeController($settings, 'PL');
            $controllerPL->register();
            $priceCallbackPL = $test_shortcodes['dbm_price'];
            
            $htmlPL = $priceCallbackPL(['key' => 'romania']);
            $this->assertSame('<span>5</span>', $htmlPL);
        }

        public function test_price_shortcode_attributes(): void
        {
            $this->setupCache([
                [
                    'key' => 'romania',
                    'type' => 'price',
                    'geo' => ['UA'],
                    'value' => '100',
                    'label' => 'грн',
                    'state' => 'ok'
                ]
            ]);

            $settings = new Settings('', 'dbm', '');
            $controller = new ShortcodeController($settings, 'UA');
            $controller->register();

            global $test_shortcodes;
            $priceCallback = $test_shortcodes['dbm_price'];

            // Tag and class attributes
            $html = $priceCallback([
                'key' => 'romania',
                'tag' => 'strong',
                'class' => 'my-price'
            ]);
            $this->assertSame('<strong class="my-price">100</strong>', $html);

            // show_label="no" attribute
            $htmlNoLabel = $priceCallback([
                'key' => 'romania',
                'show_label' => 'no'
            ]);
            $this->assertSame('<span>100</span>', $htmlNoLabel);
        }

        public function test_phone_block_shortcode_renders_phone_with_messengers(): void
        {
            $this->setupCache([
                [
                    'key' => 'main_phone',
                    'type' => 'phone',
                    'geo' => ['WORLD'],
                    'value' => '+380441234567',
                    'display_value' => '+380 (44) 123-45-67',
                    'state' => 'ok'
                ],
                [
                    'key' => 'tg_mess',
                    'type' => 'messenger',
                    'geo' => ['WORLD'],
                    'network' => 'telegram',
                    'value' => 'https://t.me/test_tg',
                    'linked_slot' => 'main_phone',
                    'state' => 'ok'
                ],
                [
                    'key' => 'wa_mess',
                    'type' => 'messenger',
                    'geo' => ['!UA'], // Excluded for UA
                    'network' => 'whatsapp',
                    'value' => 'https://wa.me/123',
                    'linked_slot' => 'main_phone',
                    'state' => 'ok'
                ]
            ]);

            $settings = new Settings('', 'dbm', '');
            $controller = new ShortcodeController($settings, 'UA');
            $controller->register();

            global $test_shortcodes;
            $phoneBlockCallback = $test_shortcodes['dbm_phone_block'];

            $html = $phoneBlockCallback(['key' => 'main_phone']);

            // Must contain phone icon and link
            $this->assertStringContainsString('href="tel:+380441234567"', $html);
            $this->assertStringContainsString('+380 (44) 123-45-67', $html);

            // Must contain Telegram messenger link
            $this->assertStringContainsString('href="https://t.me/test_tg"', $html);
            $this->assertStringContainsString('class="dbm-phone-block__msg-link dbm-phone-block__msg-link--telegram"', $html);

            // Must NOT contain WhatsApp messenger link since UA is excluded
            $this->assertStringNotContainsString('href="https://wa.me/123"', $html);
        }

        public function test_global_helper_functions_work_identically(): void
        {
            $this->setupCache([
                [
                    'key' => 'romania',
                    'type' => 'price',
                    'geo' => ['UA'],
                    'value' => '100',
                    'label' => 'грн',
                    'state' => 'ok'
                ],
                [
                    'key' => 'main_phone',
                    'type' => 'phone',
                    'geo' => ['WORLD'],
                    'value' => '+380441234567',
                    'display_value' => '+380 (44) 123-45-67',
                    'state' => 'ok'
                ]
            ]);

            $settings = new Settings('', 'dbm', '');
            $controller = new ShortcodeController($settings, 'UA');
            $controller->register();

            // Test global dbm_price
            $this->assertTrue(function_exists('dbm_price'));
            $this->assertSame('<span>100</span>', dbm_price('romania'));

            // Test global dbm_phone_block
            $this->assertTrue(function_exists('dbm_phone_block'));
            $htmlBlock = dbm_phone_block('main_phone');
            $this->assertStringContainsString('href="tel:+380441234567"', $htmlBlock);
            $this->assertStringContainsString('+380 (44) 123-45-67', $htmlBlock);
        }

        public function test_price_suffix_and_rubyp_rules(): void
        {
            $this->setupCache([
                [
                    'key' => 'romania',
                    'type' => 'price',
                    'geo' => ['UA'],
                    'value' => '100',
                    'label' => 'грн',
                    'state' => 'ok'
                ],
                [
                    'key' => 'romania',
                    'type' => 'price',
                    'geo' => ['WORLD'],
                    'value' => '5',
                    'label' => 'EUR',
                    'state' => 'ok'
                ],
                [
                    'key' => 'price_ro',
                    'type' => 'price',
                    'geo' => ['UA'],
                    'value' => '1200',
                    'label' => 'uk',
                    'state' => 'ok'
                ],
                [
                    'key' => 'price_ro',
                    'type' => 'price',
                    'geo' => ['WORLD'],
                    'value' => '5000',
                    'label' => 'фів',
                    'state' => 'ok'
                ]
            ]);

            $settings = new Settings('', 'dbm', '');
            
            // 1. Explicit country selection via suffix (romania_world from UA visitor)
            $controllerUA = new ShortcodeController($settings, 'UA');
            $controllerUA->register();
            global $test_shortcodes;
            $priceCallback = $test_shortcodes['dbm_price'];

            $htmlWorld = $priceCallback(['key' => 'romania_world']);
            $this->assertSame('<span>5</span>', $htmlWorld);

            // romania_ua from PL visitor
            $controllerPL = new ShortcodeController($settings, 'PL');
            $controllerPL->register();
            $priceCallbackPL = $test_shortcodes['dbm_price'];

            $htmlUa = $priceCallbackPL(['key' => 'romania_ua']);
            $this->assertSame('<span>100</span>', $htmlUa);

            // 2. RU/BY visitor must not fall back to WORLD price
            $controllerRU = new ShortcodeController($settings, 'RU');
            $controllerRU->register();
            $priceCallbackRU = $test_shortcodes['dbm_price'];

            $htmlRU = $priceCallbackRU(['key' => 'romania']);
            $this->assertSame('', $htmlRU);

            // 3. Label-based suffix matches
            $controllerUA->register();
            $priceCallbackUA = $test_shortcodes['dbm_price'];
            $this->assertSame('<span>1200</span>', $priceCallbackUA(['key' => 'price_ro_uk']));
            $this->assertSame('<span>1200</span>', $priceCallbackUA(['key' => 'price_ro uk']));
            $this->assertSame('<span>5000</span>', $priceCallbackUA(['key' => 'price_ro_фів']));
            $this->assertSame('<span>5000</span>', $priceCallbackUA(['key' => 'price_ro фів']));

            // 4. Dynamic country resolution for price_ro
            $this->assertSame('<span>1200</span>', $priceCallbackUA(['key' => 'price_ro'])); // UA
            
            $controllerPL->register();
            $priceCallbackPL = $test_shortcodes['dbm_price'];
            $this->assertSame('<span>5000</span>', $priceCallbackPL(['key' => 'price_ro'])); // PL -> WORLD
            
            $controllerRU->register();
            $priceCallbackRU = $test_shortcodes['dbm_price'];
            $this->assertSame('', $priceCallbackRU(['key' => 'price_ro'])); // RU -> no WORLD fallback
        }
    }
}
