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

it('lets admins keep browsing public pages during maintenance', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/')
        ->assertSuccessful();
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

it('serves the maintenance page on the sign-in route for guests', function () {
    $this->get('/login')
        ->assertStatus(503)
        ->assertSee('is under maintenance');
});

it('keeps serving assets while the site is offline', function () {
    $this->get('/images/maintenance/image-1.webp')
        ->assertOk()
        ->assertStreamedContent(file_get_contents(public_path('images/maintenance/image-1.webp')));

    $this->get('/favicon.ico')
        ->assertOk();

    $this->get('/build/assets/missing.css')
        ->assertNotFound();
});

it('serves the maintenance page in local and debug environments too', function () {
    config(['app.debug' => true]);

    $this->get('/')
        ->assertStatus(503)
        ->assertSee('is under maintenance');
});

it('stays out of the way when maintenance mode is off', function () {
    config(['app.maintenance_mode.enabled' => false]);

    $this->get('/')->assertSuccessful();
});
