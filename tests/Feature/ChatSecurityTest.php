<?php

use App\Models\BlockedUsers;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

function chatDirectConversation(User $a, User $b): Conversation
{
    $conversation = Conversation::create([
        'is_group' => false,
        'creator_id' => $a->id,
    ]);

    $conversation->participants()->attach([$a->id, $b->id]);

    return $conversation;
}

it('rejects a reply to a message from another conversation', function () {
    Event::fake();

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $carol = User::factory()->create();

    $conversation = chatDirectConversation($alice, $bob);
    $otherConversation = chatDirectConversation($alice, $carol);

    $foreignMessage = Message::create([
        'conversation_id' => $otherConversation->id,
        'sender_id' => $carol->id,
        'body' => 'Different conversation',
    ]);

    $this->actingAs($alice)
        ->postJson('/messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Replying to the wrong thread',
            'reply_to_id' => $foreignMessage->id,
        ])
        ->assertStatus(422);

    expect(Message::where('conversation_id', $conversation->id)->count())->toBe(0);
});

it('rejects direct messages across a block relationship', function () {
    Event::fake();

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = chatDirectConversation($alice, $bob);

    BlockedUsers::create([
        'blocker_id' => $bob->id,
        'blocked_id' => $alice->id,
    ]);

    $this->actingAs($alice)
        ->postJson('/messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Blocked message',
        ])
        ->assertForbidden();

    expect(Message::count())->toBe(0);
});

it('rejects chat media paths outside the chat directory or missing on disk', function () {
    Event::fake();
    Storage::fake('public');

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = chatDirectConversation($alice, $bob);

    $this->actingAs($alice)
        ->postJson('/messages', [
            'conversation_id' => $conversation->id,
            'media_path' => '../../.env',
            'media_type' => 'image/png',
            'media_kind' => 'media',
        ])
        ->assertStatus(422);

    $this->actingAs($alice)
        ->postJson('/messages', [
            'conversation_id' => $conversation->id,
            'media_path' => 'chat/media/missing.png',
            'media_type' => 'image/png',
            'media_kind' => 'media',
        ])
        ->assertStatus(422);

    Storage::disk('public')->put('chat/media/ok.png', 'image bytes');

    $this->actingAs($alice)
        ->postJson('/messages', [
            'conversation_id' => $conversation->id,
            'media_path' => 'chat/media/ok.png',
            'media_type' => 'image/png',
            'media_kind' => 'media',
        ])
        ->assertCreated();
});

it('only marks messages from the requested conversation as read', function () {
    Event::fake();

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $carol = User::factory()->create();

    $conversation = chatDirectConversation($alice, $bob);
    $otherConversation = chatDirectConversation($alice, $carol);

    $message = Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $bob->id,
        'body' => 'Unread message',
    ]);

    $foreignMessage = Message::create([
        'conversation_id' => $otherConversation->id,
        'sender_id' => $carol->id,
        'body' => 'Other conversation message',
    ]);

    $delivery = MessageDelivery::create([
        'message_id' => $message->id,
        'user_id' => $alice->id,
        'delivered_at' => now(),
        'read_at' => null,
    ]);

    $foreignDelivery = MessageDelivery::create([
        'message_id' => $foreignMessage->id,
        'user_id' => $alice->id,
        'delivered_at' => now(),
        'read_at' => null,
    ]);

    $this->actingAs($alice)
        ->postJson("/chat/conversations/{$conversation->id}/read", [
            'last_message_id' => $foreignMessage->id,
        ])
        ->assertStatus(422);

    expect($delivery->fresh()->read_at)->toBeNull();

    $this->actingAs($alice)
        ->postJson("/chat/conversations/{$conversation->id}/read", [
            'last_message_id' => $message->id,
        ])
        ->assertOk();

    expect($delivery->fresh()->read_at)->not->toBeNull()
        ->and($foreignDelivery->fresh()->read_at)->toBeNull();
});

it('returns the latest message for every conversation in the conversation list', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $carol = User::factory()->create();

    $first = chatDirectConversation($alice, $bob);
    $first->forceFill(['last_message_at' => now()->subMinute()])->save();
    Message::create([
        'conversation_id' => $first->id,
        'sender_id' => $bob->id,
        'body' => 'Newest in first conversation',
    ]);

    $second = chatDirectConversation($alice, $carol);
    $second->forceFill(['last_message_at' => now()])->save();
    Message::create([
        'conversation_id' => $second->id,
        'sender_id' => $carol->id,
        'body' => 'Newest in second conversation',
    ]);

    $response = $this->actingAs($alice)
        ->getJson('/chat/conversations')
        ->assertOk();

    $conversations = collect($response->json('data'))->keyBy('id');

    expect($conversations[$first->id]['last_message']['body'])->toBe('Newest in first conversation')
        ->and($conversations[$second->id]['last_message']['body'])->toBe('Newest in second conversation');
});
