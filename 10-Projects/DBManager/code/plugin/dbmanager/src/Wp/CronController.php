<?php

namespace DBM\Wp;

class CronController
{
    public function register(): void
    {
        // Listener-only plugin: no outgoing scheduled sync jobs.
    }

    public function activateCron(): void
    {
        $this->deactivateCron();
    }

    public function deactivateCron(): void
    {
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook('dbm_daily_reconcile');
            wp_clear_scheduled_hook('dbm_geodb_sync');
        }
    }

    public function runReconcile(): void
    {
        // No-op by design.
    }

    public function runGeoDbSync(): void
    {
        // No-op by design.
    }
}
