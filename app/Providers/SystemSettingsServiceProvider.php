<?php

namespace App\Providers;

use App\Services\System\SystemSettings;
use Illuminate\Support\ServiceProvider;

class SystemSettingsServiceProvider extends ServiceProvider
{
    /**
     * Apply database-backed settings to the framework configuration before
     * the request is handled. Values fall back to environment defaults when
     * the settings table has not been migrated yet.
     */
    public function boot(): void
    {
        try {
            SystemSettings::applyToConfig();
        } catch (\Throwable) {
            // Never let a missing settings table or an unavailable cache
            // store stop the application from booting.
        }
    }
}
