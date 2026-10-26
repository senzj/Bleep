<?php

use App\Enums\NotificationAudience;
use App\Enums\NotificationCategory;
use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Event;

function inboxNotification(User $user, array $attributes = []): Notification
{
    return Notification::create(array_merge([
        'user_id' => $user->id,
        'type' => NotificationType::UserFollowed,
        'audience' => NotificationAudience::User,
        'category' => NotificationCategory::Follow,
        'group_key' => null,
        'group_count' => 1,
        'data' => [],
    ], $attributes));
}

beforeEach(function () {
    Event::fake();
});

it('requires authentication for the inbox and its api', function () {
    $this->get(route('notifications.index'))->assertRedirect(route('login'));
    $this->getJson(route('notifications.data'))->assertUnauthorized();
    $this->getJson(route('notifications.unread-count'))->assertUnauthorized();
    $this->postJson(route('notifications.read-all'))->assertUnauthorized();
});

it('renders only the signed in user notifications', function () {
    $user = User::factory()->create();
    $actor = User::factory()->create(['dname' => 'Alice']);

    inboxNotification($user, [
        'actor_id' => $actor->id,
        'data' => ['actor_name' => 'Alice'],
    ]);
    inboxNotification(User::factory()->create(), [
        'data' => ['actor_name' => 'Mallory'],
    ]);

    $response = $this->actingAs($user)->get(route('notifications.index'));

    $response->assertOk()
        ->assertSee('Alice followed you')
        ->assertDontSee('Mallory followed you');
});

it('filters the inbox by unread state', function () {
    $user = User::factory()->create();

    inboxNotification($user, ['data' => ['actor_name' => 'UnreadActor']]);
    inboxNotification($user, [
        'data' => ['actor_name' => 'ReadActor'],
        'read_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('notifications.index', ['filter' => 'unread']));

    $response->assertOk()
        ->assertSee('UnreadActor followed you')
        ->assertDontSee('ReadActor followed you');
});

it('shows the staff filter and staff rows only to staff', function () {
    $member = User::factory()->create(['role' => 'user']);
    $moderator = User::factory()->create(['role' => 'moderator']);

    inboxNotification($member, [
        'data' => ['actor_name' => 'Alice'],
    ]);

    inboxNotification($moderator, [
        'type' => NotificationType::ReportSubmitted,
        'audience' => NotificationAudience::Moderator,
        'category' => NotificationCategory::Moderation,
        'data' => ['subject_label' => 'bleep'],
    ]);

    $memberPage = $this->actingAs($member)->get(route('notifications.index'));

    $memberPage->assertOk()
        ->assertDontSee('filter=staff', false)
        ->assertDontSee('A new report needs review');

    $moderatorPage = $this->actingAs($moderator)->get(route('notifications.index', ['filter' => 'staff']));

    $moderatorPage->assertOk()
        ->assertSee('filter=staff', false)
        ->assertSee('A new report needs review');
});

it('ignores an unknown filter', function () {
    $user = User::factory()->create();

    inboxNotification($user, ['data' => ['actor_name' => 'Alice']]);

    $this->actingAs($user)
        ->get(route('notifications.index', ['filter' => 'nonsense']))
        ->assertOk()
        ->assertSee('Alice followed you');
});

it('returns the latest notifications and unread count for the bell', function () {
    $user = User::factory()->create();

    foreach (range(1, 12) as $index) {
        inboxNotification($user, [
            'data' => ['actor_name' => "Actor {$index}"],
            'read_at' => $index % 2 === 0 ? now() : null,
        ]);
    }

    $response = $this->actingAs($user)->getJson(route('notifications.data'));

    $response->assertOk()
        ->assertJsonCount(8, 'notifications')
        ->assertJsonPath('unread_count', 6);

    $this->actingAs($user)
        ->getJson(route('notifications.data', ['limit' => 100]))
        ->assertOk()
        ->assertJsonCount(12, 'notifications');
});

it('returns the unread count only', function () {
    $user = User::factory()->create();

    inboxNotification($user);
    inboxNotification($user);

    $this->actingAs($user)
        ->getJson(route('notifications.unread-count'))
        ->assertOk()
        ->assertJsonPath('unread_count', 2);
});

it('marks one notification as read without touching updated_at', function () {
    $user = User::factory()->create();
    $notification = inboxNotification($user);
    $updatedAt = $notification->updated_at;

    $this->actingAs($user)
        ->postJson(route('notifications.read', ['notification' => $notification->id]))
        ->assertOk()
        ->assertJsonPath('unread_count', 0);

    $notification->refresh();

    expect($notification->read_at)->not->toBeNull()
        ->and($notification->updated_at->equalTo($updatedAt))->toBeTrue();
});

it('does not let a user mark someone else notification as read', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $notification = inboxNotification($owner);

    $this->actingAs($intruder)
        ->postJson(route('notifications.read', ['notification' => $notification->id]))
        ->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

it('marks every notification as read', function () {
    $user = User::factory()->create();

    inboxNotification($user);
    inboxNotification($user);
    inboxNotification(User::factory()->create());

    $this->actingAs($user)
        ->postJson(route('notifications.read-all'))
        ->assertOk()
        ->assertJsonPath('updated', 2)
        ->assertJsonPath('unread_count', 0);

    expect(Notification::where('user_id', $user->id)->whereNull('read_at')->count())->toBe(0);
});
