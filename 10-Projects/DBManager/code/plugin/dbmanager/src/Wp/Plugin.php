<?php

namespace DBM\Wp;

use DBM\Admin\AdminPages;
use DBM\Cache\WpOptionCacheStore;
use DBM\Config\Settings;
use DBM\Geo\GeoDetector;
use DBM\Geo\GeoSimulation;
use DBM\Geo\MaxMindCountryLookup;
use DBM\Http\WpHttpDataClient;
use DBM\Sync\PayloadVerifier;
use DBM\Sync\Synchronizer;

class Plugin
{
    private function settings(): Settings
    {
        $opts = get_option('dbm_settings');

        return Settings::fromArray(is_array($opts) ? $opts : []);
    }

    private function synchronizer(Settings $s): Synchronizer
    {
        return new Synchronizer(
            new WpHttpDataClient(),
            new WpOptionCacheStore(),
            new PayloadVerifier(),
            rtrim($s->bridgeUrl, '/') . '/api/v1/data',
            $s->siteToken,
            $s->signingSecret,
        );
    }

    public function register(): void
    {
        $settings = $this->settings();
        $sync = $this->synchronizer($settings);

        // 1. Детекція країни (з урахуванням симуляції)
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

        // 2. Ініціалізація та реєстрація підкомпонентів (немонолітна структура)
        (new ShortcodeController($settings, $country))->register();
        (new FilterController())->register();
        (new CronController($settings, $sync))->register();

        (new RestController($settings, function () use ($sync) {
            $sync->sync();
        }))->register();

        if (is_admin()) {
            (new AdminPages($settings, $simulation))->register();
        }
    }
}
