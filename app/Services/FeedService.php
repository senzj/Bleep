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

        $baseQuery = $this->buildBaseQuery($preferences);
        $tab = $this->normalizeTab($tab);

        $followedIds = $this->getFollowedIds($user);
        $friendIds = $this->getFriendIds($user, $followedIds);

        $scopeIds = [];
        if ($tab === 'following') {
            $scopeIds = $followedIds;
        } elseif ($tab === 'friends') {
            $scopeIds = $friendIds;
        }

        $this->applyScope($baseQuery, $scopeIds, $preferences);

        $bleeps = $baseQuery
            ->latest()
            ->limit(500)
            ->get();

        $sorted = $this->applyOrdering($bleeps, $preferences, $user, $followedIds, $page, $tab);

        $total = $sorted->count();
        $offset = max(0, ($page - 1) * $perPage);
        $items = $sorted->slice($offset, $perPage)->values();

        $this->attachFollowedReposts($items, $user?->id);

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    protected function buildBaseQuery(?object $preferences)
    {
        $query = Bleep::with(['user', 'media'])
            ->withCount(['likes']);

        if ($preferences && $preferences->show_nsfw === false) {
            $query->where('is_nsfw', false);
        }

        if ($preferences && $preferences->show_anonymous_bleeps === false) {
            $query->where('is_anonymous', false);
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
        $today = now($timezone)->toDateString();

        $sortMode = $preferences?->default_feed_sort ?? 'newest';
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

            $seed = ($user?->id ?? 'guest') . ':' . $day . ':' . $page . ':' . $tab;
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
        if ($tab === 'bleep' || $tab === 'for-you' || $tab === 'foryou') {
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

        $friends = array_values(array_intersect($followedIds, $followerIds));

        return array_values(array_unique($friends));
    }
}
