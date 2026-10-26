<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Models\FollowRequest;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FollowRequestController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Display a listing of all follow requests for the authenticated user.
     */
    public function index()
    {
        $requests = FollowRequest::where('target_id', Auth::id())
            ->with('requester')
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'accepted' THEN 1 ELSE 2 END")
            ->latest()
            ->get();

        return view('pages.users.request', compact('requests'));
    }

    /**
     * Send a follow request to a private user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'target_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $authUser = Auth::user();
        $target = User::findOrFail($validated['target_id']);

        if ((int) $target->id === (int) $authUser->id) {
            return response()->json(['message' => 'You cannot follow yourself.'], 422);
        }

        if ($authUser->isBlockedOrHasBlocked($target)) {
            return response()->json(['message' => 'Follow request is unavailable due to a block relationship.'], 403);
        }

        if ($target->getPreferences()?->block_new_followers) {
            return response()->json(['message' => 'This user is not accepting new followers.'], 403);
        }

        // Check if already following
        if ($authUser->isFollowing($target)) {
            return response()->json(['message' => 'Already following'], 400);
        }

        // Check if request already exists (any status)
        $existing = FollowRequest::where('requester_id', $authUser->id)
            ->where('target_id', $target->id)
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
        } else {
            // Create new follow request
            FollowRequest::create([
                'requester_id' => $authUser->id,
                'target_id' => $target->id,
                'status' => 'pending',
            ]);
        }

        $this->notifications->notify($target, NotificationType::FollowRequested, [
            'actor' => $authUser,
            'subject' => $authUser,
            'url' => route('follow.requests'),
            'group_key' => 'followreq:'.$authUser->id,
        ]);

        return response()->json(['success' => true, 'message' => 'Follow request sent']);
    }

    /**
     * Accept a follow request.
     */
    public function accept(FollowRequest $request)
    {
        $authUser = Auth::user();

        // Verify the authenticated user is the target
        if ((int) $request->target_id !== (int) $authUser->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($request->status !== 'pending') {
            return response()->json(['message' => 'This follow request is no longer pending.'], 422);
        }

        $requester = User::find($request->requester_id);

        if (! $requester) {
            return response()->json(['message' => 'Requester no longer exists.'], 422);
        }

        if ($authUser->isBlockedOrHasBlocked($requester)) {
            return response()->json(['message' => 'Follow request cannot be accepted due to a block relationship.'], 403);
        }

        // Create the follow relationship: requester follows target.
        // syncWithoutDetaching is race-safe and does not duplicate rows.
        $authUser->followers()->syncWithoutDetaching([$requester->id]);

        $request->update(['status' => 'accepted']);

        $this->notifications->notify($requester, NotificationType::FollowAccepted, [
            'actor' => $authUser,
            'subject' => $authUser,
            'url' => route('user.profile', ['username' => $authUser->username]),
            'group_key' => 'followacc:'.$authUser->id,
        ]);

        return response()->json(['success' => true, 'message' => 'Follow request accepted']);
    }

    /**
     * Reject a follow request.
     */
    public function reject(FollowRequest $request)
    {
        // Verify the authenticated user is the target
        if ((int) $request->target_id !== (int) Auth::id()) {
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

        if (! $followRequest) {
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
        if ((int) $request->requester_id !== (int) Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->delete();

        return response()->json(['success' => true, 'message' => 'Follow request cancelled']);
    }
}
