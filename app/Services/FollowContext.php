<?php

namespace App\Services;

use App\Models\Following;
use App\Models\FollowRequest;
use App\Models\User;

/**
 * Request-scoped view of the authenticated user's follow graph.
 *
 * Rendering a feed used to run three EXISTS queries per bleep card
 * (isFollowing + isFriend + hasSentRequestTo). This class loads each
 * data set once per request and answers the same questions from memory.
 */
class FollowContext
{
    /** @var array<int, true>|null */
    private ?array $followingIds = null;

    /** @var array<int, true>|null */
    private ?array $followerIds = null;

    /** @var array<int, true>|null */
    private ?array $pendingRequestTargetIds = null;

    public function __construct(private readonly ?User $viewer) {}

    public function follows(int $userId): bool
    {
        return isset($this->followingIds()[$userId]);
    }

    public function followedBy(int $userId): bool
    {
        return isset($this->followerIds()[$userId]);
    }

    public function isFriend(int $userId): bool
    {
        return $this->follows($userId) && $this->followedBy($userId);
    }

    public function hasSentRequestTo(int $userId): bool
    {
        return isset($this->pendingRequestTargetIds()[$userId]);
    }

    /**
     * @return array<int, true>
     */
    private function followingIds(): array
    {
        if (! $this->viewer) {
            return [];
        }

        if ($this->followingIds === null) {
            $this->followingIds = Following::query()
                ->where('follower_id', $this->viewer->id)
                ->pluck('followed_id')
                ->mapWithKeys(fn ($id) => [(int) $id => true])
                ->all();
        }

        return $this->followingIds;
    }

    /**
     * @return array<int, true>
     */
    private function followerIds(): array
    {
        if (! $this->viewer) {
            return [];
        }

        if ($this->followerIds === null) {
            $this->followerIds = Following::query()
                ->where('followed_id', $this->viewer->id)
                ->pluck('follower_id')
                ->mapWithKeys(fn ($id) => [(int) $id => true])
                ->all();
        }

        return $this->followerIds;
    }

    /**
     * @return array<int, true>
     */
    private function pendingRequestTargetIds(): array
    {
        if (! $this->viewer) {
            return [];
        }

        if ($this->pendingRequestTargetIds === null) {
            $this->pendingRequestTargetIds = FollowRequest::query()
                ->where('requester_id', $this->viewer->id)
                ->where('status', 'pending')
                ->pluck('target_id')
                ->mapWithKeys(fn ($id) => [(int) $id => true])
                ->all();
        }

        return $this->pendingRequestTargetIds;
    }
}
