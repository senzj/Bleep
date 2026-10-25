<?php

use App\Models\Logs;
use App\Models\User;

it('forbids moderators from changing user roles or bans', function () {
    $moderator = User::factory()->create(['role' => 'moderator']);
    $target = User::factory()->create(['role' => 'user']);

    $this->actingAs($moderator)
        ->postJson("/admin/users/{$target->id}/update", [
            'role' => 'admin',
            'is_verified' => false,
            'is_banned' => false,
        ])
        ->assertForbidden();

    expect($target->fresh()->role)->toBe('user');

    $this->actingAs($moderator)
        ->postJson("/admin/users/{$moderator->id}/update", [
            'role' => 'admin',
            'is_verified' => false,
            'is_banned' => false,
        ])
        ->assertForbidden();

    expect($moderator->fresh()->role)->toBe('moderator');
});

it('allows admins to update user roles', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create(['role' => 'user']);

    $this->actingAs($admin)
        ->postJson("/admin/users/{$target->id}/update", [
            'role' => 'moderator',
            'is_verified' => true,
            'is_banned' => false,
        ])
        ->assertOk();

    expect($target->fresh())
        ->role->toBe('moderator')
        ->is_verified->toBeTrue();
});

it('allows admins to search the logs page without ambiguous column errors', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create(['username' => 'searchable-user']);

    Logs::create([
        'user_id' => $target->id,
        'action' => 'login',
        'ip' => '127.0.0.1',
        'user_agent' => 'Pest',
    ]);

    $this->actingAs($admin)
        ->get('/admin/logs?q=searchable-user')
        ->assertOk()
        ->assertSee('searchable-user');
});
