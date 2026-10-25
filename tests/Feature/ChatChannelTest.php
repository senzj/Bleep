<?php

use App\Models\Conversation;
use App\Models\User;

function conversationWith(User $participant, User $otherParticipant): Conversation
{
    $conversation = Conversation::create([
        'is_group' => true,
        'creator_id' => $participant->id,
    ]);

    $conversation->participants()->attach([
        $participant->id,
        $otherParticipant->id,
    ]);

    return $conversation;
}

it('authorizes conversation participants for the private channel', function () {
    $participant = User::factory()->create();
    $otherParticipant = User::factory()->create();
    $conversation = conversationWith($participant, $otherParticipant);

    $this->actingAs($participant)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-conversation.{$conversation->id}",
        ])
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

it('forbids non-participants from the conversation private channel', function () {
    $participant = User::factory()->create();
    $otherParticipant = User::factory()->create();
    $conversation = conversationWith($participant, $otherParticipant);
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-conversation.{$conversation->id}",
        ])
        ->assertForbidden();
});
