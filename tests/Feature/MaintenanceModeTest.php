<?php

use App\Models\User;

beforeEach(function () {
    config([
        'app.debug' => false,
        'app.maintenance_mode.enabled' => true,
        'app.maintenance_mode.duration' => '30',
    ]);
});

it('serves the maintenance page to guests while enabled', function () {
    $this->get('/')
        ->assertStatus(503)
        ->assertSee('is being updated')
        ->assertSee('Expected back around');
});

it('lets staff bypass maintenance mode', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/')
        ->assertSuccessful();
});

it('does not intercept local or debug environments', function () {
    config(['app.debug' => true]);

    $this->get('/')->assertSuccessful();
});

it('stays out of the way when maintenance mode is off', function () {
    config(['app.maintenance_mode.enabled' => false]);

    $this->get('/')->assertSuccessful();
});
