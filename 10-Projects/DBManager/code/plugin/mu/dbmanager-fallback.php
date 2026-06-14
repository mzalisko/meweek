<?php
/**
 * Plugin Name: DBManager Fallback
 * Description: Аварійний рендер значень із кешу, якщо основний плагін вимкнено/видалено.
 */

if (! defined('ABSPATH')) {
    return;
}

// Самодостатнє ядро рендеру (копія render-core.php кладеться поряд установником).
require_once __DIR__ . '/render-core.php';

add_action('init', function (): void {
    // Якщо основний плагін активний — він сам реєструє шорткод; mu мовчить.
    if (function_exists('dbm_get')) {
        return;
    }

    $opts = get_option('dbm_settings');
    $shortcode = is_array($opts) && ! empty($opts['shortcode']) ? $opts['shortcode'] : 'dbm';

    function dbm_get(string $key, array $opts = []): string
    {
        $cache = get_option('dbm_cache');
        $cache = is_array($cache) ? $cache : ['values' => []];
        $settings = get_option('dbm_settings');
        $opts['class'] = $opts['class'] ?? (is_array($settings) ? (string) ($settings['css_class'] ?? '') : '');

        return dbm_render_from_cache($cache, $key, $opts);
    }

    // Detect country once per request: CF-IPCountry → MaxMind lookup → WORLD.
    // The mu-fallback uses the same GeoDetector logic via the plugin autoloader if available,
    // or falls back to WORLD when the autoloader is absent (e.g. plugin fully removed).
    $country = 'WORLD';
    if (class_exists('\DBM\Geo\GeoDetector') && class_exists('\DBM\Geo\MaxMindCountryLookup')) {
        $detector = new \DBM\Geo\GeoDetector(
            new \DBM\Geo\MaxMindCountryLookup((string) (get_option('dbm_geodb_path') ?: ''))
        );
        $country = $detector->detect(
            ['CF-IPCountry' => $_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''],
            (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''))
        );
    }

    add_shortcode($shortcode, function ($atts) use ($country) {
        $atts = shortcode_atts(['key' => '', 'format' => ''], $atts);

        return dbm_get((string) $atts['key'], ['format' => (string) $atts['format'], 'country' => $country]);
    });
});
