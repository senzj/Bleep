<?php

use App\Enums\NotificationType;
use App\Models\Bleep;
use App\Models\BlockedUsers;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Share;
use App\Models\User;
use App\Models\UserPreferences;
use Illuminate\Support\Facades\Event;

function sharedBleepConversation(User $a, User $b): Conversation
{
    $conversation = Conversation::create([
        'is_group' => false,
        'creator_id' => $a->id,
    ]);

    $conversation->participants()->attach([$a->id, $b->id]);

    return $conversation;
}

beforeEach(function () {
    Event::fake();
});

it('accepts a bleep-only chat message and returns the shared card payload', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $author = User::factory()->create(['dname' => 'Nova Star']);
    $conversation = sharedBleepConversation($sender, $recipient);

    $bleep = Bleep::factory()->for($author)->create([
        'message' => 'A shareable thought',
        'is_anonymous' => false,
        'is_nsfw' => false,
    ]);

    $response = $this->actingAs($sender)
        ->postJson('/messages', [
            'conversation_id' => $conversation->id,
            'shared_bleep_id' => $bleep->id,
        ])
        ->assertCreated();

    $message = Message::query()->sole();

    expect($message->body)->toBeNull()
        ->and($message->shared_bleep_id)->toBe($bleep->id)
        ->and($response->json('data.shared_bleep.available'))->toBeTrue()
        ->and($response->json('data.shared_bleep.url'))->toBe(route('post', $bleep->id))
        ->and($response->json('data.shared_bleep.excerpt'))->toBe('A shareable thought')
        ->and($response->json('data.shared_bleep.author_name'))->toBe('Nova Star')
        ->and($response->json('data.shared_bleep.author_username'))->toBe($author->username);
});

it('masks anonymous authors when a bleep is shared in chat', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $author = User::factory()->create(['dname' => 'Hidden Person']);
    $conversation = sharedBleepConversation($sender, $recipient);

    $bleep = Bleep::factory()->for($author)->anonymous()->create([
        'is_nsfw' => false,
    ]);

    $response = $this->actingAs($sender)
        ->postJson('/messages', [
            'conversation_id' => $conversation->id,
            'shared_bleep_id' => $bleep->id,
        ])
        ->assertCreated();

    expect($response->json('data.shared_bleep.author_name'))->toBe('Anonymous')
        ->and($response->json('data.shared_bleep.author_username'))->toBeNull()
        ->and($response->json('data.shared_bleep.author_profile_picture_url'))->toBeNull()
        ->and(json_encode($response->json('data.shared_bleep')))->not->toContain('Hidden Person');
});

it('rejects sharing a bleep the sender cannot see', function (Closure $makeInvisible) {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = sharedBleepConversation($sender, $recipient);

    $bleep = $makeInvisible($sender);

    $this->actingAs($sender)
        ->postJson('/messages', [
            'conversation_id' => $conversation->id,
            'shared_bleep_id' => $bleep->id,
        ])
        ->assertStatus(422);

    expect(Message::count())->toBe(0);
})->with([
    'soft deleted' => [function (User $sender) {
        $bleep = Bleep::factory()->create(['is_anonymous' => false]);
        $bleep->delete();

        return $bleep;
    }],
    'private profile without follow' => [function (User $sender) {
        $author = User::factory()->create();
        $author->preferences()->create([
            ...UserPreferences::defaults(),
            'private_profile' => true,
        ]);

        return Bleep::factory()->for($author)->create(['is_anonymous' => false]);
    }],
    'blocked author' => [function (User $sender) {
        $author = User::factory()->create();
        BlockedUsers::create([
            'blocker_id' => $author->id,
            'blocked_id' => $sender->id,
        ]);

        return Bleep::factory()->for($author)->create(['is_anonymous' => false]);
    }],
]);

it('renders a shared bleep as unavailable once it is deleted', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = sharedBleepConversation($sender, $recipient);

    $bleep = Bleep::factory()->create(['is_anonymous' => false]);

    $this->actingAs($sender)
        ->postJson('/messages', [
            'conversation_id' => $conversation->id,
            'shared_bleep_id' => $bleep->id,
        ])
        ->assertCreated();

    $messageId = Message::query()->sole()->id;

    $bleep->delete();

    $response = $this->actingAs($recipient)
        ->getJson("/chat/conversations/{$conversation->id}/messages")
        ->assertOk();

    $message = collect($response->json('data'))->firstWhere('id', $messageId);

    expect($message['shared_bleep']['available'])->toBeFalse()
        ->and($message['shared_bleep']['id'])->toBe($bleep->id)
        ->and($message['shared_bleep'])->not->toHaveKey('url')
        ->and($message['shared_bleep'])->not->toHaveKey('excerpt');
});

it('exposes the shared bleep on the conversation list preview', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = sharedBleepConversation($sender, $recipient);

    $bleep = Bleep::factory()->create([
        'message' => 'Preview me',
        'is_anonymous' => false,
    ]);

    $this->actingAs($sender)
        ->postJson('/messages', [
            'conversation_id' => $conversation->id,
            'shared_bleep_id' => $bleep->id,
        ])
        ->assertCreated();

    $response = $this->actingAs($recipient)
        ->getJson('/chat/conversations')
        ->assertOk();

    $preview = collect($response->json('data'))->firstWhere('id', $conversation->id);

    expect($preview['last_message']['shared_bleep']['available'])->toBeTrue()
        ->and($preview['last_message']['shared_bleep']['excerpt'])->toBe('Preview me');
});

it('does not create a link share row or share notification for chat shares', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $author = User::factory()->create();
    $conversation = sharedBleepConversation($sender, $recipient);

    $bleep = Bleep::factory()->for($author)->create(['is_anonymous' => false]);

    $this->actingAs($sender)
        ->postJson('/messages', [
            'conversation_id' => $conversation->id,
            'shared_bleep_id' => $bleep->id,
        ])
        ->assertCreated();

    expect(Share::count())->toBe(0)
        ->and(Notification::where('type', NotificationType::BleepShared)->count())->toBe(0);
});

it('includes image thumbnails for safe bleeps and omits them for NSFW bleeps', function () {
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = sharedBleepConversation($sender, $recipient);

    $safe = Bleep::factory()->create(['is_anonymous' => false, 'is_nsfw' => false]);
    $safe->media()->create([
        'path' => 'bleeps/safe.jpg',
        'type' => 'image',
    ]);

    $nsfw = Bleep::factory()->nsfw()->create(['is_anonymous' => false]);
    $nsfw->media()->create([
        'path' => 'bleeps/nsfw.jpg',
        'type' => 'image',
    ]);

    $safeResponse = $this->actingAs($sender)
        ->postJson('/messages', [
            'conversation_id' => $conversation->id,
            'shared_bleep_id' => $safe->id,
        ])
        ->assertCreated();

    $nsfwResponse = $this->actingAs($sender)
        ->postJson('/messages', [
            'conversation_id' => $conversation->id,
            'shared_bleep_id' => $nsfw->id,
        ])
        ->assertCreated();

    expect($safeResponse->json('data.shared_bleep.media_url'))->toContain('/storage/bleeps/safe.jpg')
        ->and($safeResponse->json('data.shared_bleep.is_nsfw'))->toBeFalse()
        ->and($nsfwResponse->json('data.shared_bleep.media_url'))->toBeNull()
        ->and($nsfwResponse->json('data.shared_bleep.is_nsfw'))->toBeTrue();
});
