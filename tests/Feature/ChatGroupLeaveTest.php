<?php

use App\Models\Conversation;
use App\Models\Following;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Event;

function chatGroupConversation(User $owner, array $members, string $name = 'Weekend Plans'): Conversation
{
    $conversation = Conversation::create([
        'name' => $name,
        'is_group' => true,
        'creator_id' => $owner->id,
    ]);

    $conversation->participants()->attach(
        collect([$owner, ...$members])->mapWithKeys(fn (User $user) => [
            $user->id => ['role' => $user->is($owner) ? 'admin' : 'member', 'joined_at' => now()],
        ])->all()
    );

    return $conversation;
}

it('lets a member leave a group chat and posts a notice', function () {
    Event::fake();

    $owner = User::factory()->create();
    $member = User::factory()->create();
    $conversation = chatGroupConversation($owner, [$member]);

    $this->actingAs($member)
        ->postJson("/chat/conversations/{$conversation->id}/leave")
        ->assertOk()
        ->assertJsonPath('data.has_left', true);

    $pivot = $conversation->participants()->where('users.id', $member->id)->first()->pivot;

    expect($pivot->left_at)->not->toBeNull();

    $notice = Message::where('conversation_id', $conversation->id)->latest('id')->first();

    expect($notice->sender_id)->toBe($member->id)
        ->and($notice->body)->toContain('left this group chat')
        ->and($notice->body)->toContain('Weekend Plans');
});

it('keeps left group chats read-only but still readable', function () {
    Event::fake();

    $owner = User::factory()->create();
    $member = User::factory()->create();
    $conversation = chatGroupConversation($owner, [$member]);

    $history = Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $owner->id,
        'body' => 'History before leaving',
    ]);

    $this->actingAs($member)
        ->postJson("/chat/conversations/{$conversation->id}/leave")
        ->assertOk();

    $this->actingAs($member)
        ->postJson('/messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Should not send',
        ])
        ->assertForbidden();

    $this->actingAs($member)
        ->getJson("/chat/conversations/{$conversation->id}/messages")
        ->assertOk()
        ->assertJsonFragment(['body' => $history->body])
        ->assertJsonPath('data.0.body', 'History before leaving');
});

it('reports the left state in the conversation list', function () {
    Event::fake();

    $owner = User::factory()->create();
    $member = User::factory()->create();
    $conversation = chatGroupConversation($owner, [$member]);

    $this->actingAs($member)
        ->postJson("/chat/conversations/{$conversation->id}/leave")
        ->assertOk();

    $response = $this->actingAs($member)
        ->getJson('/chat/conversations')
        ->assertOk();

    $listed = collect($response->json('data'))->firstWhere('id', $conversation->id);

    expect($listed['has_left'])->toBeTrue()
        ->and($listed['left_at'])->not->toBeNull();
});

it('does not allow leaving a direct conversation', function () {
    Event::fake();

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $conversation = Conversation::create([
        'is_group' => false,
        'creator_id' => $alice->id,
    ]);
    $conversation->participants()->attach([$alice->id, $bob->id]);

    $this->actingAs($alice)
        ->postJson("/chat/conversations/{$conversation->id}/leave")
        ->assertStatus(422);
});

it('keeps direct message history after both users unfollow', function () {
    Event::fake();

    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Following::create(['follower_id' => $alice->id, 'followed_id' => $bob->id]);
    Following::create(['follower_id' => $bob->id, 'followed_id' => $alice->id]);

    $conversation = Conversation::create([
        'is_group' => false,
        'creator_id' => $alice->id,
    ]);
    $conversation->participants()->attach([$alice->id, $bob->id]);

    Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $bob->id,
        'body' => 'Message from before the unfollow',
    ]);

    Following::where('follower_id', $alice->id)->where('followed_id', $bob->id)->delete();
    Following::where('follower_id', $bob->id)->where('followed_id', $alice->id)->delete();

    $this->actingAs($alice)
        ->getJson("/chat/conversations/{$conversation->id}/messages")
        ->assertOk()
        ->assertJsonFragment(['body' => 'Message from before the unfollow']);

    $this->actingAs($alice)
        ->getJson('/chat/conversations')
        ->assertOk()
        ->assertJsonFragment(['id' => $conversation->id]);
});
