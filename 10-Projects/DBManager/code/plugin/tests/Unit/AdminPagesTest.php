<?php

namespace {
    if (! function_exists('get_option')) {
        global $test_options;
        $test_options = [];

        function get_option(string $option, $default = false)
        {
            global $test_options;

            return array_key_exists($option, $test_options) ? $test_options[$option] : $default;
        }
    }

    if (! function_exists('delete_option')) {
        function delete_option(string $option): void
        {
            global $test_options;

            unset($test_options[$option]);
        }
    }

    if (! function_exists('sanitize_key')) {
        function sanitize_key(string $key): string
        {
            return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $key));
        }
    }

    if (! function_exists('sanitize_html_class')) {
        function sanitize_html_class(string $class): string
        {
            return preg_replace('/[^a-zA-Z0-9_-]/', '', $class);
        }
    }

    if (! function_exists('sanitize_text_field')) {
        function sanitize_text_field(string $text): string
        {
            return trim(strip_tags($text));
        }
    }

    if (! function_exists('esc_url_raw')) {
        function esc_url_raw(string $url): string
        {
            return filter_var($url, FILTER_SANITIZE_URL);
        }
    }

    if (! function_exists('current_time')) {
        function current_time(string $type): string
        {
            return '2026-06-21 12:00:00';
        }
    }

    if (! function_exists('add_settings_error')) {
        function add_settings_error(string $setting, string $code, string $message, string $type = 'error'): void
        {
            global $test_settings_errors;

            $test_settings_errors[] = compact('setting', 'code', 'message', 'type');
        }
    }
}

namespace DBM\Tests\Unit {

    use DBM\Admin\AdminPages;
    use DBM\Config\Settings;
    use DBM\Geo\GeoSimulation;
    use PHPUnit\Framework\TestCase;

    class AdminPagesTest extends TestCase
    {
        protected function setUp(): void
        {
            global $test_options, $test_settings_errors;

            $test_options = [];
            $test_settings_errors = [];
        }

        public function test_new_connection_key_clears_stale_cache(): void
        {
            global $test_options;

            $test_options['dbm_settings'] = [
                'signing_secret' => 'old-secret',
                'shortcode' => 'dbm',
                'connection_site_id' => 2,
            ];
            $test_options['dbm_cache'] = [
                'site_id' => 2,
                'version' => 122,
                'values' => [],
            ];

            $settings = Settings::fromArray([]);
            $page = new AdminPages($settings, new GeoSimulation());

            $result = $page->sanitizeSettings([
                'connection_key' => $this->connectionKey([
                    'site_id' => 2,
                    'signing_secret' => 'new-listener-secret-with-enough-length',
                ]),
            ]);

            $this->assertSame(2, $result['connection_site_id']);
            $this->assertSame('new-listener-secret-with-enough-length', $result['signing_secret']);
            $this->assertArrayNotHasKey('dbm_cache', $test_options);
        }

        private function connectionKey(array $payload): string
        {
            $json = json_encode(array_merge([
                'v' => 1,
                'mode' => 'listener',
                'site_id' => 2,
                'ping_url' => 'https://domen.ua/?rest_route=/dbm/v1/ping',
                'signing_secret' => 'site-listener-secret-with-enough-length',
                'shortcode' => 'dbm',
            ], $payload), JSON_UNESCAPED_SLASHES);

            return 'DBM1.'.rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
        }
    }
}
