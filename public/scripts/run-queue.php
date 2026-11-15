<?php

/**
 * Drains the queue from a URL request.
 *
 * `queue:work` is a long-running process, which a web request cannot be. This
 * runs it with --stop-when-empty and a hard --max-time so the worker exits
 * before the host kills the request, then the next request continues.
 */

use App\Services\Scripts\ScriptRunner;

$app = require __DIR__.'/_bootstrap.php';

$runner = $app->make(ScriptRunner::class);

$maxTime = max(5, (int) config('scripts.queue_max_time', 45));

$result = $runner->handle(
    task: 'queue',
    commands: [
        [
            'command' => 'queue:work',
            'arguments' => [
                '--stop-when-empty' => true,
                '--max-time' => $maxTime,
                '--tries' => 1,
            ],
        ],
    ],
    providedKey: $runner->keyFromRequest(),
    configKey: 'scripts.task_key',
    heartbeat: false,
    timeout: $maxTime + 15,
);

$runner->respond($result);
