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

    $cache = get_option('dbm_cache');
    $cache = is_array($cache) ? $cache : ['values' => []];
    $opts = get_option('dbm_settings');
    $shortcode = is_array($opts) && ! empty($opts['shortcode']) ? $opts['shortcode'] : 'dbm';
    $class = is_array($opts) ? (string) ($opts['css_class'] ?? '') : '';

    function dbm_get(string $key, array $opts = []): string
    {
        $cache = get_option('dbm_cache');
        $cache = is_array($cache) ? $cache : ['values' => []];
        $settings = get_option('dbm_settings');
        $opts['class'] = $opts['class'] ?? (is_array($settings) ? (string) ($settings['css_class'] ?? '') : '');

        return dbm_render_from_cache($cache, $key, $opts);
    }

    add_shortcode($shortcode, function ($atts) {
        $atts = shortcode_atts(['key' => '', 'format' => ''], $atts);

        return dbm_get((string) $atts['key'], ['format' => (string) $atts['format']]);
    });
});
