<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        $followerIds = Auth::user()->followers()->pluck('follower_id')->toArray();
        $mutualIds = array_values(array_intersect($followingIds, $followerIds));

        // If empty query: return mutuals first, then fill with random users excluding following/self
        if ($query === '') {
            // Mutual users (exclude self)
            $mutualUsers = collect();
            if (!empty($mutualIds)) {
                $mutualUsers = User::whereIn('id', $mutualIds)
                    ->where('id', '!=', $currentUserId)
                    ->withCount('followers')
                    ->get()
                    ->sortByDesc('followers_count')
                    ->values();
            }

            // Fill with random users excluding self, already-followed and mutuals
            $needed = max(0, $limit - $mutualUsers->count());
            $exclude = array_merge([$currentUserId], $followingIds, $mutualIds);

            $randomUsers = collect();
            if ($needed > 0) {
                $randomUsers = User::whereNotIn('id', $exclude)
                    ->inRandomOrder()
                    ->limit($needed)
                    ->withCount('followers')
                    ->get()
                    ->values();
            }

            $combined = $mutualUsers->concat($randomUsers)->take($limit);
            $payload = $combined->map(function ($user) use ($mutualIds, $followingIds) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'dname' => $user->dname,
                    'profile_picture_url' => $user->profile_picture_url ?? '/images/default-avatar.png',
                    'is_mutual' => in_array($user->id, $mutualIds),
                    'is_following' => in_array($user->id, $followingIds),
                    'followers_count' => $user->followers_count ?? 0,
                ];
            })->values();

            return response()->json($payload);
        }

        // Non-empty query: search all users excluding self (include following in results)
        $usersQuery = User::where('id', '!=', $currentUserId)
            ->where(function ($q) use ($query) {
                $q->where('username', 'like', "%{$query}%")
                  ->orWhere('dname', 'like', "%{$query}%");
            });

        $users = $usersQuery->withCount('followers')->get();

        // Keep mutuals at top (if any), then others sorted by followers_count
        $mutuals = $users->filter(fn($u) => in_array($u->id, $mutualIds))
                         ->sortByDesc('followers_count')
                         ->values();

        $others = $users->reject(fn($u) => in_array($u->id, $mutualIds))
                        ->sortByDesc('followers_count')
                        ->values();

        $sorted = $mutuals->concat($others)->take($limit);

        $payload = $sorted->values()->map(function ($user) use ($mutualIds, $followingIds) {
            return [
                'id' => $user->id,
                'username' => $user->username,
                'dname' => $user->dname,
                'profile_picture_url' => $user->profile_picture_url ?? '/images/default-avatar.png',
                'is_mutual' => in_array($user->id, $mutualIds),
                'is_following' => in_array($user->id, $followingIds),
                'followers_count' => $user->followers_count ?? 0,
            ];
        });

        return response()->json($payload);
    }
}