<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Log;

/**
 * Shared bootstrapper for the public/scripts/*.php entry points.
 *
 * The scripts run without SSH or cron, so this file adds the guard rails a
 * web request needs: it refuses direct access, disables caching, installs a
 * fatal-error handler and boots the console kernel so Artisan works.
 */
$requested = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));

if ($requested !== false && $requested === __FILE__) {
    http_response_code(404);
    exit('Not found');
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');

ini_set('display_errors', '0');

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';

$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

set_time_limit((int) config('scripts.timeout', 120));

register_shutdown_function(static function (): void {
    $error = error_get_last();

    if ($error === null || ! in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        return;
    }

    $message = 'Fatal error: '.$error['message'].' in '.$error['file'].':'.$error['line'];

    try {
        if (function_exists('app') && app()->bound('log')) {
            Log::error('URL script fatal error', $error);
        }
    } catch (Throwable) {
        // Logging is best effort while the process is shutting down.
    }

    if (! headers_sent()) {
        $wantsJson = strtolower((string) ($_GET['format'] ?? '')) === 'json'
            || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');

        http_response_code(500);
        header('Content-Type: '.($wantsJson ? 'application/json' : 'text/plain').'; charset=UTF-8');

        echo $wantsJson
            ? json_encode(['status' => 'error', 'message' => $message])
            : $message;

        return;
    }

    echo "\n[script stopped: ".$message.']';
});

return $app;
