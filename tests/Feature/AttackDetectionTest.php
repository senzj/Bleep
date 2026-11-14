<?php

use App\Models\Logs;
use App\Models\User;

function failedLoginRows(string $ip, int $count): void
{
    $userId = User::factory()->create()->id;

    for ($i = 0; $i < $count; $i++) {
        Logs::create([
            'user_id' => $userId,
            'action' => 'failed_login',
            'ip' => $ip,
            'user_agent' => 'Pest',
            'created_at' => now()->subMinutes($i + 1),
        ]);
    }
}

it('flags repeated failed sign-ins as a confirmed attack', function () {
    failedLoginRows('203.0.113.50', 16);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.system.attacks'))
        ->assertOk()
        ->assertSee('Repeated failed sign-ins')
        ->assertSee('203.0.113.50');
});

it('does not flag a handful of failed sign-ins', function () {
    failedLoginRows('203.0.113.51', 2);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.system.attacks'))
        ->assertOk()
        ->assertSee('No attack signals');
});

it('flags attack signatures written to the application log', function () {
    $path = tempnam(sys_get_temp_dir(), 'bleep-attack-log-');
    $timestamp = now()->format('Y-m-d H:i:s');
    $entry = "[{$timestamp}] production.ERROR: CSRF token mismatch.";

    file_put_contents($path, implode("\n", array_fill(0, 12, $entry))."\n");
    config(['system.log_path' => $path]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.system.attacks'))
        ->assertOk()
        ->assertSee('CSRF token mismatches')
        ->assertSee('Confirmed');

    @unlink($path);
});

it('ignores old log signatures outside the review window', function () {
    $path = tempnam(sys_get_temp_dir(), 'bleep-attack-old-');
    $timestamp = now()->subDays(3)->format('Y-m-d H:i:s');
    $entry = "[{$timestamp}] production.ERROR: CSRF token mismatch.";

    file_put_contents($path, implode("\n", array_fill(0, 12, $entry))."\n");
    config(['system.log_path' => $path]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get(route('admin.system.attacks'))
        ->assertOk()
        ->assertSee('No attack signals');

    @unlink($path);
});
