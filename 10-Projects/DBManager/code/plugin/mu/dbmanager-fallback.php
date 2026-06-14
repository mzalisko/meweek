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
    // Головний плагін присутній → він сам реєструє country-aware dbm_get і шорткод; mu мовчить.
    // Перевіряємо саме КЛАС плагіна, а не function_exists('dbm_get'): mu-плагіни вантажаться
    // раніше за звичайні, тож за старим guard mu визначив би dbm_get першим і затінив би
    // багатший dbm_get плагіна (#2 фінального рев'ю).
    if (class_exists('DBM\\Wp\\Plugin')) {
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

    // Аварійний фолбек активний лише коли плагін видалено — гео-детект потребує класів
    // плагіна, яких уже немає, тож показуємо значення зі скоупом «Світ» (свідома деградація).
    add_shortcode($shortcode, function ($atts) {
        $atts = shortcode_atts(['key' => '', 'format' => ''], $atts);

        return dbm_get((string) $atts['key'], ['format' => (string) $atts['format'], 'country' => 'WORLD']);
    });
});
