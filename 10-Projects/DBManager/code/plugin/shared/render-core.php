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
        foreach ($payload['values'] ?? [] as $candidate) {
            if (($candidate['key'] ?? null) === $key) {
                $value = $candidate;
                break;
            }
        }
        if ($value === null || in_array($value['state'] ?? 'ok', ['hidden', 'exhausted'], true)) {
            return '';
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
