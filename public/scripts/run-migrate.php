<?php

/**
 * Runs `php artisan migrate --force` over HTTPS.
 *
 * The secret key is the gate, so this works in production too: after a
 * deployment there is no SSH, and the schema still has to move forward.
 */

use App\Services\Scripts\ScriptRunner;

$app = require __DIR__.'/_bootstrap.php';

$runner = $app->make(ScriptRunner::class);

$result = $runner->handle(
    task: 'migrate',
    commands: [
        ['command' => 'migrate', 'arguments' => ['--force' => true]],
    ],
    providedKey: $runner->keyFromRequest(),
    configKey: 'scripts.migration_key',
    heartbeat: false,
    timeout: 300,
);

$runner->respond($result);
