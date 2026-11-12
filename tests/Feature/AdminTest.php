<?php

use App\Models\User;
use App\Models\Visits;

it('redirects guests from admin routes', function () {
    $this->get('/admin/dashboard')
        ->assertRedirect(route('login'));
});

it('forbids regular users from admin routes', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/dashboard')
        ->assertForbidden();
});

it('allows admins to view the dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/dashboard')
        ->assertOk();
});

it('allows moderators to view the dashboard', function () {
    $moderator = User::factory()->create(['role' => 'moderator']);

    $this->actingAs($moderator)
        ->get('/admin/dashboard')
        ->assertOk();
});

it('shows a unique ip table on the site visits page', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Visits::create([
        'ip_address' => '203.0.113.7',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'browser' => 'Chrome',
        'platform' => 'Windows',
    ]);
    Visits::create([
        'ip_address' => '203.0.113.7',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'browser' => 'Chrome',
        'platform' => 'Windows',
    ]);
    Visits::create([
        'ip_address' => '198.51.100.9',
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
        'browser' => 'Safari',
        'platform' => 'macOS',
    ]);

    $this->actingAs($admin)
        ->get('/admin/visits')
        ->assertOk()
        ->assertSee('Unique IP Addresses')
        ->assertSee('203.0.113.7')
        ->assertSee('198.51.100.9');
});
