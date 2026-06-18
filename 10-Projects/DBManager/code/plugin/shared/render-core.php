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
        $country = $opts['country'] ?? 'WORLD';

        foreach ($payload['values'] ?? [] as $candidate) {
            if (($candidate['key'] ?? null) === $key) {
                $geo = $candidate['geo'] ?? [];
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
        $geo = $value['geo'] ?? [];
        if ($geo !== [] && ! in_array('WORLD', $geo, true) && ! in_array($country, $geo, true)) {
            return ''; // не для цієї країни
        }
        $raw = (string) ($value['value'] ?? '');
        if ($raw === '') {
            return '';
        }

        $cls = ($opts['class'] ?? '') !== ''
            ? ' class="' . htmlspecialchars((string) $opts['class'], ENT_QUOTES) . '"'
            : '';

        $text = htmlspecialchars($raw, ENT_QUOTES);
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
