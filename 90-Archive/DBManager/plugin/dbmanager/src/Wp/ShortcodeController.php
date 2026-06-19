<?php

namespace DBM\Wp;

use DBM\Config\Settings;

class ShortcodeController
{
    public function __construct(
        private Settings $settings,
        private string $country
    ) {}

    public function register(): void
    {
        if (! function_exists('dbm_get')) {
            require_once dirname(__DIR__, 2) . '/../shared/render-core.php';

            eval('function dbm_get(string $key, array $opts = []): string {
                $cache = get_option("dbm_cache"); $cache = is_array($cache) ? $cache : ["values" => []];
                $st = get_option("dbm_settings");
                $opts["class"] = $opts["class"] ?? (is_array($st) ? (string)($st["css_class"] ?? "") : "");
                $opts["country"] = $opts["country"] ?? ($GLOBALS["dbm_country"] ?? "WORLD");
                return dbm_render_from_cache($cache, $key, $opts);
            }');
        }

        $country = $this->country;
        $GLOBALS['dbm_country'] = $country;

        add_shortcode($this->settings->shortcode, function ($atts) use ($country) {
            $atts = shortcode_atts(['key' => '', 'format' => ''], $atts);

            return dbm_get((string) $atts['key'], ['format' => (string) $atts['format'], 'country' => $country]);
        });
    }
}
