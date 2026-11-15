<?php

return [

    /*
    |--------------------------------------------------------------------------
    | URL task scripts
    |--------------------------------------------------------------------------
    |
    | Free shared hosts usually have no SSH and often no cron. The scripts in
    | public/scripts let you run Artisan tasks over HTTPS with a secret key.
    | Every script below reads its key from this file (not env()), so the
    | scripts keep working after `php artisan config:cache`.
    |
    | Set SCRIPT_SECRET_KEY to a long random string and it unlocks every
    | script. The older CACHE_CLEAR_SECRET and MIGRATION_SECRET_KEY values are
    | still honoured when SCRIPT_SECRET_KEY is left blank, so existing
    | bookmarks keep working until you rotate them.
    |
    */

    'task_key' => env('SCRIPT_SECRET_KEY', env('CACHE_CLEAR_SECRET')),

    'migration_key' => env('SCRIPT_SECRET_KEY', env('MIGRATION_SECRET_KEY', env('CACHE_CLEAR_SECRET'))),

    'cache_key' => env('SCRIPT_SECRET_KEY', env('CACHE_CLEAR_SECRET')),

    /*
    | Hard ceiling for a single script request, in seconds. Shared hosts may
    | enforce a lower max_execution_time; the scripts still fail cleanly.
    */
    'timeout' => (int) env('SCRIPT_TIMEOUT', 120),

    /*
    | How long a single queue-drain request may work. Keep this below the
    | host's request timeout so the worker stops before the request dies.
    */
    'queue_max_time' => (int) env('SCRIPT_QUEUE_MAX_TIME', 45),

    /*
    | A URL scheduler is only healthy while something keeps calling it. The
    | heartbeat is written by public/scripts/run-scheduler.php and checked by
    | `system:health` and the admin Schedule tab.
    */
    'heartbeat_stale_minutes' => (int) env('SCRIPT_HEARTBEAT_STALE_MINUTES', 10),

    'runs_path' => storage_path('app/system/script-runs.json'),

    'heartbeat_path' => storage_path('app/system/scheduler-heartbeat.json'),

    'lock_path' => storage_path('app/system/script-locks'),

];
