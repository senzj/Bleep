<?php

use App\Enums\NotificationType;
use App\Models\Bleep;
use App\Models\BlockedUsers;
use App\Models\Comments;
use App\Models\FollowRequest;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserPreferences;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Event;

function socialBleep(User $author, array $attributes = []): Bleep
{
    return Bleep::factory()->create(array_merge([
        'user_id' => $author->id,
        'message' => 'Original thought for testing',
        'is_anonymous' => false,
    ], $attributes));
}

function enablePreference(User $user, string $key): void
{
    $user->preferences()->create(array_merge(UserPreferences::defaults(), [$key => true]));
}

beforeEach(function () {
    Event::fake();
});

it('notifies the bleep author when someone likes their bleep', function () {
    $author = User::factory()->create();
    $liker = User::factory()->create();
    $bleep = socialBleep($author);

    $this->actingAs($liker)
        ->postJson("/bleeps/{$bleep->id}/like")
        ->assertOk()
        ->assertJsonPath('liked', true);

    $notification = Notification::sole();

    expect($notification->user_id)->toBe($author->id)
        ->and($notification->actor_id)->toBe($liker->id)
        ->and($notification->type)->toBe(NotificationType::BleepLiked)
        ->and($notification->group_key)->toBe('like:bleep:'.$bleep->id)
        ->and($notification->data['excerpt'])->toBe('Original thought for testing');
});

it('does not notify when the author likes their own bleep', function () {
    $author = User::factory()->create();
    $bleep = socialBleep($author);

    $this->actingAs($author)->postJson("/bleeps/{$bleep->id}/like")->assertOk();

    expect(Notification::count())->toBe(0);
});

it('groups multiple likes on the same bleep into one notification', function () {
    $author = User::factory()->create();
    $bleep = socialBleep($author);

    foreach ([User::factory()->create(), User::factory()->create()] as $liker) {
        $this->actingAs($liker)->postJson("/bleeps/{$bleep->id}/like")->assertOk();
    }

    expect(Notification::count())->toBe(1)
        ->and(Notification::sole()->group_count)->toBe(2);
});

it('notifies the author when a bleep is commented on', function () {
    $author = User::factory()->create();
    $commenter = User::factory()->create();
    $bleep = socialBleep($author);

    $this->actingAs($commenter)
        ->postJson("/bleeps/comments/{$bleep->id}/post", [
            'message' => 'This is a great point',
            'is_anonymous' => false,
        ])
        ->assertSuccessful();

    $notification = Notification::sole();

    expect($notification->user_id)->toBe($author->id)
        ->and($notification->type)->toBe(NotificationType::BleepCommented)
        ->and($notification->group_key)->toBe('comment:bleep:'.$bleep->id)
        ->and($notification->data['excerpt'])->toBe('This is a great point');
});

it('hides the actor for anonymous comments', function () {
    $author = User::factory()->create();
    $commenter = User::factory()->create(['dname' => 'Secret Squirrel']);
    $bleep = socialBleep($author);

    $this->actingAs($commenter)
        ->postJson("/bleeps/comments/{$bleep->id}/post", [
            'message' => 'Anonymous thought',
            'is_anonymous' => true,
        ])
        ->assertSuccessful();

    $notification = Notification::sole();

    expect($notification->data['anonymous'])->toBeTrue()
        ->and($notification->type)->toBe(NotificationType::BleepCommented);
});

it('notifies the parent comment author on replies', function () {
    $author = User::factory()->create();
    $commenter = User::factory()->create();
    $replier = User::factory()->create();
    $bleep = socialBleep($author);

    $parent = Comments::factory()->create([
        'user_id' => $commenter->id,
        'bleep_id' => $bleep->id,
        'is_anonymous' => false,
        'message' => 'Parent comment',
    ]);

    $this->actingAs($replier)
        ->postJson("/bleeps/comments/{$parent->id}/replies", [
            'message' => 'Replying to you',
            'is_anonymous' => false,
        ])
        ->assertSuccessful();

    $notification = Notification::where('type', NotificationType::CommentReplied)->sole();

    expect($notification->user_id)->toBe($commenter->id)
        ->and($notification->group_key)->toBe('reply:comment:'.$parent->id)
        ->and($notification->data['excerpt'])->toBe('Replying to you');
});

it('keeps comment likes quiet by default', function () {
    $commentAuthor = User::factory()->create();
    $liker = User::factory()->create();
    $bleep = socialBleep(User::factory()->create());

    $comment = Comments::factory()->create([
        'user_id' => $commentAuthor->id,
        'bleep_id' => $bleep->id,
        'is_anonymous' => false,
    ]);

    $this->actingAs($liker)
        ->postJson("/bleeps/comments/{$comment->id}/likes")
        ->assertOk();

    expect(Notification::where('type', NotificationType::CommentLiked)->count())->toBe(0);
});

it('notifies the comment author when comment likes are enabled', function () {
    $commentAuthor = User::factory()->create();
    enablePreference($commentAuthor, 'notify_comment_likes');

    $liker = User::factory()->create();
    $bleep = socialBleep(User::factory()->create());
    $comment = Comments::factory()->create([
        'user_id' => $commentAuthor->id,
        'bleep_id' => $bleep->id,
        'is_anonymous' => false,
    ]);

    $this->actingAs($liker)
        ->postJson("/bleeps/comments/{$comment->id}/likes")
        ->assertOk();

    expect(Notification::sole()->user_id)->toBe($commentAuthor->id)
        ->and(Notification::sole()->type)->toBe(NotificationType::CommentLiked);
});

it('notifies the author when a bleep is reposted', function () {
    $author = User::factory()->create();
    $reposter = User::factory()->create();
    $bleep = socialBleep($author);

    $this->actingAs($reposter)
        ->postJson("/bleeps/{$bleep->id}/repost")
        ->assertOk();

    expect(Notification::sole()->type)->toBe(NotificationType::BleepReposted)
        ->and(Notification::sole()->user_id)->toBe($author->id);
});

it('notifies the author once per day when a bleep link is shared', function () {
    $author = User::factory()->create();
    $sharer = User::factory()->create();
    $bleep = socialBleep($author);

    $this->actingAs($sharer)->postJson("/bleeps/{$bleep->id}/share")->assertOk();
    $this->actingAs($sharer)->postJson("/bleeps/{$bleep->id}/share")->assertOk();

    expect(Notification::where('type', NotificationType::BleepShared)->count())->toBe(1);
});

it('notifies a user when they gain a follower', function () {
    $target = User::factory()->create();
    $follower = User::factory()->create();

    $this->actingAs($follower)
        ->postJson("/bleeper/{$target->id}/follow")
        ->assertOk()
        ->assertJsonPath('following', true);

    expect(Notification::sole()->type)->toBe(NotificationType::UserFollowed)
        ->and(Notification::sole()->user_id)->toBe($target->id)
        ->and(Notification::sole()->group_key)->toBe('follow:'.$follower->id);
});

it('keeps unfollow notices quiet by default and respects the opt-in', function () {
    $target = User::factory()->create();
    $follower = User::factory()->create();

    $this->actingAs($follower)->postJson("/bleeper/{$target->id}/follow")->assertOk();
    $this->actingAs($follower)->postJson("/bleeper/{$target->id}/follow")->assertOk();

    expect(Notification::where('type', NotificationType::UserUnfollowed)->count())->toBe(0);

    $quietTarget = User::factory()->create();
    enablePreference($quietTarget, 'notify_unfollows');
    $other = User::factory()->create();

    $this->actingAs($other)->postJson("/bleeper/{$quietTarget->id}/follow")->assertOk();
    $this->actingAs($other)->postJson("/bleeper/{$quietTarget->id}/follow")->assertOk();

    expect(Notification::where('type', NotificationType::UserUnfollowed)
        ->where('user_id', $quietTarget->id)->count())->toBe(1);
});

it('notifies a target when a follow request is sent', function () {
    $target = User::factory()->create();
    $requester = User::factory()->create();

    $this->actingAs($requester)
        ->postJson('/api/follow-requests', ['target_id' => $target->id])
        ->assertOk();

    expect(Notification::sole()->type)->toBe(NotificationType::FollowRequested)
        ->and(Notification::sole()->user_id)->toBe($target->id);
});

it('notifies the requester when a follow request is accepted', function () {
    $target = User::factory()->create();
    $requester = User::factory()->create();

    $followRequest = FollowRequest::create([
        'requester_id' => $requester->id,
        'target_id' => $target->id,
        'status' => 'pending',
    ]);

    $this->actingAs($target)
        ->postJson("/api/follow-requests/{$followRequest->id}/accept")
        ->assertOk();

    $notification = Notification::where('type', NotificationType::FollowAccepted)->sole();

    expect($notification->user_id)->toBe($requester->id)
        ->and($notification->actor_id)->toBe($target->id);
});

it('does not notify across a block relationship', function () {
    $author = User::factory()->create();
    $liker = User::factory()->create();
    $bleep = socialBleep($author);

    BlockedUsers::create([
        'blocker_id' => $author->id,
        'blocked_id' => $liker->id,
    ]);

    // The like endpoint is blocked before the notification layer, so target the
    // service path directly to prove the suppression rule holds.
    app(NotificationService::class)->notify(
        $author,
        NotificationType::BleepLiked,
        ['actor' => $liker]
    );

    expect(Notification::count())->toBe(0);
});
