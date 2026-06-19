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

    add_shortcode('dbm_phone_block', function ($atts) {
        $atts = shortcode_atts([
            'key' => '',
            'layout' => 'row', // row або column
            'class' => '',
        ], $atts);

        $key = (string) $atts['key'];
        if ($key === '') {
            return '';
        }

        $simulated = get_option('dbm_simulated_country');
        $country = ! empty($simulated) && $simulated !== 'disabled' ? strtoupper($simulated) : 'WORLD';

        $cache = get_option('dbm_cache');
        $values = is_array($cache) && isset($cache['values']) ? $cache['values'] : [];

        $chosen = null;
        $fallback = null;
        foreach ($values as $candidate) {
            if (($candidate['key'] ?? null) === $key && ($candidate['type'] ?? null) === 'phone') {
                $geo = array_map('strtoupper', $candidate['geo'] ?? []);
                if (in_array('!' . $country, $geo, true)) {
                    continue;
                }
                if (in_array($country, $geo, true)) {
                    $chosen = $candidate;
                    break;
                }
                if (empty($geo) || in_array('WORLD', $geo, true)) {
                    $fallback = $candidate;
                }
            }
        }
        $phone = $chosen ?? $fallback;

        if ($phone === null || in_array($phone['state'] ?? 'ok', ['hidden', 'exhausted'], true)) {
            return '';
        }

        $display = trim((string) ($phone['display_value'] ?? $phone['value'] ?? ''));
        $raw = trim((string) ($phone['value'] ?? ''));
        if ($display === '') {
            return '';
        }

        $messengers = [];
        foreach ($values as $candidate) {
            if (($candidate['type'] ?? null) === 'messenger') {
                $slots = $candidate['linked_slot'] ?? null;
                $match = false;
                if (is_array($slots)) {
                    $match = in_array($key, $slots, true);
                } elseif (is_string($slots) && $slots === $key) {
                    $match = true;
                }

                if ($match) {
                    $geo = array_map('strtoupper', $candidate['geo'] ?? []);
                    if (in_array('!' . $country, $geo, true)) {
                        continue;
                    }
                    if ($geo !== [] && ! in_array('WORLD', $geo, true) && ! in_array($country, $geo, true)) {
                        continue;
                    }
                    if (in_array($candidate['state'] ?? 'ok', ['hidden', 'exhausted'], true)) {
                        continue;
                    }
                    $messengers[] = $candidate;
                }
            }
        }

        $layout = in_array(strtolower($atts['layout']), ['row', 'column'], true) ? strtolower($atts['layout']) : 'row';
        $extraCls = $atts['class'] !== '' ? ' ' . htmlspecialchars($atts['class'], ENT_QUOTES) : '';

        $phoneIcon = '<svg class="dbm-phone-block__phone-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 6px;"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>';
        
        $href = htmlspecialchars('tel:' . preg_replace('/[^+\d]/', '', $raw), ENT_QUOTES);
        $text = htmlspecialchars($display, ENT_QUOTES);

        $html = '';
        static $stylesPrinted = false;
        if (! $stylesPrinted) {
            $stylesPrinted = true;
            $html .= '<style>
            .dbm-phone-block{display:inline-flex;align-items:center;gap:12px;font-family:inherit}
            .dbm-phone-block--column{display:inline-flex;flex-direction:column;align-items:flex-start;gap:6px}
            .dbm-phone-block__phone{display:inline-flex;align-items:center;text-decoration:none;color:inherit;font-weight:bold}
            .dbm-phone-block__phone:hover{opacity:0.85}
            .dbm-phone-block__messengers{display:inline-flex;align-items:center;gap:6px}
            .dbm-phone-block--column .dbm-phone-block__messengers{margin-left:22px}
            .dbm-phone-block__msg-link{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;color:#6b7683;transition:color 0.2s}
            .dbm-phone-block__msg-link:hover{opacity:0.85}
            .dbm-phone-block__msg-link--telegram{color:#229ED9}
            .dbm-phone-block__msg-link--whatsapp{color:#25D366}
            .dbm-phone-block__msg-link--viber{color:#7360f2}
            </style>';
        }

        $html .= '<div class="dbm-phone-block dbm-phone-block--' . $layout . $extraCls . '">';
        $html .= '<a class="dbm-phone-block__phone" href="' . $href . '">' . $phoneIcon . '<span>' . $text . '</span></a>';

        if (count($messengers) > 0) {
            $html .= '<div class="dbm-phone-block__messengers">';
            foreach ($messengers as $msg) {
                $net = strtolower((string) ($msg['network'] ?? 'unknown'));
                $msgUrl = (string) ($msg['url'] ?? $msg['value'] ?? '');
                if ($msgUrl !== '') {
                    $icon = '';
                    if ($net === 'telegram') {
                        $icon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 .587c-6.29 0-11.39 5.09-11.39 11.38 0 6.29 5.1 11.39 11.39 11.39 6.29 0 11.38-5.1 11.38-11.39 0-6.29-5.09-11.38-11.38-11.38zm5.28 7.42c-.17 1.77-.91 6.13-1.28 8.16-.16.86-.47 1.15-.78 1.18-.68.06-1.19-.45-1.85-.88-1.03-.68-1.61-1.1-2.61-1.76-1.16-.76-.41-1.18.25-1.87.17-.18 3.19-2.92 3.25-3.19.01-.03.01-.15-.06-.21-.07-.06-.17-.04-.25-.02-.11.02-1.91 1.21-5.4 3.56-.51.35-.97.52-1.39.51-.46-.01-1.35-.26-2.01-.48-.81-.27-1.46-.42-1.4-.88.03-.24.36-.49.99-.74 3.89-1.69 6.48-2.8 7.78-3.33 3.69-1.5 4.46-1.76 4.96-1.77.11 0 .36.03.52.16.14.11.18.27.2.42-.01.07-.01.19-.02.26z"/></svg>';
                    } elseif ($net === 'whatsapp') {
                        $icon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.513 2.262 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.724-1.455L0 24zm6.59-4.846c1.6.95 3.188 1.449 4.825 1.451 5.436 0 9.86-4.42 9.864-9.864.002-2.637-1.023-5.116-2.887-6.98C16.526 1.897 14.05 .87 11.417.87c-5.442 0-9.869 4.42-9.873 9.863-.001 1.724.455 3.411 1.32 4.908l-.993 3.626 3.71-.973zm11.536-6.973c-.297-.148-1.758-.868-2.03-.967-.273-.099-.471-.148-.669.149-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.568-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>';
                    } elseif ($net === 'viber') {
                        $icon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19.77 15.63c-.88-.29-2.08-.96-2.58-1.46s-.66-.99-.66-1.48c0-.33.09-.64.28-.93.22-.32.61-.71.97-1.07.64-.64.67-.71.67-.93 0-.17-.1-.38-.28-.56a19.7 19.7 0 0 0-2.45-2.45c-.18-.18-.39-.28-.56-.28-.22 0-.29.03-.93.67-.36.36-.75.75-1.07.97-.29.19-.6.28-.93.28-.49 0-.98-.16-1.48-.66s-1.17-1.7-1.46-2.58c-.15-.46-.22-.92-.22-1.37 0-.54.16-1.07.49-1.57.19-.29.41-.6.67-.86.29-.29.35-.38.35-.59 0-.16-.08-.34-.23-.51C10.55.93 9.4.15 8.9.03c-.15-.03-.31-.03-.46-.03-.54 0-1.07.16-1.57.49-.29.19-.6.41-.86.67-.85.85-1.34 1.93-1.46 3.12-.13 1.28.16 2.76.87 4.29.98 2.11 2.5 4.19 4.39 6.08a18.3 18.3 0 0 0 6.08 4.39c1.53.71 3.01 1 4.29.87 1.19-.12 2.27-.61 3.12-1.46.26-.26.48-.57.67-.86.33-.5.49-1.03.49-1.57 0-.15 0-.31-.03-.46-.12-.5-1.13-1.63-1.48-1.97z"/></svg>';
                    } else {
                        $icon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>';
                    }
                    $msgName = htmlspecialchars((string) ($msg['name'] ?? $msg['network'] ?? 'messenger'), ENT_QUOTES);
                    $html .= '<a class="dbm-phone-block__msg-link dbm-phone-block__msg-link--' . htmlspecialchars($net, ENT_QUOTES) . '" href="' . htmlspecialchars($msgUrl, ENT_QUOTES) . '" target="_blank" rel="noopener noreferrer" title="' . $msgName . '">' . $icon . '</a>';
                }
            }
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    });

    add_shortcode('dbm_price', function ($atts) {
        $atts = shortcode_atts([
            'key' => '',
            'show_label' => 'yes', // yes або no
            'class' => '',
            'tag' => 'span', // span, div, strong і т.д.
        ], $atts);

        $key = (string) $atts['key'];
        if ($key === '') {
            return '';
        }

        $simulated = get_option('dbm_simulated_country');
        $country = ! empty($simulated) && $simulated !== 'disabled' ? strtoupper($simulated) : 'WORLD';

        $cache = get_option('dbm_cache');
        $values = is_array($cache) && isset($cache['values']) ? $cache['values'] : [];

        $chosen = null;
        $fallback = null;
        foreach ($values as $candidate) {
            if (($candidate['key'] ?? null) === $key && ($candidate['type'] ?? null) === 'price') {
                $geo = array_map('strtoupper', $candidate['geo'] ?? []);
                if (in_array('!' . $country, $geo, true)) {
                    continue;
                }
                if (in_array($country, $geo, true)) {
                    $chosen = $candidate;
                    break;
                }
                if (empty($geo) || in_array('WORLD', $geo, true)) {
                    $fallback = $candidate;
                }
            }
        }
        $price = $chosen ?? $fallback;

        if ($price === null || in_array($price['state'] ?? 'ok', ['hidden', 'exhausted'], true)) {
            return '';
        }

        $val = trim((string) ($price['value'] ?? ''));
        if ($val === '') {
            return '';
        }

        $label = trim((string) ($price['label'] ?? ''));
        $showLabel = in_array(strtolower($atts['show_label']), ['yes', '1', 'true'], true);

        $display = htmlspecialchars($val, ENT_QUOTES);
        if ($showLabel && $label !== '') {
            $display .= ' ' . htmlspecialchars($label, ENT_QUOTES);
        }

        $tag = in_array(strtolower($atts['tag']), ['span', 'div', 'strong', 'p', 'b'], true) ? strtolower($atts['tag']) : 'span';
        $class = $atts['class'] !== '' ? ' class="' . htmlspecialchars($atts['class'], ENT_QUOTES) . '"' : '';

        return '<' . $tag . $class . '>' . $display . '</' . $tag . '>';
    });
});
