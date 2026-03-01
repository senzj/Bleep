<?php

namespace App\Http\Controllers;

use App\Models\FollowRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FollowRequestController extends Controller
{
    /**
     * Display a listing of all follow requests for the authenticated user.
     */
    public function index()
    {
        $requests = FollowRequest::where('target_id', Auth::id())
            ->with('requester')
            ->orderByRaw("FIELD(status, 'pending', 'accepted', 'rejected')")
            ->latest()
            ->get();
        return view('pages.users.request', compact('requests'));
    }

    /**
     * Send a follow request to a private user.
     */
    public function store(Request $request)
    {
        $targetId = $request->input('target_id');
        $target = User::findOrFail($targetId);

        // Check if already following
        if (Auth::user()->isFollowing($target)) {
            return response()->json(['message' => 'Already following'], 400);
        }

        // Check if request already exists (any status)
        $existing = FollowRequest::where('requester_id', Auth::id())
            ->where('target_id', $targetId)
            ->first();

        if ($existing) {
            // If there's a pending request, return error
            if ($existing->status === 'pending') {
                return response()->json(['message' => 'Follow request already sent'], 400);
            }

            // If there's an accepted or rejected request, update it back to pending
            $existing->update([
                'status' => 'pending',
                'updated_at' => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'Follow request sent']);
        }

        // Create new follow request
        FollowRequest::create([
            'requester_id' => Auth::id(),
            'target_id' => $targetId,
            'status' => 'pending',
        ]);

        return response()->json(['success' => true, 'message' => 'Follow request sent']);
    }

    /**
     * Accept a follow request.
     */
    public function accept(FollowRequest $request)
    {
        // Verify the authenticated user is the target
        if ($request->target_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Create the follow relationship: requester follows target
        // Check if requester is already following the target (auth user)
        $isAlreadyFollower = Auth::user()->followers()->where('users.id', $request->requester_id)->exists();

        if (!$isAlreadyFollower) {
            Auth::user()->followers()->attach($request->requester_id);
        }

        // Update the request status
        $request->update(['status' => 'accepted']);

        return response()->json(['success' => true, 'message' => 'Follow request accepted']);
    }

    /**
     * Reject a follow request.
     */
    public function reject(FollowRequest $request)
    {
        // Verify the authenticated user is the target
        if ($request->target_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Update the request status
        $request->update(['status' => 'rejected']);

        return response()->json(['success' => true, 'message' => 'Follow request rejected']);
    }

    /**
     * Cancel a sent follow request by target user ID.
     */
    public function cancelByUserId(User $user)
    {
        $followRequest = FollowRequest::where('requester_id', Auth::id())
            ->where('target_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$followRequest) {
            return response()->json(['message' => 'Follow request not found'], 404);
        }

        $followRequest->delete();

        return response()->json(['success' => true, 'message' => 'Follow request cancelled']);
    }

    /**
     * Cancel a sent follow request by request ID.
     */
    public function cancel(FollowRequest $request)
    {
        if ($request->requester_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->delete();

        return response()->json(['success' => true, 'message' => 'Follow request cancelled']);
    }
}
