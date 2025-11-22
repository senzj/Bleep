<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SocialController extends Controller
{
    public function searchUsers(Request $request)
    {
        $query = $request->get('q', '');
        $limit = 10;

        if (Auth::check()) {
            // Cache following IDs for 5 minutes
            $followingIds = cache()->remember("user_" . Auth::id() . "_following", 300, fn() => Auth::user()->following()->pluck('followed_id'));
            $excludedIds = collect([Auth::id()])->merge($followingIds);

            $baseQuery = User::whereNotIn('id', $excludedIds);

            if ($query) {
                $baseQuery->where(function ($q) use ($query) {
                    $q->where('username', 'like', "%{$query}%")
                      ->orWhere('dname', 'like', "%{$query}%");
                });
            }

            // Only apply mutual prioritization if there are following IDs
            if ($followingIds->isNotEmpty()) {
                $baseQuery->orderByRaw("CASE WHEN id IN (SELECT followed_id FROM followings WHERE follower_id IN (" . $followingIds->implode(',') . ")) THEN 0 ELSE 1 END");
            }

            $users = $baseQuery->inRandomOrder()->limit($limit)->get();

            // Efficiently determine which of these users follow the authenticated user (mutuals)
            $userIds = $users->pluck('id')->all();
            $mutualFollowerIds = [];
            if (!empty($userIds)) {
                $mutualFollowerIds = DB::table('followings')
                    ->whereIn('follower_id', $userIds)
                    ->where('followed_id', Auth::id())
                    ->pluck('follower_id')
                    ->toArray();
            }

            return response()->json($users->map(fn($user) => [
                'id' => $user->id,
                'username' => $user->username,
                'dname' => $user->dname,
                'profile_picture_url' => $user->profile_picture_url,
                'is_mutual' => in_array($user->id, $mutualFollowerIds),
            ]));
        }

        return response()->json([]);
    }
}
