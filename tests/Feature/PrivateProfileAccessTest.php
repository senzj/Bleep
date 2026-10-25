<?php

use App\Models\Bleep;
use App\Models\Following;
use App\Models\User;
use App\Models\UserPreferences;

function privateProfileOwner(): User
{
    $owner = User::factory()->create();
    $owner->preferences()->create([
        ...UserPreferences::defaults(),
        'private_profile' => true,
    ]);

    return $owner;
}

it('hides private profile bleeps from guests on every read endpoint', function () {
    $owner = privateProfileOwner();
    $bleep = Bleep::factory()->for($owner)->create([
        'message' => 'Private profile secret bleep',
    ]);

    $this->get("/bleeps/{$bleep->id}")->assertNotFound();
    $this->getJson("/bleeps/{$bleep->id}/data")->assertNotFound();
    $this->getJson("/bleeps/comments/{$bleep->id}/comments")->assertNotFound();
    $this->getJson("/bleeps/comments/{$bleep->id}/count")->assertNotFound();
});

it('blocks likes, reposts and shares of private profile bleeps for non-followers', function () {
    $owner = privateProfileOwner();
    $bleep = Bleep::factory()->for($owner)->create();
    $viewer = User::factory()->create();

    $this->actingAs($viewer)
        ->postJson("/bleeps/{$bleep->id}/like")
        ->assertNotFound();

    $this->actingAs($viewer)
        ->postJson("/bleeps/{$bleep->id}/repost")
        ->assertNotFound();

    $this->actingAs($viewer)
        ->postJson("/bleeps/{$bleep->id}/share")
        ->assertNotFound();

    $this->assertDatabaseCount('likes', 0);
    $this->assertDatabaseCount('reposts', 0);
    $this->assertDatabaseCount('shares', 0);
});

it('allows accepted followers to read private profile bleeps', function () {
    $owner = privateProfileOwner();
    $follower = User::factory()->create();
    $bleep = Bleep::factory()->for($owner)->create([
        'message' => 'Followers can see this bleep',
    ]);

    Following::create([
        'follower_id' => $follower->id,
        'followed_id' => $owner->id,
    ]);

    $this->actingAs($follower)
        ->get("/bleeps/{$bleep->id}")
        ->assertOk()
        ->assertSee('Followers can see this bleep');

    $this->actingAs($follower)
        ->getJson("/bleeps/comments/{$bleep->id}/comments")
        ->assertOk();
});
