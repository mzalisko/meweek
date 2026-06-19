<?php

// Совместное ядро рендера: один источник правды для основного плагина и mu-фолбека.
// Без каких-либо вызовов WP — только чистое преобразование кеш-payload в строку.

if (! function_exists('dbm_select_price_candidate')) {
    function dbm_select_price_candidate(array $candidates, string $country): ?array
    {
        $country = strtoupper($country);
        
        // 1. Пошук прямого співпадіння для конкретної країни (наприклад, UA, RU, BY, RO тощо)
        foreach ($candidates as $candidate) {
            $geo = array_map('strtoupper', $candidate['geo'] ?? []);
            if (in_array('!' . $country, $geo, true)) {
                continue;
            }
            if (in_array($country, $geo, true)) {
                return $candidate;
            }
        }
        
        // 2. Якщо відвідувач з Росії або Білорусі, ми НЕ показуємо WORLD ціну (заборонено)
        if ($country === 'RU' || $country === 'BY') {
            return null;
        }
        
        // 3. Для всіх інших країн (не Україна, не Росія, не Білорусь) шукаємо WORLD ціну
        foreach ($candidates as $candidate) {
            $geo = array_map('strtoupper', $candidate['geo'] ?? []);
            if (in_array('!' . $country, $geo, true)) {
                continue;
            }
            if (empty($geo) || in_array('WORLD', $geo, true)) {
                return $candidate;
            }
        }
        
        return null;
    }
}

if (! function_exists('dbm_resolve_price_candidate')) {
    function dbm_resolve_price_candidate(array $values, string $key, string $country): ?array
    {
        $separators = [' ', '_'];
        foreach ($separators as $sep) {
            if (str_contains($key, $sep)) {
                $lastPos = strrpos($key, $sep);
                $baseKey = substr($key, 0, $lastPos);
                $suffix = strtolower(substr($key, $lastPos + 1));

                $candidates = [];
                foreach ($values as $candidate) {
                    if (($candidate['key'] ?? null) === $baseKey && ($candidate['type'] ?? null) === 'price') {
                        $candidates[] = $candidate;
                    }
                }

                if (! empty($candidates)) {
                    // 1. Match label case-insensitive
                    foreach ($candidates as $candidate) {
                        $label = strtolower(trim((string) ($candidate['label'] ?? '')));
                        if ($label !== '' && $label === $suffix) {
                            return $candidate;
                        }
                    }

                    // 2. Match geo code or WORLD
                    $suffixUpper = strtoupper($suffix);
                    foreach ($candidates as $candidate) {
                        $geo = array_map('strtoupper', $candidate['geo'] ?? []);
                        if (in_array($suffixUpper, $geo, true) || ($suffixUpper === 'WORLD' && empty($geo))) {
                            return $candidate;
                        }
                    }
                }
            }
        }

        // 3. Match exact key
        $candidates = [];
        foreach ($values as $candidate) {
            if (($candidate['key'] ?? null) === $key && ($candidate['type'] ?? null) === 'price') {
                $candidates[] = $candidate;
            }
        }

        if (! empty($candidates)) {
            return dbm_select_price_candidate($candidates, $country);
        }

        return null;
    }
}

if (! function_exists('dbm_render_from_cache')) {
    /**
     * @param array $payload распакованный кеш (site/version/values)
     * @param array $opts    ['format' => 'tel'|'link'|'', 'class' => string]
     */
    function dbm_render_from_cache(array $payload, string $key, array $opts = []): string
    {
        $value = null;
        $country = strtoupper($opts['country'] ?? 'WORLD');
        $isSuffixMatch = false;

        // Check price candidates first (handles suffix and country selection)
        $priceCandidate = dbm_resolve_price_candidate($payload['values'] ?? [], $key, $country);
        if ($priceCandidate !== null) {
            $value = $priceCandidate;
            $isSuffixMatch = ($priceCandidate['key'] ?? '') !== $key;
        } else {
            // Standard non-price lookup
            $fallback = null;
            foreach ($payload['values'] ?? [] as $candidate) {
                if (($candidate['key'] ?? null) === $key) {
                    $geo = array_map('strtoupper', $candidate['geo'] ?? []);
                    
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
        }

        if ($value === null || in_array($value['state'] ?? 'ok', ['hidden', 'exhausted'], true)) {
            return '';
        }

        // Only enforce country constraints for non-suffix matches
        if (! $isSuffixMatch) {
            $geo = array_map('strtoupper', $value['geo'] ?? []);
            if (in_array('!' . $country, $geo, true)) {
                return ''; // excluded
            }
            
            if ($geo !== [] && ! in_array('WORLD', $geo, true) && ! in_array($country, $geo, true)) {
                return ''; // not for this country
            }
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
