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
        ->assertSee('is under maintenance')
        ->assertSee('images/maintenance/image-', false)
        ->assertSee('Expected back around');
});

it('serves the maintenance page to staff on public pages', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/')
        ->assertStatus(503)
        ->assertSee('is under maintenance');
});

it('lets staff reach the sign-in, settings and admin areas', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/system')
        ->assertSuccessful();

    $this->actingAs($admin)
        ->get('/settings/system')
        ->assertSuccessful();
});

it('keeps the sign-in page reachable for guests', function () {
    $this->get('/login')
        ->assertSuccessful()
        ->assertSee('Sign In');
});

it('does not intercept local or debug environments', function () {
    config(['app.debug' => true]);

    $this->get('/')->assertSuccessful();
});

it('stays out of the way when maintenance mode is off', function () {
    config(['app.maintenance_mode.enabled' => false]);

    $this->get('/')->assertSuccessful();
});
