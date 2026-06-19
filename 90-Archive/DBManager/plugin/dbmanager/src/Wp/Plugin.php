<?php

namespace DBM\Wp;

use DBM\Admin\AdminPages;
use DBM\Config\Settings;
use DBM\Geo\GeoDetector;
use DBM\Geo\GeoSimulation;
use DBM\Geo\MaxMindCountryLookup;

class Plugin
{
    private function settings(): Settings
    {
        $opts = get_option('dbm_settings');

        return Settings::fromArray(is_array($opts) ? $opts : []);
    }

    public function register(): void
    {
        $settings = $this->settings();

        register_activation_hook(
            dirname(__DIR__, 2) . '/dbmanager.php',
            function (): void {
                $muDir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
                if (! is_dir($muDir)) {
                    wp_mkdir_p($muDir);
                }

                $sourceFallback = dirname(__DIR__, 2) . '/mu/dbmanager-fallback.php';
                $sourceRender = dirname(__DIR__, 2) . '/../shared/render-core.php';

                if (is_file($sourceFallback)) {
                    copy($sourceFallback, $muDir . '/dbmanager-fallback.php');
                }
                if (is_file($sourceRender)) {
                    copy($sourceRender, $muDir . '/render-core.php');
                }
            }
        );

        $simulation = new GeoSimulation();
        $simulatedCountry = $simulation->getSimulatedCountry();

        if ($simulatedCountry !== null) {
            $country = $simulatedCountry;
        } else {
            $detector = new GeoDetector(
                new MaxMindCountryLookup((string) (get_option('dbm_geodb_path') ?: ''))
            );
            $country = $detector->detect(
                ['CF-IPCountry' => $_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''],
                (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''))
            );
        }

        (new ShortcodeController($settings, $country))->register();
        (new FilterController())->register();
        (new RestController($settings))->register();

        if (is_admin()) {
            (new AdminPages($settings, $simulation))->register();
        }
    }
}
