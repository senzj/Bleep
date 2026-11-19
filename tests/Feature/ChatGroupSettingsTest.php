<?php

use App\Models\Bleep;
use App\Models\Conversation;
use App\Models\ConversationInvite;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

function groupConversation(User $admin, array $members = [], array $attributes = []): Conversation
{
    $conversation = Conversation::create(array_merge([
        'name' => 'Crew',
        'is_group' => true,
        'creator_id' => $admin->id,
    ], $attributes));

    $conversation->participants()->attach($admin->id, [
        'role' => 'admin',
        'joined_at' => now(),
    ]);

    foreach ($members as $member) {
        $conversation->participants()->attach($member->id, [
            'role' => 'member',
            'joined_at' => now(),
        ]);
    }

    return $conversation;
}

beforeEach(function () {
    Event::fake();
});

it('lets a group admin rename the group and toggle invite only', function () {
    $admin = User::factory()->create();
    $conversation = groupConversation($admin);

    $this->actingAs($admin)
        ->patchJson("/chat/conversations/{$conversation->id}", [
            'name' => 'Night Owls',
            'invite_only' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Night Owls')
        ->assertJsonPath('data.invite_only', true);

    expect($conversation->fresh())
        ->name->toBe('Night Owls')
        ->invite_only->toBeTrue();
});

it('blocks non admins from changing group settings', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $conversation = groupConversation($admin, [$member]);

    $this->actingAs($member)
        ->patchJson("/chat/conversations/{$conversation->id}", ['name' => 'Hijacked'])
        ->assertForbidden();
});

it('keeps invite link joiners pending on invite only groups until approved', function () {
    $admin = User::factory()->create();
    $joiner = User::factory()->create();
    $conversation = groupConversation($admin, [], [
        'invite_only' => true,
        'invite_token' => Str::random(40),
    ]);

    $this->actingAs($joiner)
        ->getJson("/chat/join/{$conversation->invite_token}")
        ->assertOk()
        ->assertJsonPath('status', 'pending');

    expect($conversation->participants()->where('users.id', $joiner->id)->exists())->toBeFalse();

    $invite = ConversationInvite::where('conversation_id', $conversation->id)
        ->where('user_id', $joiner->id)
        ->firstOrFail();

    $this->actingAs($admin)
        ->postJson("/chat/conversations/{$conversation->id}/invites/{$invite->id}/approve")
        ->assertOk();

    expect($conversation->participants()->where('users.id', $joiner->id)->exists())->toBeTrue()
        ->and($invite->fresh()->status)->toBe(ConversationInvite::STATUS_ACCEPTED);
});

it('adds invite link joiners straight away when the group is open', function () {
    $admin = User::factory()->create();
    $joiner = User::factory()->create();
    $conversation = groupConversation($admin, [], [
        'invite_only' => false,
        'invite_token' => Str::random(40),
    ]);

    $this->actingAs($joiner)
        ->getJson("/chat/join/{$conversation->invite_token}")
        ->assertOk()
        ->assertJsonPath('status', 'joined');

    expect($conversation->participants()->where('users.id', $joiner->id)->exists())->toBeTrue();
});

it('queues directly added members on invite only groups', function () {
    $admin = User::factory()->create();
    $friend = User::factory()->create();
    $conversation = groupConversation($admin, [], ['invite_only' => true]);

    $this->actingAs($admin)
        ->postJson("/chat/conversations/{$conversation->id}/members", ['user_id' => $friend->id])
        ->assertOk()
        ->assertJsonPath('message', 'Invite sent. They stay pending until you approve them.');

    expect($conversation->participants()->where('users.id', $friend->id)->exists())->toBeFalse();
});

it('lets admins remove members but keeps members from removing others', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $other = User::factory()->create();
    $conversation = groupConversation($admin, [$member, $other]);

    $this->actingAs($member)
        ->deleteJson("/chat/conversations/{$conversation->id}/members/{$other->id}")
        ->assertForbidden();

    $this->actingAs($admin)
        ->deleteJson("/chat/conversations/{$conversation->id}/members/{$other->id}")
        ->assertOk();

    expect($conversation->participants()->where('users.id', $other->id)->exists())->toBeFalse();
});

it('exposes group admin flags and pending invites in the conversation list', function () {
    $admin = User::factory()->create();
    $joiner = User::factory()->create();
    $conversation = groupConversation($admin, [], [
        'invite_only' => true,
        'invite_token' => 'token-'.$admin->id,
    ]);

    ConversationInvite::create([
        'conversation_id' => $conversation->id,
        'user_id' => $joiner->id,
        'status' => ConversationInvite::STATUS_PENDING,
    ]);

    $payload = collect($this->actingAs($admin)->getJson('/chat/conversations')->assertOk()->json('data'))
        ->firstWhere('id', $conversation->id);

    expect($payload['is_admin'])->toBeTrue()
        ->and($payload['role'])->toBe('admin')
        ->and($payload['invite_only'])->toBeTrue()
        ->and($payload['invite_url'])->toContain($conversation->invite_token)
        ->and($payload['pending_invites'])->toHaveCount(1);
});

it('searches only the conversation the viewer belongs to', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $conversation = groupConversation($admin, [$member]);

    Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $member->id,
        'body' => 'the lighthouse is open tonight',
    ]);

    $this->actingAs($member)
        ->getJson("/chat/conversations/{$conversation->id}/search?q=lighthouse")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->actingAs($outsider)
        ->getJson("/chat/conversations/{$conversation->id}/search?q=lighthouse")
        ->assertForbidden();
});

it('lists shared media and files for participants only', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $conversation = groupConversation($admin, [$member]);

    $withImage = Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $admin->id,
        'body' => null,
    ]);
    $withImage->mediaItems()->create([
        'media_path' => 'chat/media/photo.jpg',
        'media_type' => 'image/jpeg',
        'media_kind' => 'media',
    ]);

    $withFile = Message::create([
        'conversation_id' => $conversation->id,
        'sender_id' => $admin->id,
        'body' => null,
    ]);
    $withFile->mediaItems()->create([
        'media_path' => 'chat/media/brief.pdf',
        'media_type' => 'application/pdf',
        'media_kind' => 'media',
    ]);

    $this->actingAs($member)
        ->getJson("/chat/conversations/{$conversation->id}/shared?type=media")
        ->assertOk()
        ->assertJsonPath('data.0.id', $withImage->id);

    $this->actingAs($member)
        ->getJson("/chat/conversations/{$conversation->id}/shared?type=files")
        ->assertOk()
        ->assertJsonPath('data.0.id', $withFile->id);
});

it('sends a bleep share with a note and no raw link', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $author = User::factory()->create();

    $conversation = Conversation::create([
        'is_group' => false,
        'creator_id' => $sender->id,
    ]);
    $conversation->participants()->attach([$sender->id, $recipient->id]);

    $bleep = Bleep::factory()->for($author)->create([
        'message' => 'Share me',
        'is_anonymous' => false,
        'is_nsfw' => false,
    ]);

    $this->actingAs($sender)
        ->postJson('/messages', [
            'conversation_id' => $conversation->id,
            'body' => 'Look at this',
            'shared_bleep_id' => $bleep->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Look at this')
        ->assertJsonPath('data.shared_bleep.id', $bleep->id);

    expect(Message::query()->latest('id')->first()->shared_bleep_id)->toBe($bleep->id);
});
