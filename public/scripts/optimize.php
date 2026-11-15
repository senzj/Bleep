<?php

/**
 * Cache management over HTTP.
 *
 * ?action=cache         rebuild config + view caches
 * ?action=clear         wipe every cache (optimize:clear)
 * ?action=routes        route:cache (fails while routes contain closures)
 * ?action=storage-link  recreate the public/storage symlink
 *
 * Default action is `cache`, which is what a fresh deployment needs most.
 */

use App\Services\Scripts\ScriptRunner;

$app = require __DIR__.'/_bootstrap.php';

$runner = $app->make(ScriptRunner::class);

$action = strtolower((string) ($_GET['action'] ?? 'cache'));

$plan = match ($action) {
    'clear' => [
        'task' => 'optimize-clear',
        'commands' => [
            ['command' => 'optimize:clear', 'arguments' => []],
        ],
    ],
    'routes' => [
        'task' => 'route-cache',
        'commands' => [
            ['command' => 'route:cache', 'arguments' => []],
        ],
    ],
    'storage-link' => [
        'task' => 'storage-link',
        'commands' => [
            ['command' => 'storage:link', 'arguments' => []],
        ],
    ],
    'cache' => [
        'task' => 'optimize-cache',
        'commands' => [
            ['command' => 'config:cache', 'arguments' => []],
            ['command' => 'view:cache', 'arguments' => []],
        ],
    ],
    default => null,
};

if ($plan === null) {
    $runner->respond([
        'task' => 'optimize',
        'status' => 'error',
        'http' => 400,
        'message' => 'Unknown action. Use cache, clear, routes or storage-link.',
        'started_at' => now()->toIso8601String(),
        'duration' => 0,
        'commands' => [],
    ]);
}

$result = $runner->handle(
    task: $plan['task'],
    commands: $plan['commands'],
    providedKey: $runner->keyFromRequest(),
    configKey: 'scripts.task_key',
    heartbeat: false,
    timeout: 120,
);

$runner->respond($result);
