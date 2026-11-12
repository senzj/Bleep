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
