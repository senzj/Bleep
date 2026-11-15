<?php

use App\Models\User;
use App\Services\System\ScheduleInspector;
use App\Services\System\ScheduleRunRecorder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Carbon;

function scheduledGeoIpEvent(): Event
{
    app(ConsoleKernel::class)->bootstrap();

    return collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => $event->description === 'Refresh the GeoIP database');
}

function temporarySchedulePath(): string
{
    return sys_get_temp_dir().DIRECTORY_SEPARATOR.'bleep-schedule-'.uniqid().'.json';
}

it('lists every scheduled task with its schedule and next run', function () {
    config(['system.schedule_runs_path' => temporarySchedulePath()]);

    $events = collect(app(ScheduleInspector::class)->events());

    expect($events->pluck('description'))
        ->toContain(
            'Clean up old records',
            'Prune expired bulletin notes',
            'Refresh the GeoIP database',
            'Run the system health check',
        );

    $geoIp = $events->firstWhere('description', 'Refresh the GeoIP database');

    expect($geoIp['expression'])->toBe('0 4 3 * *')
        ->and($geoIp['human'])->toBe('Monthly on day 3 at 04:00')
        ->and($geoIp['next_run'])->toBeInstanceOf(Carbon::class)
        ->and($geoIp['last_run'])->toBeNull();
});

it('aggregates every run that falls on a given date', function () {
    config(['system.schedule_runs_path' => temporarySchedulePath()]);

    $runs = collect(app(ScheduleInspector::class)->runsOn(Carbon::parse('2026-12-03')));

    $geoIp = $runs->firstWhere('description', 'Refresh the GeoIP database');

    expect($geoIp)->not->toBeNull()
        ->and($geoIp['times'])->toBe(['04:00'])
        ->and($geoIp['count'])->toBe(1)
        ->and($runs->firstWhere('description', 'Clean up old records')['count'])->toBe(1)
        ->and($runs->firstWhere('description', 'Prune expired bulletin notes')['count'])->toBe(24)
        ->and($runs->firstWhere('description', 'Run the system health check'))->toBeNull();
});

it('records the last run of a scheduled task', function () {
    $path = temporarySchedulePath();
    config(['system.schedule_runs_path' => $path]);

    app(ScheduleRunRecorder::class)->record(scheduledGeoIpEvent(), 'success', 4.25);

    $event = collect(app(ScheduleInspector::class)->events())
        ->firstWhere('description', 'Refresh the GeoIP database');

    expect($event['last_run']['status'])->toBe('success')
        ->and($event['last_run']['runtime'])->toBe(4.25)
        ->and($event['last_run']['message'])->toBeNull();

    app(ScheduleRunRecorder::class)->record(scheduledGeoIpEvent(), 'failed', 1.5, 'Download timed out');

    $event = collect(app(ScheduleInspector::class)->events())
        ->firstWhere('description', 'Refresh the GeoIP database');

    expect($event['last_run']['status'])->toBe('failed')
        ->and($event['last_run']['message'])->toBe('Download timed out');

    @unlink($path);
});

it('renders the schedule tab for admins', function () {
    $path = temporarySchedulePath();
    config([
        'system.schedule_runs_path' => $path,
        'system.health_path' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'bleep-health-none-'.uniqid().'.json',
        'scripts.heartbeat_path' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'bleep-heartbeat-none-'.uniqid().'.json',
    ]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.system.schedule'))
        ->assertOk()
        ->assertSee('Schedule')
        ->assertSee('Refresh the GeoIP database')
        ->assertSee('Run the system health check')
        ->assertSee('Scheduler trigger')
        ->assertSee('Never triggered')
        ->assertSee('Not recorded yet');
});

it('shows a stale scheduler trigger', function () {
    $heartbeat = sys_get_temp_dir().DIRECTORY_SEPARATOR.'bleep-heartbeat-stale-'.uniqid().'.json';

    file_put_contents($heartbeat, json_encode([
        'at' => now()->subHour()->toIso8601String(),
        'status' => 'ok',
        'message' => 'Task completed successfully.',
        'duration' => 1.2,
    ]));

    config([
        'system.schedule_runs_path' => temporarySchedulePath(),
        'system.health_path' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'bleep-health-none-'.uniqid().'.json',
        'scripts.heartbeat_path' => $heartbeat,
        'scripts.heartbeat_stale_minutes' => 10,
    ]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.system.schedule'))
        ->assertOk()
        ->assertSee('Scheduler trigger')
        ->assertSee('Stale')
        ->assertSee('run-scheduler.php');

    @unlink($heartbeat);
});

it('shows the tasks due on a chosen date', function () {
    config([
        'system.schedule_runs_path' => temporarySchedulePath(),
        'system.health_path' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'bleep-health-none-'.uniqid().'.json',
        'scripts.heartbeat_path' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'bleep-heartbeat-none-'.uniqid().'.json',
    ]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.system.schedule', ['date' => '2026-12-03']))
        ->assertOk()
        ->assertSee('December 3, 2026')
        ->assertSee('04:00')
        ->assertSee('Refresh the GeoIP database');
});

it('keeps the schedule tab away from moderators', function () {
    config(['system.schedule_runs_path' => temporarySchedulePath()]);

    $this->actingAs(User::factory()->create(['role' => 'moderator']))
        ->get(route('admin.system.schedule'))
        ->assertForbidden();

    $this->actingAs(User::factory()->create(['role' => 'moderator']))
        ->post(route('admin.system.health'))
        ->assertForbidden();
});

it('redirects guests away from the schedule tab', function () {
    $this->get(route('admin.system.schedule'))
        ->assertRedirect(route('login'));
});
