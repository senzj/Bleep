<?php

use App\Models\Bleep;
use App\Models\User;

/*
 * The like button is painted optimistically by resources/js/bleep/posts/like.js.
 * That script keys off the `like-btn` class, the `text-red-600` liked class and
 * the `.heart-icon` child, so those are a contract between the markup and the
 * broken-heart hover state. These assertions keep that contract from drifting
 * silently when the footer markup changes.
 */
it('renders the like control contract used by the optimistic icon', function () {
    $viewer = User::factory()->create(['profile_picture' => null]);
    $bleep = Bleep::factory()->create(['is_anonymous' => false]);

    $this->actingAs($viewer)
        ->get(route('post', ['id' => $bleep->id]))
        ->assertOk()
        ->assertSee('like-btn', false)
        ->assertSee('heart-icon', false)
        ->assertSee('data-bleep-id="'.$bleep->id.'"', false)
        ->assertSee('aria-pressed="false"', false)
        ->assertDontSee('like-btn text-red-600', false);

    $this->actingAs($viewer)
        ->postJson("/bleeps/{$bleep->id}/like")
        ->assertOk()
        ->assertJson(['liked' => true]);

    $this->actingAs($viewer)
        ->get(route('post', ['id' => $bleep->id]))
        ->assertOk()
        ->assertSee('like-btn text-red-600', false)
        ->assertSee('aria-pressed="true"', false);
});
