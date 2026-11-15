<?php

use App\Services\Scripts\ScriptRunLog;
use App\Services\Scripts\ScriptRunner;

function scriptTempPath(string $suffix): string
{
    return sys_get_temp_dir().DIRECTORY_SEPARATOR.'bleep-script-'.uniqid().'-'.$suffix;
}

function scriptRunner(): ScriptRunner
{
    return app(ScriptRunner::class);
}

function scriptConfig(): array
{
    return [
        'scripts.task_key' => 'test-script-key',
        'scripts.runs_path' => scriptTempPath('runs.json'),
        'scripts.heartbeat_path' => scriptTempPath('heartbeat.json'),
        'scripts.lock_path' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'bleep-script-locks-'.uniqid(),
    ];
}

it('rejects a missing or wrong key', function (?string $key) {
    config(scriptConfig());

    $result = scriptRunner()->handle(
        task: 'auth-check',
        commands: [['command' => 'cache:clear']],
        providedKey: $key,
    );

    expect($result['status'])->toBe('forbidden')
        ->and($result['http'])->toBe(403)
        ->and($result['commands'])->toBe([]);
})->with([
    'missing' => null,
    'empty' => '',
    'wrong' => 'nope',
]);

it('refuses to run when the key is not configured', function () {
    config(array_merge(scriptConfig(), ['scripts.task_key' => '']));

    $result = scriptRunner()->handle(
        task: 'misconfigured',
        commands: [['command' => 'cache:clear']],
        providedKey: 'anything',
    );

    expect($result['status'])->toBe('misconfigured')
        ->and($result['http'])->toBe(500)
        ->and($result['message'])->toContain('SCRIPT_SECRET_KEY');
});

it('runs the configured commands and records them', function () {
    config(scriptConfig());

    $result = scriptRunner()->handle(
        task: 'cache-clear',
        commands: [
            ['command' => 'cache:clear', 'arguments' => []],
            ['command' => 'view:clear', 'arguments' => []],
        ],
        providedKey: 'test-script-key',
    );

    expect($result['status'])->toBe('ok')
        ->and($result['http'])->toBe(200)
        ->and($result['commands'])->toHaveCount(2)
        ->and(array_column($result['commands'], 'exit_code'))->toBe([0, 0])
        ->and($result['duration'])->toBeFloat();

    $log = app(ScriptRunLog::class);

    expect($log->recent())->toHaveCount(1)
        ->and($log->recent()[0]['task'])->toBe('cache-clear')
        ->and($log->recent()[0]['status'])->toBe('ok');
});

it('reports a failing command without hiding it', function () {
    config(scriptConfig());

    $result = scriptRunner()->handle(
        task: 'broken',
        commands: [['command' => 'does:not-exist']],
        providedKey: 'test-script-key',
    );

    expect($result['status'])->toBe('error')
        ->and($result['http'])->toBe(500)
        ->and($result['commands'][0]['exit_code'])->toBe(1)
        ->and($result['commands'][0]['output'])->toContain('does:not-exist');
});

it('refuses overlapping runs of the same task', function () {
    $config = scriptConfig();
    config($config);

    $directory = $config['scripts.lock_path'];
    mkdir($directory, 0777, true);

    $handle = fopen($directory.DIRECTORY_SEPARATOR.'overlap.lock', 'c');
    expect($handle)->not->toBeFalse();
    flock($handle, LOCK_EX | LOCK_NB);

    try {
        $result = scriptRunner()->handle(
            task: 'overlap',
            commands: [['command' => 'cache:clear']],
            providedKey: 'test-script-key',
        );
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    expect($result['status'])->toBe('busy')
        ->and($result['http'])->toBe(409)
        ->and($result['commands'])->toBe([]);
});

it('writes and reads the scheduler heartbeat', function () {
    config(scriptConfig());

    $result = scriptRunner()->handle(
        task: 'scheduler',
        commands: [['command' => 'cache:clear']],
        providedKey: 'test-script-key',
        heartbeat: true,
    );

    $log = app(ScriptRunLog::class);
    $state = $log->heartbeatState();

    expect($result['status'])->toBe('ok')
        ->and($state)->not->toBeNull()
        ->and($state['status'])->toBe('ok')
        ->and($log->heartbeatAgeMinutes())->toBe(0)
        ->and($log->heartbeatIsStale())->toBeFalse();
});

it('treats a missing heartbeat as stale', function () {
    config(scriptConfig());

    $log = app(ScriptRunLog::class);

    expect($log->heartbeatState())->toBeNull()
        ->and($log->heartbeatAgeMinutes())->toBeNull()
        ->and($log->heartbeatIsStale())->toBeTrue();
});

it('treats an old heartbeat as stale', function () {
    config(scriptConfig());

    $log = app(ScriptRunLog::class);
    $log->heartbeat(['status' => 'ok', 'message' => 'Task completed.', 'duration' => 1.2]);

    $path = config('scripts.heartbeat_path');
    $state = json_decode((string) file_get_contents($path), true);
    $state['at'] = now()->subMinutes(30)->toIso8601String();
    file_put_contents($path, json_encode($state));

    expect($log->heartbeatIsStale())->toBeTrue();
});

it('caps the script history', function () {
    config(scriptConfig());

    $path = config('scripts.runs_path');
    $entries = [];

    for ($i = 0; $i < 120; $i++) {
        $entries[] = ['task' => 'history-'.$i, 'status' => 'ok'];
    }

    file_put_contents($path, json_encode($entries));

    $log = app(ScriptRunLog::class);
    $log->record(['task' => 'newest', 'status' => 'ok', 'message' => 'done']);

    $recent = $log->recent(5);

    expect($recent[0]['task'])->toBe('newest')
        ->and($log->recent(200))->toHaveCount(100)
        ->and($log->recent(200)[99]['task'])->toBe('history-21');
});

it('reads the key from the query string or header', function (array $get, array $server, ?string $expected) {
    $_GET = $get;
    $_SERVER = array_merge($_SERVER, $server);

    try {
        expect(scriptRunner()->keyFromRequest())->toBe($expected);
    } finally {
        $_GET = [];
        unset($_SERVER['HTTP_X_SCRIPT_KEY']);
    }
})->with([
    'key parameter' => [['key' => 'from-query'], [], 'from-query'],
    'secret parameter' => [['secret' => 'from-secret'], [], 'from-secret'],
    'header' => [[], ['HTTP_X_SCRIPT_KEY' => 'from-header'], 'from-header'],
    'nothing' => [[], [], null],
]);
