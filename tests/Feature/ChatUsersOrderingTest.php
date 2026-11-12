<?php

use App\Models\Bleep;
use App\Models\Comments;
use App\Models\Conversation;
use App\Models\Following;
use App\Models\Likes;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function makeFriend(User $viewer, array $attributes = []): User
{
    $friend = User::factory()->create($attributes);

    Following::create([
        'follower_id' => $viewer->id,
        'followed_id' => $friend->id,
    ]);

    Following::create([
        'follower_id' => $friend->id,
        'followed_id' => $viewer->id,
    ]);

    return $friend;
}

it('orders chat recipients by how much they interact with the viewer', function () {
    $viewer = User::factory()->create();
    $quietFriend = makeFriend($viewer);
    $activeFriend = makeFriend($viewer);

    $bleep = Bleep::factory()->for($viewer)->create();

    Likes::create([
        'user_id' => $activeFriend->id,
        'bleep_id' => $bleep->id,
    ]);

    Comments::create([
        'user_id' => $activeFriend->id,
        'bleep_id' => $bleep->id,
        'message' => 'Great bleep.',
    ]);

    $payload = collect($this->actingAs($viewer)->getJson('/chat/users')->assertOk()->json('data'));

    expect($payload->pluck('id')->all())->toBe([$activeFriend->id, $quietFriend->id])
        ->and($payload->firstWhere('id', $activeFriend->id)['interaction_score'])->toBe(2)
        ->and($payload->firstWhere('id', $quietFriend->id)['interaction_score'])->toBe(0);
});

it('excludes people who are not mutual friends', function () {
    $viewer = User::factory()->create();
    $stranger = User::factory()->create();

    Following::create([
        'follower_id' => $viewer->id,
        'followed_id' => $stranger->id,
    ]);

    $payload = collect($this->actingAs($viewer)->getJson('/chat/users')->assertOk()->json('data'));

    expect($payload->pluck('id')->all())->not->toContain($stranger->id);
});

it('returns the other participant avatar for direct conversations', function () {
    Storage::fake('public');
    Storage::disk('public')->put('avatars/bob.jpg', 'fake');

    $viewer = User::factory()->create();
    $bob = User::factory()->create(['profile_picture' => 'avatars/bob.jpg']);

    $conversation = Conversation::create([
        'is_group' => false,
        'creator_id' => $viewer->id,
    ]);
    $conversation->participants()->attach([$viewer->id, $bob->id]);

    $payload = collect($this->actingAs($viewer)->getJson('/chat/conversations')->assertOk()->json('data'));

    expect($payload->firstWhere('id', $conversation->id)['avatar'])
        ->toBe(asset('storage/avatars/bob.jpg'));
});
