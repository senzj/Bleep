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
        $suggestedUsers = $this->getSuggestedUsers($user, $graph, 10);

        return view('pages.social.people', [
            'suggestedUsers' => $suggestedUsers,
        ]);
    }

    public function searchUsers(Request $request)
    {
        $query = trim($request->get('q', ''));
        $limit = 20;

        if (! Auth::check()) {
            return response()->json([]);
        }

        $user = Auth::user();
        $currentUserId = $user->id;

        $graph = $this->getRelationshipGraph($user);

        if ($query === '') {
            $suggestedUsers = $this->getSuggestedUsers($user, $graph, $limit);

            return response()->json(
                $this->serializeUsers($suggestedUsers, $graph)
            );
        }

        $localPart = explode('@', $query)[0];

        $users = User::where('id', '!=', $currentUserId)
            ->where(function ($q) use ($query, $localPart) {
                $q->where('username', 'like', "%{$query}%")
                    ->orWhere('dname', 'like', "%{$query}%")
                    ->orWhereRaw("SUBSTRING_INDEX(email, '@', 1) like ?", ["%{$localPart}%"]);
            })
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

        return response()->json(
            $this->serializeUsers($sorted, $graph)
        );
    }

    protected function getSuggestedUsers(User $user, array $graph, int $limit): Collection
    {
        $secondDegreeUsers = collect();
        if (!empty($graph['second'])) {
            $secondDegreeUsers = User::whereIn('id', $graph['second'])
                ->withCount('followers')
                ->orderByDesc('followers_count')
                ->limit($limit)
                ->get();
        }

        $remainingAfterSecond = max(0, $limit - $secondDegreeUsers->count());

        $thirdDegreeUsers = collect();
        if ($remainingAfterSecond > 0 && !empty($graph['third'])) {
            $thirdDegreeUsers = User::whereIn('id', $graph['third'])
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

    protected function serializeUsers(Collection $users, array $graph): Collection
    {
        return $users->map(function ($candidate) use ($graph) {
            $isDirectMutual = in_array($candidate->id, $graph['direct'], true);
            $isSecondDegree = in_array($candidate->id, $graph['second'], true);
            $isThirdDegree = in_array($candidate->id, $graph['third'], true);
            $isFollowing = in_array($candidate->id, $graph['following'], true);

            $mutualType = null;
            if ($isDirectMutual) {
                $mutualType = 'two-way';
            } elseif ($isSecondDegree) {
                $mutualType = 'friend-of-friend';
            } elseif ($isThirdDegree) {
                $mutualType = 'friend-of-friend-of-friend';
            }

            return [
                'id' => $candidate->id,
                'username' => $candidate->username,
                'dname' => $candidate->dname,
                'profile_picture_url' => $candidate->profile_picture_url ?? '/images/default-avatar.png',
                'is_mutual' => $isDirectMutual || $isSecondDegree || $isThirdDegree,
                'mutual_type' => $mutualType,
                'is_following' => $isFollowing,
                'followers_count' => $candidate->followers_count ?? 0,
            ];
        })->values();
    }
}
