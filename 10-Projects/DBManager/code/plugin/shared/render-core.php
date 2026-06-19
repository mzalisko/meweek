<?php

// Спільне ядро рендеру: одне джерело правди для основного плагіна і mu-фолбека.
// Без жодних викликів WP — лише чисте перетворення кеш-payload у рядок.

if (! function_exists('dbm_render_from_cache')) {
    /**
     * @param array $payload розпакований кеш (site/version/values)
     * @param array $opts    ['format' => 'tel'|'link'|'', 'class' => string]
     */
    function dbm_render_from_cache(array $payload, string $key, array $opts = []): string
    {
        $value = null;
        $fallback = null;
        $country = strtoupper($opts['country'] ?? 'WORLD');

        foreach ($payload['values'] ?? [] as $candidate) {
            if (($candidate['key'] ?? null) === $key) {
                $geo = array_map('strtoupper', $candidate['geo'] ?? []);
                
                // Якщо є пряме виключення цієї країни (наприклад, !RU)
                if (in_array('!' . $country, $geo, true)) {
                    continue;
                }
                
                if (in_array($country, $geo, true)) {
                    $value = $candidate;
                    break;
                }
                if (empty($geo) || in_array('WORLD', $geo, true)) {
                    $fallback = $candidate;
                }
            }
        }

        if ($value === null) {
            $value = $fallback;
        }

        if ($value === null || in_array($value['state'] ?? 'ok', ['hidden', 'exhausted'], true)) {
            return '';
        }
        
        $geo = array_map('strtoupper', $value['geo'] ?? []);
        if (in_array('!' . $country, $geo, true)) {
            return ''; // виключено для цієї країни
        }
        
        if ($geo !== [] && ! in_array('WORLD', $geo, true) && ! in_array($country, $geo, true)) {
            return ''; // не для цієї країни
        }
        $raw = (string) ($value['value'] ?? '');
        $display = (string) ($value['display_value'] ?? $raw);
        if ($raw === '') {
            return '';
        }

        $cls = ($opts['class'] ?? '') !== ''
            ? ' class="' . htmlspecialchars((string) $opts['class'], ENT_QUOTES) . '"'
            : '';

        $text = htmlspecialchars($display, ENT_QUOTES);
        $format = $opts['format'] ?? '';

        if ($format === 'tel') {
            $href = htmlspecialchars('tel:' . preg_replace('/[^+\d]/', '', $raw), ENT_QUOTES);

            return '<a' . $cls . ' href="' . $href . '">' . $text . '</a>';
        }
        if ($format === 'link' && ! empty($value['url'])) {
            return '<a' . $cls . ' href="' . htmlspecialchars((string) $value['url'], ENT_QUOTES) . '">' . $text . '</a>';
        }

        return '<span' . $cls . '>' . $text . '</span>';
    }
}
