<?php

use App\Events\Chat\MessageReacted;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;

function reactionConversation(User $a, User $b): Conversation
{
    $conversation = Conversation::create([
        'is_group' => false,
        'creator_id' => $a->id,
    ]);

    $conversation->participants()->attach([$a->id, $b->id]);

    return $conversation;
}

it('adds, switches and removes a reaction with idempotent desired states', function () {
    Event::fake();

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = reactionConversation($alice, $bob);

    $message = Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $bob->id,
        'body' => 'React to me',
    ]);

    $this->actingAs($alice)
        ->postJson("/messages/{$message->id}/reactions", ['emoji' => '👍', 'active' => true])
        ->assertOk()
        ->assertJsonPath('data.action', 'added')
        ->assertJsonPath('data.reactions.0.emoji', '👍')
        ->assertJsonPath('data.reactions.0.user_id', $alice->id);

    expect(MessageReaction::count())->toBe(1);

    // Replaying the same desired state must not create duplicates.
    $this->actingAs($alice)
        ->postJson("/messages/{$message->id}/reactions", ['emoji' => '👍', 'active' => true])
        ->assertOk()
        ->assertJsonPath('data.action', 'added');

    expect(MessageReaction::count())->toBe(1);

    // A different emoji replaces the previous reaction.
    $this->actingAs($alice)
        ->postJson("/messages/{$message->id}/reactions", ['emoji' => '❤️', 'active' => true])
        ->assertOk()
        ->assertJsonPath('data.reactions.0.emoji', '❤️');

    expect(MessageReaction::count())->toBe(1)
        ->and(MessageReaction::first()->emoji)->toBe('❤️');

    // Removing twice is also idempotent.
    $this->actingAs($alice)
        ->postJson("/messages/{$message->id}/reactions", ['emoji' => '❤️', 'active' => false])
        ->assertOk()
        ->assertJsonPath('data.action', 'removed')
        ->assertJsonCount(0, 'data.reactions');

    $this->actingAs($alice)
        ->postJson("/messages/{$message->id}/reactions", ['emoji' => '❤️', 'active' => false])
        ->assertOk()
        ->assertJsonPath('data.action', 'removed');

    expect(MessageReaction::count())->toBe(0);

    Event::assertDispatched(MessageReacted::class, 5);
});

it('keeps the legacy toggle behaviour when no desired state is sent', function () {
    Event::fake();

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = reactionConversation($alice, $bob);
    $message = Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $bob->id,
        'body' => 'Legacy toggle',
    ]);

    $this->actingAs($alice)
        ->postJson("/messages/{$message->id}/reactions", ['emoji' => '🔥'])
        ->assertOk()
        ->assertJsonPath('data.action', 'added');

    expect(MessageReaction::count())->toBe(1);

    $this->actingAs($alice)
        ->postJson("/messages/{$message->id}/reactions", ['emoji' => '🔥'])
        ->assertOk()
        ->assertJsonPath('data.action', 'removed');

    expect(MessageReaction::count())->toBe(0);
});

it('rejects reactions from non participants and deleted messages', function () {
    Event::fake();

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $mallory = User::factory()->create();
    $conversation = reactionConversation($alice, $bob);

    $message = Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $bob->id,
        'body' => 'Private',
    ]);

    $this->actingAs($mallory)
        ->postJson("/messages/{$message->id}/reactions", ['emoji' => '👍', 'active' => true])
        ->assertForbidden();

    $message->delete();

    $this->actingAs($alice)
        ->postJson("/messages/{$message->id}/reactions", ['emoji' => '👍', 'active' => true])
        ->assertNotFound();

    expect(MessageReaction::count())->toBe(0);
});

it('saves the reaction even when the broadcast transport is unreachable', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = reactionConversation($alice, $bob);

    $message = Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $bob->id,
        'body' => 'Broadcast down',
    ]);

    Broadcast::extend('throwing', fn () => new class implements Broadcaster
    {
        public function auth($request)
        {
            return null;
        }

        public function validAuthenticationResponse($request, $result)
        {
            return null;
        }

        public function broadcast(array $channels, $event, array $payload = [])
        {
            throw new BroadcastException('Broadcast transport unreachable.');
        }
    });

    config(['broadcasting.default' => 'throwing']);

    $this->actingAs($alice)
        ->postJson("/messages/{$message->id}/reactions", ['emoji' => '👍', 'active' => true])
        ->assertOk()
        ->assertJsonPath('data.action', 'added')
        ->assertJsonPath('data.reactions.0.emoji', '👍');

    expect(MessageReaction::where('message_id', $message->id)->count())->toBe(1);
});
