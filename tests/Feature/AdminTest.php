<?php

use App\Models\User;

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
