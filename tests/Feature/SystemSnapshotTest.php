<?php

use App\Services\Scripts\ScriptResponse;
use App\Services\Scripts\ScriptRunLog;
use App\Services\Scripts\SystemSnapshot;
use App\Services\System\HealthReport;

function snapshotTempPath(string $suffix): string
{
    return sys_get_temp_dir().DIRECTORY_SEPARATOR.'bleep-snapshot-'.uniqid().'-'.$suffix;
}

function snapshotConfig(): array
{
    return [
        'scripts.task_key' => 'snapshot-key',
        'scripts.runs_path' => snapshotTempPath('runs.json'),
        'scripts.heartbeat_path' => snapshotTempPath('heartbeat.json'),
        'scripts.heartbeat_stale_minutes' => 10,
        'system.health_path' => snapshotTempPath('health.json'),
        'geoip.database' => snapshotTempPath('missing.mmdb'),
        'queue.default' => 'sync',
        'broadcasting.default' => 'reverb',
    ];
}

it('describes the application, scheduler and integrations', function () {
    config(snapshotConfig());

    $data = app(SystemSnapshot::class)->data();

    expect($data['app']['environment'])->toBe('testing')
        ->and($data['app']['laravel'])->not->toBeEmpty()
        ->and($data['scheduler']['at'])->toBeNull()
        ->and($data['scheduler']['healthy'])->toBeFalse()
        ->and($data['scheduler']['stale_after_minutes'])->toBe(10)
        ->and($data['health']['status'])->toBeNull()
        ->and($data['geoip']['exists'])->toBeFalse()
        ->and($data['reverb']['configured'])->toBeTrue()
        ->and($data['reverb']['reachable'])->toBeNull()
        ->and($data['queue']['connection'])->toBe('sync')
        ->and($data['queue']['pending'])->toBeNull()
        ->and($data['scripts'])->toBe([])
        ->and($data['scheduled_tasks'])->toBe([]);
});

it('reports a fresh scheduler heartbeat as healthy', function () {
    config(snapshotConfig());

    app(ScriptRunLog::class)->heartbeat([
        'status' => 'ok',
        'message' => 'Task completed successfully.',
        'duration' => 2.5,
    ]);

    $scheduler = app(SystemSnapshot::class)->data()['scheduler'];

    expect($scheduler['healthy'])->toBeTrue()
        ->and($scheduler['age_minutes'])->toBe(0)
        ->and($scheduler['status'])->toBe('ok')
        ->and($scheduler['message'])->toBe('Task completed successfully.');
});

it('renders the status page with escaped content', function () {
    config(snapshotConfig());

    $health = app(HealthReport::class);
    $health->save([
        'checked_at' => now()->toIso8601String(),
        'status' => 'warning',
        'checks' => [
            [
                'key' => 'database',
                'label' => 'Database',
                'status' => 'warning',
                'detail' => '<script>alert(1)</script>',
            ],
        ],
    ]);

    app(ScriptRunLog::class)->heartbeat([
        'status' => 'ok',
        'message' => 'Task completed successfully.',
        'duration' => 1,
    ]);

    $html = app(ScriptResponse::class)->status(app(SystemSnapshot::class)->data());

    expect($html)->toContain('System status')
        ->and($html)->toContain('Scheduler trigger')
        ->and($html)->toContain('Latest health check')
        ->and($html)->toContain('GeoIP database')
        ->and($html)->toContain('Realtime (Reverb)')
        ->and($html)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->and($html)->not->toContain('<script>alert(1)</script>');
});

it('renders a readable result page for a task', function () {
    $html = app(ScriptResponse::class)->result([
        'task' => 'cache-clear',
        'status' => 'ok',
        'http' => 200,
        'message' => 'Task completed successfully.',
        'started_at' => now()->toIso8601String(),
        'duration' => 0.42,
        'commands' => [
            ['command' => 'cache:clear', 'exit_code' => 0, 'output' => "Cache cleared.\n"],
        ],
    ]);

    expect($html)->toContain('cache-clear')
        ->and($html)->toContain('Task completed successfully.')
        ->and($html)->toContain('$ php artisan cache:clear')
        ->and($html)->toContain('Cache cleared.');
});

it('renders a forbidden result with the key hint', function () {
    $html = app(ScriptResponse::class)->result([
        'task' => 'migrate',
        'status' => 'forbidden',
        'http' => 403,
        'message' => 'Unauthorized: invalid or missing key.',
        'started_at' => now()->toIso8601String(),
        'duration' => 0,
        'commands' => [],
    ]);

    expect($html)->toContain('forbidden')
        ->and($html)->toContain('?key=')
        ->and($html)->toContain('X-Script-Key');
});
