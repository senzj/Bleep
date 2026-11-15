<?php

/**
 * Read-only dashboard for a free shared host.
 *
 * Answers the questions you cannot answer without SSH: is the scheduler
 * still firing, is the last health check green, is GeoIP current, is Reverb
 * reachable, and are queued jobs piling up?
 */

use App\Services\Scripts\ScriptResponse;
use App\Services\Scripts\ScriptRunner;
use App\Services\Scripts\SystemSnapshot;

$app = require __DIR__.'/_bootstrap.php';

$runner = $app->make(ScriptRunner::class);
$response = $app->make(ScriptResponse::class);

$configured = (string) config('scripts.task_key');
$provided = $runner->keyFromRequest();

if ($configured === '' || $provided === null || ! hash_equals($configured, $provided)) {
    $runner->respond([
        'task' => 'status',
        'status' => 'forbidden',
        'http' => 403,
        'message' => 'Unauthorized: invalid or missing key.',
        'started_at' => now()->toIso8601String(),
        'duration' => 0,
        'commands' => [],
    ]);
}

$snapshot = $app->make(SystemSnapshot::class)->data();

$format = strtolower((string) ($_GET['format'] ?? ''));
$accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
$asJson = $format === 'json' || ($format === '' && str_contains($accept, 'application/json'));

header('X-Robots-Tag: noindex, nofollow');
header('Content-Type: '.($asJson ? 'application/json' : 'text/html').'; charset=UTF-8');

echo $asJson ? $response->json($snapshot) : $response->status($snapshot);
