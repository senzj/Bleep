<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run cleanup daily at 2 AM, deleting records older than 30 days
Schedule::command('cleanup:old-records --days=30')
    ->daily()
    ->at('02:00')
    ->withoutOverlapping()
    ->onSuccess(function () {
        Log::info('Old records cleanup completed successfully');
    })
    ->onFailure(function () {
        Log::error('Old records cleanup failed');
    });
