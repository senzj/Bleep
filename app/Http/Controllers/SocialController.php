<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class SocialController extends Controller
{
    public function searchUsers(Request $request)
    {
        $query = trim($request->get('q', ''));
        $limit = 20;

        if (! Auth::check()) {
            return response()->json([]);
        }

        $currentUserId = Auth::id();

        // Get following and follower ids
        $followingIds = Auth::user()->following()->pluck('followed_id')->toArray();
        $followerIds  = Auth::user()->followers()->pluck('follower_id')->toArray();

        $directMutualIds = array_values(array_intersect($followingIds, $followerIds));

        $secondDegreeIds = User::whereIn('id', $followingIds)
            ->with(['following:id'])
            ->get()
            ->flatMap(fn ($followedUser) => $followedUser->following->pluck('id'))
            ->unique()
            ->reject(fn ($id) => $id === $currentUserId || in_array($id, $followingIds, true))
            ->diff($directMutualIds)
            ->values()
            ->toArray();

        if ($query === '') {
            $secondDegreeUsers = collect();
            if (!empty($secondDegreeIds)) {
                $secondDegreeUsers = User::whereIn('id', $secondDegreeIds)
                    ->withCount('followers')
                    ->orderByDesc('followers_count')
                    ->limit($limit)
                    ->get();
            }

            $excludeIds = array_merge(
                [$currentUserId],
                $followingIds,
                $secondDegreeUsers->pluck('id')->all(),
                $directMutualIds // keep direct mutuals out of suggestions
            );

            $neededAfterSecond = max(0, $limit - $secondDegreeUsers->count());

            $randomUsers = collect();
            if ($neededAfterSecond > 0) {
                $randomUsers = User::whereNotIn('id', $excludeIds)
                    ->withCount('followers')
                    ->inRandomOrder()
                    ->limit($neededAfterSecond)
                    ->get();
            }

            $combined = $secondDegreeUsers
                ->concat($randomUsers)
                ->take($limit);

            return response()->json(
                $combined->map(function ($user) use ($secondDegreeIds, $followingIds) {
                    $isSecondDegree = in_array($user->id, $secondDegreeIds, true);
                    $isFollowing    = in_array($user->id, $followingIds, true);

                    return [
                        'id'                  => $user->id,
                        'username'            => $user->username,
                        'dname'               => $user->dname,
                        'profile_picture_url' => $user->profile_picture_url ?? '/images/default-avatar.png',
                        'is_mutual'           => $isSecondDegree,
                        'mutual_type'         => $isSecondDegree ? 'friend-of-friend' : null,
                        'is_following'        => $isFollowing,
                        'followers_count'     => $user->followers_count ?? 0,
                    ];
                })->values()
            );
        }

        $users = User::where('id', '!=', $currentUserId)
            ->where(function ($q) use ($query) {
                $q->where('username', 'like', "%{$query}%")
                  ->orWhere('dname', 'like', "%{$query}%");
            })
            ->withCount('followers')
            ->get();

        $sorted = $users
            ->sortByDesc(fn ($user) => [
                in_array($user->id, $directMutualIds, true),
                in_array($user->id, $secondDegreeIds, true),
                in_array($user->id, $followingIds, true),
                $user->followers_count ?? 0,
            ])
            ->take($limit);

        return response()->json(
            $sorted->map(function ($user) use ($directMutualIds, $secondDegreeIds, $followingIds) {
                $isDirectMutual = in_array($user->id, $directMutualIds, true);
                $isSecondDegree = in_array($user->id, $secondDegreeIds, true);
                $isFollowing    = in_array($user->id, $followingIds, true);

                return [
                    'id'                  => $user->id,
                    'username'            => $user->username,
                    'dname'               => $user->dname,
                    'profile_picture_url' => $user->profile_picture_url ?? '/images/default-avatar.png',
                    'is_mutual'           => $isDirectMutual || $isSecondDegree,
                    'mutual_type'         => $isDirectMutual ? 'two-way' : ($isSecondDegree ? 'friend-of-friend' : null),
                    'is_following'        => $isFollowing,
                    'followers_count'     => $user->followers_count ?? 0,
                ];
            })->values()
        );
    }
}
