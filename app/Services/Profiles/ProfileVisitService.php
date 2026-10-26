<?php

namespace App\Services\Profiles;

use App\Enums\NotificationType;
use App\Models\ProfileVisit;
use App\Models\User;
use App\Models\UserPreferences;
use App\Services\Notifications\NotificationService;

/**
 * Records profile views once per viewer per day and turns the first view of
 * the day into a notification.
 *
 * Two rules keep this cheap and private:
 * - nothing is written unless the profile owner opted in to visit notices;
 * - the daily row is created with a single `insertOrIgnore` against a unique
 *   index, so concurrent views cannot duplicate it.
 */
class ProfileVisitService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * @return bool true when this view created a new daily row
     */
    public function record(User $owner, User $visitor): bool
    {
        if ((int) $owner->id === (int) $visitor->id) {
            return false;
        }

        if ($visitor->isBlockedOrHasBlocked($owner)) {
            return false;
        }

        if (! $this->visitsEnabled($owner)) {
            return false;
        }

        $today = now()->toDateString();

        $inserted = ProfileVisit::query()->insertOrIgnore([
            'profile_user_id' => $owner->id,
            'visitor_id' => $visitor->id,
            'visited_on' => $today,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 0) {
            return false;
        }

        $this->notifications->notify($owner, NotificationType::ProfileVisited, [
            'actor' => $visitor,
            'url' => route('user.profile', $visitor->username),
            'group_key' => 'visit:'.$visitor->id.':'.$today,
        ]);

        return true;
    }

    private function visitsEnabled(User $owner): bool
    {
        return (bool) ($owner->preferences?->notify_profile_visits
            ?? UserPreferences::defaults()['notify_profile_visits']);
    }
}
