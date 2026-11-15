<?php

return [

    /*
    |--------------------------------------------------------------------------
    | IP geolocation
    |--------------------------------------------------------------------------
    |
    | Bleep resolves the location of public IP addresses from a local DB-IP
    | City Lite database (https://db-ip.com, CC BY 4.0). The stable file name
    | below is the one the monthly `geoip:update` command refreshes. When it is
    | missing, the newest *.mmdb in the same directory is used instead, so a
    | manually uploaded dated database keeps working.
    |
    */

    'enabled' => (bool) env('GEOIP_ENABLED', true),

    'database' => env('GEOIP_DATABASE', storage_path('app/geoip/dbip-city-lite.mmdb')),

    'download_url' => env('GEOIP_DOWNLOAD_URL', 'https://download.db-ip.com/free/dbip-city-lite-%s.mmdb.gz'),

    /*
    | A database older than this is treated as stale by the health check. DB-IP
    | publishes a fresh build on the first of every month.
    */
    'stale_after_days' => (int) env('GEOIP_STALE_AFTER_DAYS', 40),

];
