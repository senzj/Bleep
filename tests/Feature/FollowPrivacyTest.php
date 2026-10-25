<?php

use App\Models\BlockedUsers;
use App\Models\Following;
use App\Models\FollowRequest;
use App\Models\User;
use App\Models\UserPreferences;

function userWithPreferences(array $overrides = []): User
{
    $user = User::factory()->create();
    $user->preferences()->create([
        ...UserPreferences::defaults(),
        ...$overrides,
    ]);

    return $user;
}

it('creates a follow request instead of following a private profile directly', function () {
    $requester = User::factory()->create();
    $privateUser = userWithPreferences(['private_profile' => true]);

    $this->actingAs($requester)
        ->postJson("/bleeper/{$privateUser->id}/follow")
        ->assertOk()
        ->assertJson([
            'following' => false,
            'requested' => true,
        ]);

    $this->assertDatabaseMissing('followings', [
        'follower_id' => $requester->id,
        'followed_id' => $privateUser->id,
    ]);

    $this->assertDatabaseHas('follow_requests', [
        'requester_id' => $requester->id,
        'target_id' => $privateUser->id,
        'status' => 'pending',
    ]);
});

it('refuses new followers when the target blocks new followers', function () {
    $requester = User::factory()->create();
    $target = userWithPreferences(['block_new_followers' => true]);

    $this->actingAs($requester)
        ->postJson("/bleeper/{$target->id}/follow")
        ->assertForbidden();

    $this->assertDatabaseMissing('followings', [
        'follower_id' => $requester->id,
        'followed_id' => $target->id,
    ]);
});

it('still follows public profiles directly', function () {
    $requester = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($requester)
        ->postJson("/bleeper/{$target->id}/follow")
        ->assertOk()
        ->assertJson([
            'following' => true,
            'requested' => false,
        ]);

    $this->assertDatabaseHas('followings', [
        'follower_id' => $requester->id,
        'followed_id' => $target->id,
    ]);
});

it('rejects follow requests to yourself or across a block', function () {
    $requester = User::factory()->create();
    $blocked = User::factory()->create();

    BlockedUsers::create([
        'blocker_id' => $blocked->id,
        'blocked_id' => $requester->id,
    ]);

    $this->actingAs($requester)
        ->postJson('/api/follow-requests', ['target_id' => $requester->id])
        ->assertStatus(422);

    $this->actingAs($requester)
        ->postJson('/api/follow-requests', ['target_id' => $blocked->id])
        ->assertForbidden();

    expect(FollowRequest::count())->toBe(0);
});

it('accepts a pending follow request only for its target', function () {
    $requester = User::factory()->create();
    $target = userWithPreferences(['private_profile' => true]);
    $stranger = User::factory()->create();

    $followRequest = FollowRequest::create([
        'requester_id' => $requester->id,
        'target_id' => $target->id,
        'status' => 'pending',
    ]);

    $this->actingAs($stranger)
        ->postJson("/api/follow-requests/{$followRequest->id}/accept")
        ->assertForbidden();

    $this->actingAs($target)
        ->postJson("/api/follow-requests/{$followRequest->id}/accept")
        ->assertOk();

    expect($followRequest->fresh()->status)->toBe('accepted');

    $this->assertDatabaseHas('followings', [
        'follower_id' => $requester->id,
        'followed_id' => $target->id,
    ]);

    expect(Following::count())->toBe(1);
});
