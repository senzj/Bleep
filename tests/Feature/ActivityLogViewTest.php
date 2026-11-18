<?php

use App\Models\Logs;
use App\Models\User;

it('shows activity as readable text with a JSON toggle', function () {
    $user = User::factory()->create();

    Logs::create([
        'user_id' => $user->id,
        'action' => 'login',
        'details' => ['username' => $user->username, 'remember' => false],
        'ip' => '127.0.0.1',
    ]);

    $this->actingAs($user)
        ->get(route('settings.logs'))
        ->assertSuccessful()
        ->assertSee('Signed in')
        ->assertSee('View JSON');
});

it('shows activity timestamps in the viewer own timezone', function () {
    $user = User::factory()->create(['timezone' => 'Asia/Manila']);

    Logs::unguarded(fn () => Logs::create([
        'user_id' => $user->id,
        'action' => 'login',
        'ip' => '127.0.0.1',
        'created_at' => '2026-01-01 00:00:00',
    ]));

    $this->actingAs($user)
        ->get(route('settings.logs'))
        ->assertSuccessful()
        ->assertSee('January 01, 2026')
        ->assertSee('08:00:00 AM');
});

it('falls back to a neutral summary when there are no details', function () {
    $user = User::factory()->create();

    Logs::create([
        'user_id' => $user->id,
        'action' => 'logout',
        'details' => null,
    ]);

    $this->actingAs($user)
        ->get(route('settings.logs'))
        ->assertSuccessful()
        ->assertSee('Signed out');
});

it('lists personal activity newest first even when timestamps tie', function () {
    $user = User::factory()->create();

    Logs::unguarded(function () use ($user) {
        Logs::create([
            'user_id' => $user->id,
            'action' => 'login',
            'details' => ['message' => 'older activity marker'],
            'created_at' => '2026-01-01 00:00:00',
        ]);

        Logs::create([
            'user_id' => $user->id,
            'action' => 'logout',
            'details' => ['message' => 'newest activity marker'],
            'created_at' => '2026-01-01 00:00:00',
        ]);
    });

    $body = $this->actingAs($user)
        ->get(route('settings.logs'))
        ->assertSuccessful()
        ->getContent();

    expect(strpos($body, 'newest activity marker'))
        ->toBeLessThan(strpos($body, 'older activity marker'));
});

it('lists admin activity newest first even when timestamps tie', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Logs::unguarded(function () use ($admin) {
        foreach (['older admin marker', 'newest admin marker'] as $message) {
            Logs::create([
                'user_id' => $admin->id,
                'action' => 'login',
                'details' => ['message' => $message],
                'created_at' => '2026-01-01 00:00:00',
            ]);
        }
    });

    $body = $this->actingAs($admin)
        ->get(route('admin.logs'))
        ->assertSuccessful()
        ->getContent();

    expect(strpos($body, 'newest admin marker'))
        ->toBeLessThan(strpos($body, 'older admin marker'));
});
