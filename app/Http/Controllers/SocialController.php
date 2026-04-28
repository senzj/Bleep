<?php

namespace App\Http\Controllers;

use App\Models\Following;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class SocialController extends Controller
{
    public function peoplePage(Request $request)
    {
        if (!Auth::check()) {
            return view('pages.social.people', [
                'suggestedUsers' => collect(),
            ]);
        }

        $user = Auth::user();
        $graph = $this->getRelationshipGraph($user);
        $suggestedUsers = $this->decorateUsers(
            $this->getSuggestedUsers($user, $graph, 10),
            $graph,
            $user
        );

        return view('pages.social.people', [
            'suggestedUsers' => $suggestedUsers,
        ]);
    }

    public function searchUsers(Request $request)
    {
        $query = trim($request->get('q', ''));
        $limit = 20;

        if (! Auth::check()) {
            return response()->json([
                'html' => view('components.card.users', [
                    'users' => collect(),
                    'emptyMessage' => 'No suggestions available at the moment.',
                ])->render(),
            ]);
        }

        $user = Auth::user();
        $currentUserId = $user->id;

        $graph = $this->getRelationshipGraph($user);

        if ($query === '') {
            $suggestedUsers = $this->decorateUsers(
                $this->getSuggestedUsers($user, $graph, $limit),
                $graph,
                $user
            );

            return response()->json(
                [
                    'html' => view('components.card.users', [
                        'users' => $suggestedUsers,
                        'emptyMessage' => 'No suggestions available at the moment.',
                    ])->render(),
                ]
            );
        }

        $localPart = explode('@', $query)[0];

        $users = User::where('id', '!=', $currentUserId)
            ->where(function ($q) use ($query, $localPart) {
                $q->where('username', 'like', "%{$query}%")
                    ->orWhere('dname', 'like', "%{$query}%")
                    ->orWhereRaw("SUBSTRING_INDEX(email, '@', 1) like ?", ["%{$localPart}%"]);
            })
            ->with('preferences')
            ->withCount('followers')
            ->get();

        $sorted = $users
            ->sortByDesc(function ($candidate) use ($graph) {
                $direct = in_array($candidate->id, $graph['direct'], true) ? 1 : 0;
                $second = in_array($candidate->id, $graph['second'], true) ? 1 : 0;
                $third = in_array($candidate->id, $graph['third'], true) ? 1 : 0;
                $following = in_array($candidate->id, $graph['following'], true) ? 1 : 0;
                $followers = $candidate->followers_count ?? 0;

                return ($direct * 1000000) + ($second * 100000) + ($third * 10000) + ($following * 1000) + min($followers, 999);
            })
            ->take($limit);

        $sorted = $this->decorateUsers($sorted, $graph, $user);

        return response()->json(
            [
                'html' => view('components.card.users', [
                    'users' => $sorted,
                    'emptyMessage' => 'No users found. Try a different search term.',
                ])->render(),
            ]
        );
    }

    public function relationships(Request $request, string $username, string $type)
    {
        $profileUser = User::where('username', $username)->with('preferences')->firstOrFail();
        $authUser = Auth::user();

        $isOwnProfile = Auth::check() && Auth::id() === $profileUser->id;
        $isFollowing = Auth::check() ? $authUser->isFollowing($profileUser) : false;
        $isPrivate = !$isOwnProfile && ($profileUser->preferences?->private_profile ?? false);
        $hasBlockingRelationship = Auth::check() && $authUser->isBlockedOrHasBlocked($profileUser);

        abort_if($hasBlockingRelationship || ($isPrivate && !$isFollowing && !$isOwnProfile), 403);

        if (! in_array($type, ['followers', 'following'], true)) {
            abort(404);
        }

        $query = trim($request->get('q', ''));
        $relationshipQuery = $type === 'followers'
            ? $profileUser->followers()
            : $profileUser->following();

        $users = $relationshipQuery
            ->with('preferences')
            ->when($query !== '', function ($builder) use ($query) {
                $builder->where(function ($searchQuery) use ($query) {
                    $searchQuery->where('username', 'like', "%{$query}%")
                        ->orWhere('dname', 'like', "%{$query}%");
                });
            })
            ->orderByDesc('followings.created_at')
            ->get();

        $title = $type === 'followers' ? 'Followers' : 'Following';
        $emptyMessage = $query === ''
            ? "No {$type} found yet."
            : "No {$type} match your search.";

        return response()->json([
            'title' => $title,
            'count' => $users->count(),
            'html' => view('components.card.users', [
                'users' => $users,
                'emptyMessage' => $emptyMessage,
                'showMessage' => true,
            ])->render(),
        ]);
    }

    protected function getSuggestedUsers(User $user, array $graph, int $limit): Collection
    {
        $secondDegreeUsers = collect();
        if (!empty($graph['second'])) {
            $secondDegreeUsers = User::whereIn('id', $graph['second'])
                ->with('preferences')
                ->withCount('followers')
                ->orderByDesc('followers_count')
                ->limit($limit)
                ->get();
        }

        $remainingAfterSecond = max(0, $limit - $secondDegreeUsers->count());

        $thirdDegreeUsers = collect();
        if ($remainingAfterSecond > 0 && !empty($graph['third'])) {
            $thirdDegreeUsers = User::whereIn('id', $graph['third'])
                ->with('preferences')
                ->withCount('followers')
                ->orderByDesc('followers_count')
                ->limit($remainingAfterSecond)
                ->get();
        }

        $excludeIds = array_merge(
            [$user->id],
            $graph['following'],
            $graph['direct'],
            $secondDegreeUsers->pluck('id')->all(),
            $thirdDegreeUsers->pluck('id')->all()
        );

        $remainingAfterThird = max(0, $limit - $secondDegreeUsers->count() - $thirdDegreeUsers->count());

        $randomUsers = collect();
        if ($remainingAfterThird > 0) {
            $randomUsers = User::whereNotIn('id', $excludeIds)
                ->with('preferences')
                ->withCount('followers')
                ->inRandomOrder()
                ->limit($remainingAfterThird)
                ->get();
        }

        return $secondDegreeUsers
            ->concat($thirdDegreeUsers)
            ->concat($randomUsers)
            ->take($limit)
            ->values();
    }

    protected function getRelationshipGraph(User $user): array
    {
        $followingIds = $user->following()->pluck('followed_id')->toArray();
        $followerIds = $user->followers()->pluck('follower_id')->toArray();

        $directMutualIds = array_values(array_intersect($followingIds, $followerIds));

        $secondDegreeIds = Following::whereIn('follower_id', $followingIds)
            ->pluck('followed_id')
            ->unique()
            ->reject(function ($id) use ($user, $followingIds, $directMutualIds) {
                return $id === $user->id
                    || in_array($id, $followingIds, true)
                    || in_array($id, $directMutualIds, true);
            })
            ->values()
            ->toArray();

        $thirdDegreeIds = Following::whereIn('follower_id', $secondDegreeIds)
            ->pluck('followed_id')
            ->unique()
            ->reject(function ($id) use ($user, $followingIds, $directMutualIds, $secondDegreeIds) {
                return $id === $user->id
                    || in_array($id, $followingIds, true)
                    || in_array($id, $directMutualIds, true)
                    || in_array($id, $secondDegreeIds, true);
            })
            ->values()
            ->toArray();

        return [
            'following' => $followingIds,
            'direct' => $directMutualIds,
            'second' => $secondDegreeIds,
            'third' => $thirdDegreeIds,
        ];
    }

    protected function decorateUsers(Collection $users, array $graph, User $authUser): Collection
    {
        return $users->map(function ($candidate) use ($graph, $authUser) {
            $isDirectMutual = in_array($candidate->id, $graph['direct'], true);
            $isSecondDegree = in_array($candidate->id, $graph['second'], true);
            $isThirdDegree = in_array($candidate->id, $graph['third'], true);
            $isFollowing = in_array($candidate->id, $graph['following'], true);
            $isFriend = $authUser ? $authUser->isFriend($candidate) : false;

            $mutualType = null;
            if ($isDirectMutual) {
                $mutualType = 'two-way';
            } elseif ($isSecondDegree) {
                $mutualType = 'friend-of-friend';
            } elseif ($isThirdDegree) {
                $mutualType = 'friend-of-friend-of-friend';
            }

            $candidate->is_mutual = $isDirectMutual || $isSecondDegree || $isThirdDegree;
            $candidate->mutual_type = $mutualType;
            $candidate->is_following = $isFollowing;
            $candidate->is_private = (bool) ($candidate->preferences?->private_profile ?? false);
            $candidate->has_pending_request = $authUser ? $authUser->hasSentRequestTo($candidate) : false;
            $candidate->is_friend = $isFriend;
            $candidate->followers_count = $candidate->followers_count ?? 0;

            return $candidate;
        })->values();
    }
}
