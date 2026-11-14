<?php

return [

    /*
    |--------------------------------------------------------------------------
    | System log inspection
    |--------------------------------------------------------------------------
    |
    | The admin "System Logs" tab tails the application log. Override the path
    | in tests (or unusual deployments) without touching the logging channel.
    |
    */

    'log_path' => env('SYSTEM_LOG_PATH', storage_path('logs/laravel.log')),

    /*
    | Maximum number of bytes read from the end of the log file. The whole
    | file is never loaded, so a multi-gigabyte log cannot exhaust memory.
    */
    'log_tail_bytes' => (int) env('SYSTEM_LOG_TAIL_BYTES', 524288),

];
