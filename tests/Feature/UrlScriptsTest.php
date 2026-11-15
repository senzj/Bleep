<?php

/**
 * Every public script is a thin wrapper: bootstrap, run through the shared
 * runner, respond. These assertions keep a future script from quietly
 * bypassing the key check, the lock, or the error handling.
 */
dataset('url scripts', [
    'cache-clear' => 'cache-clear.php',
    'optimize' => 'optimize.php',
    'run-migrate' => 'run-migrate.php',
    'run-queue' => 'run-queue.php',
    'run-scheduler' => 'run-scheduler.php',
    'system-health' => 'system-health.php',
    'system-status' => 'system-status.php',
    'update-geoip' => 'update-geoip.php',
]);

it('boots through the shared bootstrap and runner', function (string $file) {
    $source = (string) file_get_contents(public_path('scripts/'.$file));

    expect($source)->toContain("require __DIR__.'/_bootstrap.php'")
        ->and($source)->toContain('ScriptRunner::class')
        ->and($source)->not->toContain('Artisan::call')
        ->and($source)->not->toContain('env(');
})->with('url scripts');

it('protects the bootstrap from direct requests', function () {
    $source = (string) file_get_contents(public_path('scripts/_bootstrap.php'));

    expect($source)->toContain('SCRIPT_FILENAME')
        ->and($source)->toContain('http_response_code(404)')
        ->and($source)->toContain('register_shutdown_function')
        ->and($source)->toContain("ini_set('display_errors', '0')");
});

it('documents every optimize action', function () {
    $source = (string) file_get_contents(public_path('scripts/optimize.php'));

    expect($source)->toContain("'clear'")
        ->and($source)->toContain("'routes'")
        ->and($source)->toContain("'storage-link'")
        ->and($source)->toContain("'cache'");
});
