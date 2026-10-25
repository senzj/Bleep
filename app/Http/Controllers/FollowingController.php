<?php

namespace App\Http\Controllers;

use App\Models\Following;
use App\Models\FollowRequest;
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

        if ($authUser->isBlockedOrHasBlocked($user)) {
            return response()->json([
                'message' => 'Follow action is unavailable due to a block relationship.',
            ], 403);
        }

        $existing = Following::where('follower_id', $authUser->id)
            ->where('followed_id', $user->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json([
                'following' => false,
                'requested' => false,
            ]);
        }

        $preferences = $user->getPreferences();
        $isPrivate = (bool) ($preferences?->private_profile ?? false);
        $blocksNewFollowers = (bool) ($preferences?->block_new_followers ?? false);

        if ($blocksNewFollowers) {
            return response()->json([
                'message' => 'This user is not accepting new followers.',
            ], 403);
        }

        if ($isPrivate) {
            $followRequest = FollowRequest::firstOrNew([
                'requester_id' => $authUser->id,
                'target_id' => $user->id,
            ]);

            $followRequest->status = 'pending';
            $followRequest->save();

            return response()->json([
                'following' => false,
                'requested' => true,
            ]);
        }

        Following::create([
            'follower_id' => $authUser->id,
            'followed_id' => $user->id,
        ]);

        return response()->json([
            'following' => true,
            'requested' => false,
        ]);
    }
}
