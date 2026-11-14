<?php

use App\Models\User;

it('shows the application log to admins', function () {
    $path = tempnam(sys_get_temp_dir(), 'bleep-log-');
    file_put_contents($path, implode("\n", [
        '[2026-11-14 10:00:00] production.ERROR: Undefined array key "reason"',
        '#0 /app/Http/Controllers/ReportsController.php(40): handle()',
        '[2026-11-14 10:01:00] production.INFO: Report submitted',
    ])."\n");

    config(['system.log_path' => $path]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.system.logs'))
        ->assertOk()
        ->assertSee('System Logs')
        ->assertSee('Undefined array key')
        ->assertSee('Report submitted');

    @unlink($path);
});

it('filters the application log by level', function () {
    $path = tempnam(sys_get_temp_dir(), 'bleep-log-');
    file_put_contents($path, implode("\n", [
        '[2026-11-14 10:00:00] production.ERROR: Database exploded',
        '[2026-11-14 10:01:00] production.INFO: All good',
    ])."\n");

    config(['system.log_path' => $path]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.system.logs', ['level' => 'error']))
        ->assertOk()
        ->assertSee('Database exploded')
        ->assertDontSee('All good');

    @unlink($path);
});

it('handles a missing application log file', function () {
    config(['system.log_path' => sys_get_temp_dir().'/bleep-missing-'.uniqid().'.log']);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.system.logs'))
        ->assertOk()
        ->assertSee('No application log has been written yet.');
});

it('keeps application logs away from moderators', function () {
    $this->actingAs(User::factory()->create(['role' => 'moderator']))
        ->get(route('admin.system.logs'))
        ->assertForbidden();
});

it('redirects guests away from application logs', function () {
    $this->get(route('admin.system.logs'))
        ->assertRedirect(route('login'));
});

it('renders the attack review tab for admins', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.system.attacks'))
        ->assertOk()
        ->assertSee('Attacks')
        ->assertSee('No attack signals');
});

it('keeps the attack tab away from moderators', function () {
    $this->actingAs(User::factory()->create(['role' => 'moderator']))
        ->get(route('admin.system.attacks'))
        ->assertForbidden();
});
