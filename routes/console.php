<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run cleanup daily at 2 AM, deleting records older than 30 days
Schedule::command('cleanup:old-records --days=30')
    ->description('Clean up old records')
    ->daily()
    ->at('02:00')
    ->withoutOverlapping()
    ->onSuccess(function () {
        Log::info('Old records cleanup completed successfully');
    })
    ->onFailure(function () {
        Log::error('Old records cleanup failed');
    });

// Archive expired bulletin notes and drop old archived ones. The board also
// performs a throttled sweep on first visit, so hosts without cron still work.
Schedule::command('bulletin:prune')
    ->description('Prune expired bulletin notes')
    ->hourly()
    ->withoutOverlapping();

// DB-IP publishes a fresh City Lite build on the first of every month. The
// command skips the download when the installed database is already current,
// and falls back to the previous month if this month's build is not ready yet.
Schedule::command('geoip:update --if-stale')
    ->description('Refresh the GeoIP database')
    ->monthlyOn(3, '04:00')
    ->withoutOverlapping()
    ->onSuccess(function () {
        Log::info('GeoIP database refresh completed successfully');
    })
    ->onFailure(function () {
        Log::error('GeoIP database refresh failed');
    });

// Functional health check (database, cache, storage, Reverb, GeoIP freshness,
// log writing). The result is shown on the admin System > Schedule tab.
Schedule::command('system:health')
    ->description('Run the system health check')
    ->monthlyOn(1, '03:00')
    ->withoutOverlapping()
    ->onSuccess(function () {
        Log::info('System health check completed');
    })
    ->onFailure(function () {
        Log::error('System health check reported errors');
    });
