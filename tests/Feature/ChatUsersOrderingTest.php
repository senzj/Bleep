<?php

use App\Models\Bleep;
use App\Models\Comments;
use App\Models\Conversation;
use App\Models\Following;
use App\Models\Likes;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
        ->toBe(route('media.image', ['size' => 'thumb', 'path' => 'avatars/bob.jpg']));
});

it('counts weeks away from the persistent last active stamp', function () {
    $viewer = User::factory()->create();
    $away = makeFriend($viewer, ['last_active_at' => now()->subWeeks(3)]);

    $payload = collect($this->actingAs($viewer)->getJson('/chat/users')->assertOk()->json('data'))
        ->firstWhere('id', $away->id);

    expect($payload['is_online'])->toBeFalse()
        ->and($payload['last_seen_at'])->toBe($away->last_active_at->toIso8601String());
});

it('marks a chatted non-mutual member as online in the conversation list', function () {
    $viewer = User::factory()->create();
    $chatPartner = User::factory()->create();

    $conversation = Conversation::create([
        'is_group' => false,
        'creator_id' => $viewer->id,
    ]);
    $conversation->participants()->attach([$viewer->id, $chatPartner->id]);

    DB::table('sessions')->insert([
        'id' => 'chat-presence-session',
        'user_id' => $chatPartner->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $conversations = collect($this->actingAs($viewer)
        ->getJson('/chat/conversations')
        ->assertOk()
        ->json('data'));

    $participant = collect($conversations->firstWhere('id', $conversation->id)['participants'])
        ->firstWhere('id', $chatPartner->id);

    expect($participant['is_online'])->toBeTrue();

    // The friend directory only lists mutuals, so the home online row also
    // reads conversation participants to include people already chatted with.
    $directoryIds = collect($this->actingAs($viewer)
        ->getJson('/chat/users')
        ->assertOk()
        ->json('data'))
        ->pluck('id');

    expect($directoryIds)->not->toContain($chatPartner->id);
});

it('falls back to the persistent last active stamp in conversation participants', function () {
    $viewer = User::factory()->create();
    $away = User::factory()->create(['last_active_at' => now()->subMonths(2)]);

    $conversation = Conversation::create([
        'is_group' => false,
        'creator_id' => $viewer->id,
    ]);
    $conversation->participants()->attach([$viewer->id, $away->id]);

    $payload = collect($this->actingAs($viewer)->getJson('/chat/conversations')->assertOk()->json('data'))
        ->firstWhere('id', $conversation->id);

    $participant = collect($payload['participants'])->firstWhere('id', $away->id);

    expect($participant['is_online'])->toBeFalse()
        ->and($participant['last_seen_at'])->toBe($away->last_active_at->toIso8601String());
});

it('records when a signed-in member was last active', function () {
    $user = User::factory()->create(['last_active_at' => null]);

    $this->actingAs($user)->get('/')->assertOk();

    expect($user->fresh()->last_active_at)->not->toBeNull();
});
