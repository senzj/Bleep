<?php

namespace App\Services;

use App\Models\Bleep;
use App\Models\Following;
use App\Models\Repost;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class FeedService
{
    public function getFeed(Request $request, string $tab = 'for-you', ?int $page = null, ?int $perPage = null): LengthAwarePaginator
    {
        $user = Auth::user();
        $preferences = $user?->getPreferences();
        $perPage = $perPage ?? ($preferences?->bleeps_per_page ?? 15);
        $page = $page ?? (int) $request->get('page', 1);

        $followedIds = $this->getFollowedIds($user);
        $friendIds   = $this->getFriendIds($user, $followedIds);

        $tab = $this->normalizeTab($tab);

        $scopeIds = [];
        if ($tab === 'following') {
            $scopeIds = $followedIds;
        } elseif ($tab === 'friends') {
            $scopeIds = $friendIds;
        }

        // Pass followedIds so buildBaseQuery can whitelist them from the
        // private-profile filter (only applied on the "for you" tab).
        $baseQuery = $this->buildBaseQuery($preferences, $user, $followedIds, $tab);

        $this->applyScope($baseQuery, $scopeIds, $preferences);

        $bleeps = $baseQuery
            ->latest()
            ->limit(500)
            ->get();

        $sorted = $this->applyOrdering($bleeps, $preferences, $user, $followedIds, $page, $tab);

        $total  = $sorted->count();
        $offset = max(0, ($page - 1) * $perPage);
        $items  = $sorted->slice($offset, $perPage)->values();

        $this->attachFollowedReposts($items, $user?->id);

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    /**
     * Build the base Eloquent query shared by all tabs.
     *
     * Private-profile filtering is applied here at the DB level for the
     * "for you" tab only — following/friends tabs already scope to people
     * the user chose to follow, so the filter is not needed there.
     *
     * Rule: on "for you", hide bleeps from users whose preferences have
     * private_profile = true, UNLESS:
     *   (a) they are the authenticated user themselves, OR
     *   (b) the authenticated user already follows them.
     */
    protected function buildBaseQuery(?object $preferences, ?User $user, array $followedIds, string $tab)
    {
        $query = Bleep::with(['user', 'media'])
            ->withCount(['likes']);

        if ($preferences && $preferences->show_nsfw === false) {
            $query->where('is_nsfw', false);
        }

        if ($preferences && $preferences->show_anonymous_bleeps === false) {
            $query->where('is_anonymous', false);
        }

        // ── Private profile filter (for-you tab only) ─────────────────────────
        // On following/friends tabs the scope already limits to followed/mutual
        // users, so private accounts are implicitly handled there.
        if ($tab === 'for-you') {
            $query->where(function ($q) use ($user, $followedIds) {
                // Always show the authenticated user's own bleeps
                if ($user) {
                    $q->where('user_id', $user->id);
                }

                // Show bleeps from users with public profiles
                $q->orWhereDoesntHave('user.preferences', function ($pref) {
                    $pref->where('private_profile', true);
                });

                // Show bleeps from private-profile users the auth user follows
                if ($user && !empty($followedIds)) {
                    // followedIds already includes $user->id, so slice it out
                    // to avoid redundancy (not harmful but cleaner)
                    $othersFollowed = array_filter(
                        $followedIds,
                        fn ($id) => $id !== $user->id
                    );

                    if (!empty($othersFollowed)) {
                        $q->orWhere(function ($inner) use ($othersFollowed) {
                            $inner->whereIn('user_id', $othersFollowed)
                                  ->whereHas('user.preferences', function ($pref) {
                                      $pref->where('private_profile', true);
                                  });
                        });
                    }
                }
            });
        }

        return $query;
    }

    protected function applyScope($query, array $scopeIds, ?object $preferences): void
    {
        if (empty($scopeIds)) {
            return;
        }

        $query->where(function ($q) use ($scopeIds, $preferences) {
            $q->whereIn('user_id', $scopeIds);

            if ($preferences && $preferences->show_reposts_in_feed) {
                $repostedIds = Repost::whereIn('user_id', $scopeIds)
                    ->pluck('bleep_id')
                    ->unique()
                    ->values()
                    ->toArray();

                if (!empty($repostedIds)) {
                    $q->orWhereIn('id', $repostedIds);
                }
            }
        });
    }

    protected function applyOrdering(Collection $bleeps, ?object $preferences, ?User $user, array $followedIds, int $page, string $tab): Collection
    {
        if ($bleeps->isEmpty()) {
            return collect();
        }

        $timezone = $user?->timezone ?? 'UTC';
        $today    = now($timezone)->toDateString();

        $sortMode       = $preferences?->default_feed_sort ?? 'newest';
        $followedLookup = array_flip($followedIds);

        $buckets = $bleeps->groupBy(function ($bleep) use ($timezone) {
            return $bleep->created_at->timezone($timezone)->toDateString();
        });

        $dayKeys = $buckets->keys()->sortDesc()->values();
        if ($dayKeys->contains($today)) {
            $dayKeys = $dayKeys->reject(fn ($day) => $day === $today)->prepend($today)->values();
        }

        $ordered = collect();

        foreach ($dayKeys as $day) {
            $bucket = $buckets->get($day, collect());

            if ($sortMode === 'popular') {
                $sortedBucket = $bucket->sort(function ($a, $b) {
                    if ($a->likes_count === $b->likes_count) {
                        return $b->created_at <=> $a->created_at;
                    }
                    return $b->likes_count <=> $a->likes_count;
                })->values();
            } elseif ($sortMode === 'following') {
                $sortedBucket = $bucket->sort(function ($a, $b) use ($followedLookup) {
                    $aFollowed = isset($followedLookup[$a->user_id]) ? 1 : 0;
                    $bFollowed = isset($followedLookup[$b->user_id]) ? 1 : 0;
                    if ($aFollowed === $bFollowed) {
                        return $b->created_at <=> $a->created_at;
                    }
                    return $bFollowed <=> $aFollowed;
                })->values();
            } else {
                $sortedBucket = $bucket->sortByDesc('created_at')->values();
            }

            $seed     = ($user?->id ?? 'guest') . ':' . $day . ':' . $page . ':' . $tab;
            $shuffled = $this->shuffleLightly($sortedBucket, $seed, 5);

            $ordered = $ordered->concat($shuffled);
        }

        return $ordered->values();
    }

    protected function shuffleLightly(Collection $items, string $seed, int $chunkSize = 5): Collection
    {
        if ($items->count() <= 1) {
            return $items;
        }

        return $items->chunk($chunkSize)->flatMap(function ($chunk, $chunkIndex) use ($seed) {
            return $chunk->sortBy(function ($bleep) use ($seed, $chunkIndex) {
                $hash = crc32($seed . ':' . $chunkIndex . ':' . $bleep->id);
                return $hash / 0xffffffff;
            })->values();
        })->values();
    }

    protected function attachFollowedReposts(Collection $bleeps, ?int $userId): void
    {
        if (!$userId) {
            return;
        }

        $bleeps->transform(function ($bleep) use ($userId) {
            $bleep->followedReposts = Repost::visibleToUser($userId, $bleep->id);
            return $bleep;
        });
    }

    protected function normalizeTab(string $tab): string
    {
        $tab = strtolower(trim($tab));
        if (in_array($tab, ['bleep', 'for-you', 'foryou'], true)) {
            return 'for-you';
        }
        if (in_array($tab, ['following', 'friends'], true)) {
            return $tab;
        }
        return 'for-you';
    }

    protected function getFollowedIds(?User $user): array
    {
        if (!$user) {
            return [];
        }

        return Following::where('follower_id', $user->id)
            ->pluck('followed_id')
            ->push($user->id)
            ->unique()
            ->values()
            ->toArray();
    }

    protected function getFriendIds(?User $user, array $followedIds): array
    {
        if (!$user) {
            return [];
        }

        $followerIds = Following::where('followed_id', $user->id)
            ->pluck('follower_id')
            ->toArray();

        return array_values(array_unique(array_intersect($followedIds, $followerIds)));
    }
}
