<?php

/**
 * Refreshes the MaxMind/DB-IP city database.
 *
 * The download is ~120 MB, so this is safe to trigger weekly. `--if-stale`
 * makes an accidental extra request cheap: it exits immediately when the
 * database is already current.
 */

use App\Services\Scripts\ScriptRunner;

$app = require __DIR__.'/_bootstrap.php';

$runner = $app->make(ScriptRunner::class);

$result = $runner->handle(
    task: 'geoip',
    commands: [
        [
            'command' => 'geoip:update',
            'arguments' => ['--if-stale' => true],
        ],
    ],
    providedKey: $runner->keyFromRequest(),
    configKey: 'scripts.task_key',
    heartbeat: false,
    timeout: 300,
);

$runner->respond($result);
