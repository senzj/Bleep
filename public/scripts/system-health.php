<?php

/**
 * Runs the application health check and stores the report.
 *
 * The report is what `system:health` writes, so the admin Health tab shows
 * the same result the free host sees.
 */

use App\Services\Scripts\ScriptRunner;

$app = require __DIR__.'/_bootstrap.php';

$runner = $app->make(ScriptRunner::class);

$result = $runner->handle(
    task: 'health',
    commands: [
        ['command' => 'system:health', 'arguments' => []],
    ],
    providedKey: $runner->keyFromRequest(),
    configKey: 'scripts.task_key',
    heartbeat: false,
    timeout: 45,
);

$runner->respond($result);
