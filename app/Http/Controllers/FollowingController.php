<?php

namespace App\Http\Controllers;

use App\Models\Following;
use App\Models\User;

use Illuminate\Http\Request;

class FollowingController extends Controller
{
    /**
     * Toggle follow/unfollow a user.
     */
    public function toggle(Request $request, User $user)
    {
        $authUser = $request->user();

        if ($authUser->id === $user->id) {
            return response()->json([
                'message' => 'You cannot follow yourself.',
            ], 422);
        }

        $existing = Following::where('follower_id', $authUser->id)
            ->where('followed_id', $user->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $following = false;
        } else {
            Following::create([
                'follower_id' => $authUser->id,
                'followed_id' => $user->id,
            ]);
            $following = true;
        }

        return response()->json([
            'following' => $following,
        ]);
    }
}
