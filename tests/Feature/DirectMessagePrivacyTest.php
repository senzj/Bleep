<?php

use App\Models\Conversation;
use App\Models\Following;
use App\Models\User;
use App\Models\UserPreferences;

function userWithDmPrivacy(string $privacy): User
{
    $user = User::factory()->create();
    $user->preferences()->create([
        ...UserPreferences::defaults(),
        'dm_privacy' => $privacy,
    ]);

    return $user;
}

function follow(User $follower, User $followed): void
{
    Following::create([
        'follower_id' => $follower->id,
        'followed_id' => $followed->id,
    ]);
}

it('allows anyone to start a direct message when privacy is everyone', function () {
    $sender = User::factory()->create();
    $recipient = userWithDmPrivacy('everyone');

    $this->actingAs($sender)
        ->postJson('/chat/conversations/direct', ['user_id' => $recipient->id])
        ->assertOk()
        ->assertJsonStructure(['data' => ['id']]);
});

it('requires the sender to follow the recipient when privacy is followers', function () {
    $sender = User::factory()->create();
    $recipient = userWithDmPrivacy('followers');

    $this->actingAs($sender)
        ->postJson('/chat/conversations/direct', ['user_id' => $recipient->id])
        ->assertForbidden();

    follow($sender, $recipient);

    $this->actingAs($sender)
        ->postJson('/chat/conversations/direct', ['user_id' => $recipient->id])
        ->assertOk();
});

it('requires a mutual follow when privacy is friends', function () {
    $sender = User::factory()->create();
    $recipient = userWithDmPrivacy('friends');

    follow($sender, $recipient);

    $this->actingAs($sender)
        ->postJson('/chat/conversations/direct', ['user_id' => $recipient->id])
        ->assertForbidden();

    follow($recipient, $sender);

    $this->actingAs($sender)
        ->postJson('/chat/conversations/direct', ['user_id' => $recipient->id])
        ->assertOk();
});

it('keeps existing direct conversations available after privacy changes', function () {
    $sender = User::factory()->create();
    $recipient = userWithDmPrivacy('everyone');

    $conversation = Conversation::create([
        'name' => null,
        'is_group' => false,
        'creator_id' => $sender->id,
    ]);
    $conversation->participants()->attach([
        $sender->id => ['role' => 'member', 'joined_at' => now()],
        $recipient->id => ['role' => 'member', 'joined_at' => now()],
    ]);

    $recipient->preferences()->update(['dm_privacy' => 'friends']);

    $this->actingAs($sender)
        ->postJson('/chat/conversations/direct', ['user_id' => $recipient->id])
        ->assertOk()
        ->assertJsonPath('data.id', $conversation->id);
});

it('defaults direct message privacy to everyone', function () {
    $user = User::factory()->create();

    expect($user->getPreferences()->dm_privacy)->toBe('everyone');
});
