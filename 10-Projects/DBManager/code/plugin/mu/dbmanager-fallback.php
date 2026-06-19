<?php
/**
 * Plugin Name: DBManager Fallback
 * Description: Аварийный рендер значений из кеша, если основной плагин отключен/удален.
 */

if (! defined('ABSPATH')) {
    return;
}

// Самодостаточное ядро рендера (копия render-core.php кладется рядом установщиком).
require_once __DIR__ . '/render-core.php';

add_action('init', function (): void {
    // Главный плагин присутствует → он сам регистрирует country-aware dbm_get и шорткод; mu молчит.
    // Проверяем именно КЛАСС плагина, а не function_exists('dbm_get'): mu-плагины загружаются
    // раньше обычных, поэтому при старом guard mu определил бы dbm_get первым и затенил бы
    // более богатый dbm_get плагина (#2 финального ревью).
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

    // Аварийный фолбек активен только когда плагин удален — гео-детект требует классов
    // плагина, которых уже нет, поэтому показываем значения со скоупом «Мир» (сознательная деградация)
    // или используем сохраненную страну симуляции.
    add_shortcode($shortcode, function ($atts) {
        $atts = shortcode_atts(['key' => '', 'format' => ''], $atts);
        $simulated = get_option('dbm_simulated_country');
        $country = ! empty($simulated) && $simulated !== 'disabled' ? strtoupper($simulated) : 'WORLD';

        return dbm_get((string) $atts['key'], ['format' => (string) $atts['format'], 'country' => $country]);
    });
});
