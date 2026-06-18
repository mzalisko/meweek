<?php

namespace DBM\Wp;

use DBM\Config\Settings;
use DBM\Sync\Synchronizer;

class CronController
{
    public function __construct(
        private Settings $settings,
        private Synchronizer $synchronizer
    ) {}

    public function register(): void
    {
        add_action('dbm_daily_reconcile', [$this, 'runReconcile']);
        add_action('dbm_geodb_sync', [$this, 'runGeoDbSync']);
        add_filter('cron_schedules', fn ($s) => $s);

        register_activation_hook(
            dirname(__DIR__, 2) . '/dbmanager.php',
            [$this, 'activateCron']
        );

        register_deactivation_hook(
            dirname(__DIR__, 2) . '/dbmanager.php',
            [$this, 'deactivateCron']
        );
    }

    public function activateCron(): void
    {
        if (! wp_next_scheduled('dbm_daily_reconcile')) {
            wp_schedule_event(time() + 3600, 'daily', 'dbm_daily_reconcile');
        }
        if (! wp_next_scheduled('dbm_geodb_sync')) {
            wp_schedule_event(time() + 3600, 'weekly', 'dbm_geodb_sync');
        }
    }

    public function deactivateCron(): void
    {
        wp_clear_scheduled_hook('dbm_daily_reconcile');
        wp_clear_scheduled_hook('dbm_geodb_sync');
    }

    public function runReconcile(): void
    {
        $this->synchronizer->reconcile();
    }

    public function runGeoDbSync(): void
    {
        if ($this->settings->bridgeUrl === '') {
            return;
        }

        $store = new \DBM\Geo\FileGeoDbStore();
        $synchronizer = new \DBM\Geo\GeoDbSynchronizer(
            new \DBM\Geo\WpHttpGeoDbClient(),
            $store,
            new \DBM\Sync\PayloadVerifier(),
            rtrim($this->settings->bridgeUrl, '/') . '/api/v1/geodb',
            $this->settings->siteToken,
            $this->settings->signingSecret,
        );
        $synchronizer->sync();
    }
}
