<?php

use App\Models\Following;
use App\Models\User;
use App\Models\UserPreferences;

function userWithFollowerVisibility(string $visibility): User
{
    $user = User::factory()->create();
    $user->preferences()->create([
        ...UserPreferences::defaults(),
        'follower_list_visibility' => $visibility,
    ]);

    return $user;
}

function makeFriends(User $a, User $b): void
{
    Following::create(['follower_id' => $a->id, 'followed_id' => $b->id]);
    Following::create(['follower_id' => $b->id, 'followed_id' => $a->id]);
}

it('hides follower lists from everyone when set to only me', function () {
    $owner = userWithFollowerVisibility('only_me');
    $stranger = User::factory()->create();

    $this->getJson("/bleeper/{$owner->username}/relationships/followers")
        ->assertForbidden();

    $this->actingAs($stranger)
        ->getJson("/bleeper/{$owner->username}/relationships/following")
        ->assertForbidden();

    $this->actingAs($owner)
        ->getJson("/bleeper/{$owner->username}/relationships/followers")
        ->assertSuccessful();
});

it('limits follower lists to friends when set to friends', function () {
    $owner = userWithFollowerVisibility('friends');
    $friend = User::factory()->create();
    $stranger = User::factory()->create();
    makeFriends($owner, $friend);

    $this->actingAs($stranger)
        ->getJson("/bleeper/{$owner->username}/relationships/followers")
        ->assertForbidden();

    $this->actingAs($friend)
        ->getJson("/bleeper/{$owner->username}/relationships/followers")
        ->assertSuccessful();
});

it('lets staff review any follower list', function () {
    $owner = userWithFollowerVisibility('only_me');
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->getJson("/bleeper/{$owner->username}/relationships/followers")
        ->assertSuccessful();
});

it('hides the follower counters on the profile page when restricted', function () {
    $owner = userWithFollowerVisibility('only_me');

    $this->get(route('user.profile', ['username' => $owner->username]))
        ->assertSuccessful()
        ->assertDontSee('data-relationship-trigger="followers"', false)
        ->assertSee('keeps its follower lists private');
});

it('defaults follower list visibility to everyone', function () {
    $user = User::factory()->create();
    $user->preferences()->create(UserPreferences::defaults());

    expect($user->fresh()->preferences->follower_list_visibility)->toBe('everyone');
});

it('hides account actions from guests on a profile page', function () {
    $user = User::factory()->create();

    $this->get(route('user.profile', ['username' => $user->username]))
        ->assertSuccessful()
        ->assertSee('Sign in to follow')
        ->assertDontSee('Block user');
});
