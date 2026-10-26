<?php

use App\Enums\NotificationType;
use App\Models\BlockedUsers;
use App\Models\Notification;
use App\Models\ProfileVisit;
use App\Models\User;
use App\Models\UserPreferences;
use Illuminate\Support\Facades\Event;

function optInToProfileVisits(User $user): void
{
    $user->preferences()->create(array_merge(UserPreferences::defaults(), [
        'notify_profile_visits' => true,
    ]));
}

beforeEach(function () {
    Event::fake();
});

it('records a visit and notifies the owner once per viewer per day', function () {
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    optInToProfileVisits($owner);

    $this->actingAs($visitor)
        ->get(route('user.profile', $owner->username))
        ->assertOk();

    $visit = ProfileVisit::sole();
    $notification = Notification::sole();

    expect($visit->profile_user_id)->toBe($owner->id)
        ->and($visit->visitor_id)->toBe($visitor->id)
        ->and($visit->visited_on->toDateString())->toBe(now()->toDateString())
        ->and($notification->user_id)->toBe($owner->id)
        ->and($notification->actor_id)->toBe($visitor->id)
        ->and($notification->type)->toBe(NotificationType::ProfileVisited)
        ->and($notification->url)->toBe(route('user.profile', $visitor->username))
        ->and($notification->group_count)->toBe(1);
});

it('records nothing when the owner has not opted in', function () {
    $owner = User::factory()->create();
    $visitor = User::factory()->create();

    $this->actingAs($visitor)
        ->get(route('user.profile', $owner->username))
        ->assertOk();

    expect(ProfileVisit::count())->toBe(0)
        ->and(Notification::count())->toBe(0);
});

it('deduplicates repeat visits on the same day', function () {
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    optInToProfileVisits($owner);

    $this->actingAs($visitor)->get(route('user.profile', $owner->username))->assertOk();
    $this->actingAs($visitor)->get(route('user.profile', $owner->username))->assertOk();

    expect(ProfileVisit::count())->toBe(1)
        ->and(Notification::count())->toBe(1);
});

it('records a new visit the next day', function () {
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    optInToProfileVisits($owner);

    $this->actingAs($visitor)->get(route('user.profile', $owner->username))->assertOk();

    $this->travelTo(now()->addDay());

    $this->actingAs($visitor)->get(route('user.profile', $owner->username))->assertOk();

    expect(ProfileVisit::count())->toBe(2)
        ->and(Notification::count())->toBe(2);
});

it('ignores visits to your own profile', function () {
    $owner = User::factory()->create();
    optInToProfileVisits($owner);

    $this->actingAs($owner)
        ->get(route('user.profile', $owner->username))
        ->assertOk();

    expect(ProfileVisit::count())->toBe(0)
        ->and(Notification::count())->toBe(0);
});

it('ignores guest views because there is no visitor to report', function () {
    $owner = User::factory()->create();
    optInToProfileVisits($owner);

    $this->get(route('user.profile', $owner->username))->assertOk();

    expect(ProfileVisit::count())->toBe(0)
        ->and(Notification::count())->toBe(0);
});

it('keeps blocked viewers out of the visit log', function () {
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    optInToProfileVisits($owner);

    BlockedUsers::create([
        'blocker_id' => $owner->id,
        'blocked_id' => $visitor->id,
    ]);

    $this->actingAs($visitor)
        ->get(route('user.profile', $owner->username))
        ->assertOk();

    expect(ProfileVisit::count())->toBe(0)
        ->and(Notification::count())->toBe(0);
});

it('does not count the paginated profile fragments as visits', function () {
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    optInToProfileVisits($owner);

    $this->actingAs($visitor)
        ->getJson(route('user.bleeps', $owner->username))
        ->assertOk();

    expect(ProfileVisit::count())->toBe(0)
        ->and(Notification::count())->toBe(0);
});
