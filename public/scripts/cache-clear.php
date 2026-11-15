<?php

/**
 * Clears compiled views, application cache, config and route caches.
 *
 * Use this after changing .env or a Blade file when you cannot run Artisan
 * from a terminal.
 */

use App\Services\Scripts\ScriptRunner;

$app = require __DIR__.'/_bootstrap.php';

$runner = $app->make(ScriptRunner::class);

$result = $runner->handle(
    task: 'cache-clear',
    commands: [
        ['command' => 'view:clear', 'arguments' => []],
        ['command' => 'cache:clear', 'arguments' => []],
        ['command' => 'config:clear', 'arguments' => []],
        ['command' => 'route:clear', 'arguments' => []],
    ],
    providedKey: $runner->keyFromRequest(),
    configKey: 'scripts.cache_key',
    heartbeat: false,
    timeout: 60,
);

$runner->respond($result);
