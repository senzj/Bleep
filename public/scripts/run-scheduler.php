<?php

/**
 * Runs Laravel's scheduler over HTTP.
 *
 * Free shared hosts usually have no cron and no SSH, so point a one-minute
 * URL pinger (cron-job.org, UptimeRobot, etc.) at this file. Each request runs
 * every task that is due right now and writes the scheduler heartbeat.
 */

use App\Services\Scripts\ScriptRunner;

$app = require __DIR__.'/_bootstrap.php';

$runner = $app->make(ScriptRunner::class);

$result = $runner->handle(
    task: 'scheduler',
    commands: [
        ['command' => 'schedule:run', 'arguments' => []],
    ],
    providedKey: $runner->keyFromRequest(),
    configKey: 'scripts.task_key',
    heartbeat: true,
    timeout: 55,
);

$runner->respond($result);
