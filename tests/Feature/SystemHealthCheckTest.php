<?php

use App\Models\User;

function temporaryHealthPath(): string
{
    return sys_get_temp_dir().DIRECTORY_SEPARATOR.'bleep-health-'.uniqid().'.json';
}

function missingGeoIpPath(): string
{
    return sys_get_temp_dir().DIRECTORY_SEPARATOR.'bleep-geoip-missing-'.uniqid().'.mmdb';
}

function temporaryHeartbeatPath(): string
{
    return sys_get_temp_dir().DIRECTORY_SEPARATOR.'bleep-heartbeat-'.uniqid().'.json';
}

it('writes a health report and exits successfully', function () {
    $path = temporaryHealthPath();
    config([
        'system.health_path' => $path,
        'geoip.database' => missingGeoIpPath(),
        'scripts.heartbeat_path' => temporaryHeartbeatPath(),
    ]);

    $this->artisan('system:health')->assertExitCode(0);

    expect(is_file($path))->toBeTrue();

    $report = json_decode((string) file_get_contents($path), true);

    expect($report['status'])->toBe('warning')
        ->and(array_column($report['checks'], 'key'))
        ->toContain('application', 'scheduler', 'database', 'cache', 'storage', 'queue', 'broadcasting', 'geoip', 'logs');

    expect(collect($report['checks'])->firstWhere('key', 'database')['status'])->toBe('ok');

    $scheduler = collect($report['checks'])->firstWhere('key', 'scheduler');

    expect($scheduler['status'])->toBe('warning')
        ->and($scheduler['detail'])->toContain('run-scheduler.php');

    @unlink($path);
});

it('prints the report as json when asked', function () {
    $path = temporaryHealthPath();
    config([
        'system.health_path' => $path,
        'geoip.database' => missingGeoIpPath(),
        'scripts.heartbeat_path' => temporaryHeartbeatPath(),
    ]);

    $this->artisan('system:health', ['--json' => true])
        ->expectsOutputToContain('"checks"')
        ->assertExitCode(0);

    @unlink($path);
});

it('runs the health check from the schedule tab', function () {
    $path = temporaryHealthPath();
    config([
        'system.health_path' => $path,
        'geoip.database' => missingGeoIpPath(),
        'scripts.heartbeat_path' => temporaryHeartbeatPath(),
    ]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->post(route('admin.system.health'))
        ->assertRedirect(route('admin.system.schedule'))
        ->assertSessionHas('success');

    expect(is_file($path))->toBeTrue();

    @unlink($path);
});

it('refuses the health check for moderators and guests', function () {
    $this->post(route('admin.system.health'))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create(['role' => 'moderator']))
        ->post(route('admin.system.health'))
        ->assertForbidden();
});
