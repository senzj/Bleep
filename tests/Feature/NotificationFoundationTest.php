<?php

use App\Enums\NotificationAudience;
use App\Enums\NotificationCategory;
use App\Enums\NotificationType;
use App\Events\Notifications\NotificationCreated;
use App\Models\BlockedUsers;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserPreferences;
use App\Services\Notifications\NotificationPresenter;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Event;

function notifyService(): NotificationService
{
    return app(NotificationService::class);
}

it('creates a notification with audience and category derived from the type', function () {
    Event::fake();

    $actor = User::factory()->create();
    $recipient = User::factory()->create();

    $created = notifyService()->notify($recipient, NotificationType::BleepLiked, [
        'actor' => $actor,
        'url' => '/bleeps/1',
        'data' => ['excerpt' => 'Hello world'],
    ]);

    expect($created)->toBe(1);

    $notification = Notification::sole();

    expect($notification->user_id)->toBe($recipient->id)
        ->and($notification->actor_id)->toBe($actor->id)
        ->and($notification->type)->toBe(NotificationType::BleepLiked)
        ->and($notification->audience)->toBe(NotificationAudience::User)
        ->and($notification->category)->toBe(NotificationCategory::Social)
        ->and($notification->group_count)->toBe(1)
        ->and($notification->data)->toBe(['excerpt' => 'Hello world']);

    Event::assertDispatched(NotificationCreated::class, function (NotificationCreated $event) use ($recipient) {
        return $event->userId === $recipient->id
            && $event->notification['type'] === NotificationType::BleepLiked->value
            && $event->notification['read'] === false;
    });
});

it('never notifies the actor about their own action', function () {
    Event::fake();

    $user = User::factory()->create();

    $created = notifyService()->notify($user, NotificationType::BleepLiked, ['actor' => $user]);

    expect($created)->toBe(0)
        ->and(Notification::count())->toBe(0);
    Event::assertNotDispatched(NotificationCreated::class);
});

it('respects a muted category preference', function () {
    Event::fake();

    $actor = User::factory()->create();
    $recipient = User::factory()->create();
    $recipient->preferences()->create(array_merge(UserPreferences::defaults(), [
        'notify_likes' => false,
    ]));

    $created = notifyService()->notify($recipient, NotificationType::BleepLiked, ['actor' => $actor]);

    expect($created)->toBe(0)
        ->and(Notification::count())->toBe(0);
});

it('falls back to the documented defaults when a user has no preferences row', function () {
    Event::fake();

    $actor = User::factory()->create();
    $recipient = User::factory()->create();

    expect($recipient->preferences)->toBeNull()
        ->and(notifyService()->notify($recipient, NotificationType::BleepLiked, ['actor' => $actor]))->toBe(1)
        ->and(notifyService()->notify($recipient, NotificationType::CommentLiked, ['actor' => $actor]))->toBe(0);

    expect(Notification::count())->toBe(1);
});

it('delivers critical account notices even when activity notifications are muted', function () {
    Event::fake();

    $recipient = User::factory()->create();
    $recipient->preferences()->create(array_merge(UserPreferences::defaults(), [
        'notify_likes' => false,
        'notify_moderation' => false,
        'notify_account' => false,
    ]));

    $created = notifyService()->notify($recipient, NotificationType::AccountBanned, [
        'data' => ['reason' => 'Spam'],
    ]);

    expect($created)->toBe(1)
        ->and(Notification::sole()->type)->toBe(NotificationType::AccountBanned);
});

it('collapses repeated events into one grouped notification', function () {
    Event::fake();

    $first = User::factory()->create();
    $second = User::factory()->create();
    $third = User::factory()->create();
    $recipient = User::factory()->create();

    foreach ([$first, $second, $third] as $actor) {
        notifyService()->notify($recipient, NotificationType::BleepLiked, [
            'actor' => $actor,
            'group_key' => 'like:bleep:9',
            'data' => ['actor_name' => $actor->dname],
        ]);
    }

    $notification = Notification::sole();

    expect(Notification::count())->toBe(1)
        ->and($notification->group_count)->toBe(3)
        ->and($notification->actor_id)->toBe($third->id)
        ->and($notification->read_at)->toBeNull();

    $payload = NotificationPresenter::toArray($notification);

    expect($payload['title'])->toContain('and 2 others')
        ->and($payload['group_count'])->toBe(3);
});

it('does not notify across a block relationship', function () {
    Event::fake();

    $actor = User::factory()->create();
    $recipient = User::factory()->create();

    BlockedUsers::create([
        'blocker_id' => $recipient->id,
        'blocked_id' => $actor->id,
    ]);

    $created = notifyService()->notify($recipient, NotificationType::BleepLiked, ['actor' => $actor]);

    expect($created)->toBe(0)
        ->and(Notification::count())->toBe(0);
});

it('fans staff events out to moderators and admins with role audiences', function () {
    Event::fake();

    $admin = User::factory()->create(['role' => 'admin']);
    $moderator = User::factory()->create(['role' => 'moderator']);
    $member = User::factory()->create(['role' => 'user']);

    $created = notifyService()->notifyStaff(NotificationType::ReportSubmitted, [
        'group_key' => 'reportqueue:bleep:3',
        'data' => ['subject_label' => 'bleep'],
    ]);

    expect($created)->toBe(2)
        ->and(Notification::where('user_id', $member->id)->exists())->toBeFalse()
        ->and(Notification::where('user_id', $admin->id)->sole()->audience)->toBe(NotificationAudience::Admin)
        ->and(Notification::where('user_id', $moderator->id)->sole()->audience)->toBe(NotificationAudience::Moderator);
});

it('limits admin audit events to admins and excludes the acting admin', function () {
    Event::fake();

    $acting = User::factory()->create(['role' => 'admin']);
    $otherAdmin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'moderator']);

    $created = notifyService()->notifyStaff(NotificationType::AdminBanIssued, [
        'data' => ['target_name' => 'someone'],
    ], $acting);

    expect($created)->toBe(1)
        ->and(Notification::sole()->user_id)->toBe($otherAdmin->id)
        ->and(Notification::sole()->audience)->toBe(NotificationAudience::Admin);
});

it('masks the actor for anonymous subjects', function () {
    Event::fake();

    $actor = User::factory()->create(['dname' => 'Alice']);
    $recipient = User::factory()->create();

    notifyService()->notify($recipient, NotificationType::BleepLiked, [
        'actor' => $actor,
        'data' => ['anonymous' => true],
    ]);

    $payload = NotificationPresenter::toArray(Notification::sole());

    expect($payload['actor'])->toBeNull()
        ->and($payload['title'])->toBe('Someone liked your bleep');
});

it('counts unread notifications and marks them all as read', function () {
    Event::fake();

    $recipient = User::factory()->create();

    Notification::create([
        'user_id' => $recipient->id,
        'type' => NotificationType::UserFollowed,
        'audience' => NotificationAudience::User,
        'category' => NotificationCategory::Follow,
        'group_key' => 'follow:1',
        'group_count' => 1,
    ]);

    Notification::create([
        'user_id' => $recipient->id,
        'type' => NotificationType::UserFollowed,
        'audience' => NotificationAudience::User,
        'category' => NotificationCategory::Follow,
        'group_key' => 'follow:2',
        'group_count' => 1,
        'read_at' => now(),
    ]);

    $service = notifyService();

    expect($service->unreadCount($recipient))->toBe(1)
        ->and($service->markAllRead($recipient))->toBe(1)
        ->and($service->unreadCount($recipient))->toBe(0);
});
