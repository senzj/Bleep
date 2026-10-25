<?php

use App\Models\Bleep;
use App\Models\User;

it('redirects guests to login when creating a bleep', function () {
    $this->post('/bleeps', ['message' => 'Guest bleep'])
        ->assertRedirect(route('login'));

    $this->assertDatabaseCount('bleeps', 0);
});

it('allows authenticated users to create a bleep', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/bleeps', ['message' => 'My first bleep'])
        ->assertRedirect('/');

    $this->assertDatabaseHas('bleeps', [
        'user_id' => $user->id,
        'message' => 'My first bleep',
    ]);
});

it('prevents users from deleting another users bleep', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $bleep = Bleep::factory()->for($owner)->create();

    $this->actingAs($otherUser)
        ->delete("/bleeps/{$bleep->id}/delete")
        ->assertForbidden();

    $this->assertNotSoftDeleted('bleeps', ['id' => $bleep->id]);
});

it('allows owners to soft delete their own bleep', function () {
    $owner = User::factory()->create();
    $bleep = Bleep::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->delete("/bleeps/{$bleep->id}/delete")
        ->assertRedirect();

    $this->assertSoftDeleted('bleeps', ['id' => $bleep->id]);
    $this->assertDatabaseHas('bleeps', [
        'id' => $bleep->id,
        'deleted_by_author' => true,
    ]);
});
