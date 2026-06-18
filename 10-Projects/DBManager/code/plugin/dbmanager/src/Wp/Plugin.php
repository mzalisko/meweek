<?php

namespace DBM\Wp;

use DBM\Admin\AdminPages;
use DBM\Cache\WpOptionCacheStore;
use DBM\Config\Settings;
use DBM\Http\WpHttpDataClient;
use DBM\Rest\PingController;
use DBM\Sync\PayloadVerifier;
use DBM\Sync\Synchronizer;

class Plugin
{
    private function settings(): Settings
    {
        $opts = get_option('dbm_settings');

        return Settings::fromArray(is_array($opts) ? $opts : []);
    }

    private function synchronizer(): Synchronizer
    {
        $s = $this->settings();

        return new Synchronizer(
            new WpHttpDataClient(),
            new WpOptionCacheStore(),
            new PayloadVerifier(),
            rtrim($s->bridgeUrl, '/') . '/api/v1/data',
            $s->siteToken,
            $s->signingSecret,
        );
    }

    public function register(): void
    {
        add_action('init', [$this, 'registerShortcode']);
        add_action('rest_api_init', [$this, 'registerRest']);
        add_action('dbm_daily_reconcile', [$this, 'runReconcile']);
        add_action('dbm_geodb_sync', [$this, 'runGeoDbSync']);
        add_filter('cron_schedules', fn ($s) => $s); // добова — через wp_schedule_event(daily)
        register_activation_hook(
            dirname(__DIR__, 2) . '/dbmanager.php',
            function (): void {
                if (! wp_next_scheduled('dbm_daily_reconcile')) {
                    wp_schedule_event(time() + 3600, 'daily', 'dbm_daily_reconcile');
                }
                if (! wp_next_scheduled('dbm_geodb_sync')) {
                    wp_schedule_event(time() + 3600, 'weekly', 'dbm_geodb_sync');
                }
            }
        );
        register_deactivation_hook(
            dirname(__DIR__, 2) . '/dbmanager.php',
            function (): void {
                wp_clear_scheduled_hook('dbm_daily_reconcile');
                wp_clear_scheduled_hook('dbm_geodb_sync');
                // Кеш (dbm_cache) НЕ видаляємо — живучість даних (§8).
            }
        );

        if (is_admin()) {
            (new AdminPages($this->settings()))->register();
        }
    }

    public function registerShortcode(): void
    {
        $s = $this->settings();

        if (! function_exists('dbm_get')) {
            require_once dirname(__DIR__, 2) . '/../shared/render-core.php';

            // INTENTIONAL eval: defines a named global function at runtime so both the main
            // plugin and the mu-fallback can declare dbm_get() without collision. The eval
            // string is a literal constant in source — no external input reaches it.
            // See plan note: "навмисний компроміс" (plan 1.3, Task 10, Step 1).
            eval('function dbm_get(string $key, array $opts = []): string {
                $cache = get_option("dbm_cache"); $cache = is_array($cache) ? $cache : ["values" => []];
                $st = get_option("dbm_settings");
                $opts["class"] = $opts["class"] ?? (is_array($st) ? (string)($st["css_class"] ?? "") : "");
                $opts["country"] = $opts["country"] ?? ($GLOBALS["dbm_country"] ?? "WORLD");
                return dbm_render_from_cache($cache, $key, $opts);
            }');
        }

        // Detect country once per request: CF-IPCountry → MaxMind lookup → WORLD.
        $detector = new \DBM\Geo\GeoDetector(
            new \DBM\Geo\MaxMindCountryLookup((string) (get_option('dbm_geodb_path') ?: ''))
        );
        $country = $detector->detect(
            ['CF-IPCountry' => $_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''],
            (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''))
        );

        $simulated = get_option('dbm_simulated_country');
        if (! empty($simulated) && $simulated !== 'disabled') {
            $country = strtoupper($simulated);
        }

        // Доступно для прямих викликів dbm_get('key') у шаблонах (без явної країни) — #2 рев'ю.
        $GLOBALS['dbm_country'] = $country;

        add_shortcode($s->shortcode, function ($atts) use ($country) {
            $atts = shortcode_atts(['key' => '', 'format' => ''], $atts);

            return dbm_get((string) $atts['key'], ['format' => (string) $atts['format'], 'country' => $country]);
        });

        // Застосовуємо шорткоди до стандартних полів WordPress
        add_filter('the_title', 'do_shortcode');
        add_filter('the_excerpt', 'do_shortcode');
        add_filter('widget_text', 'do_shortcode');

        // Застосовуємо шорткоди до полів ACF перед їх виведенням
        add_filter('acf/format_value', function ($value) {
            if (is_string($value)) {
                return do_shortcode($value);
            }
            return $value;
        }, 10, 1);
    }

    public function registerRest(): void
    {
        $s = $this->settings();
        $sync = fn () => $this->synchronizer()->sync();
        $controller = new PingController(new PayloadVerifier(), $s->pingSecret, $sync);

        register_rest_route('dbm/v1', '/ping', [
            'methods' => 'POST',
            'permission_callback' => '__return_true',
            'callback' => function ($request) use ($controller) {
                $status = $controller->handle(
                    (string) $request->get_body(),
                    (string) $request->get_header('x-signature')
                );

                return new \WP_REST_Response(null, $status);
            },
        ]);
    }

    public function runReconcile(): void
    {
        $this->synchronizer()->reconcile();
    }

    public function runGeoDbSync(): void
    {
        $s = $this->settings();
        if ($s->bridgeUrl === '') {
            return;
        }

        $store = new \DBM\Geo\FileGeoDbStore();
        $synchronizer = new \DBM\Geo\GeoDbSynchronizer(
            new \DBM\Geo\WpHttpGeoDbClient(),
            $store,
            new \DBM\Sync\PayloadVerifier(),
            rtrim($s->bridgeUrl, '/') . '/api/v1/geodb',
            $s->siteToken,
            $s->signingSecret,
        );
        $synchronizer->sync();
    }
}
