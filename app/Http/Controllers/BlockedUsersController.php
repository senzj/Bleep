<?php

namespace App\Http\Controllers;

use App\Models\BlockedUsers;
use App\Models\Following;
use App\Models\FollowRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlockedUsersController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $blockedUsers = Auth::user()
            ->blockedUsers()
            ->with('blocked')
            ->latest()
            ->get()
            ->pluck('blocked')
            ->filter();

        return view('pages.users.blocked', compact('blockedUsers'));
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, User $user)
    {
        $authUser = $request->user();

        if ($authUser->id === $user->id) {
            return response()->json(['message' => 'You cannot block yourself.'], 422);
        }

        BlockedUsers::firstOrCreate([
            'blocker_id' => $authUser->id,
            'blocked_id' => $user->id,
        ]);

        Following::where(function ($q) use ($authUser, $user) {
            $q->where('follower_id', $authUser->id)->where('followed_id', $user->id);
        })->orWhere(function ($q) use ($authUser, $user) {
            $q->where('follower_id', $user->id)->where('followed_id', $authUser->id);
        })->delete();

        FollowRequest::where(function ($q) use ($authUser, $user) {
            $q->where('requester_id', $authUser->id)->where('target_id', $user->id);
        })->orWhere(function ($q) use ($authUser, $user) {
            $q->where('requester_id', $user->id)->where('target_id', $authUser->id);
        })->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'User blocked. Now you will not see their content and they will not see yours.']);
        }

        return back()->with('success', 'User blocked.');
    }

    /**
     * Display the specified resource.
     */
    public function show(BlockedUsers $blockedUsers)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(BlockedUsers $blockedUsers)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, BlockedUsers $blockedUsers)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $user)
    {
        $authUser = $request->user();

        BlockedUsers::where('blocker_id', $authUser->id)
            ->where('blocked_id', $user->id)
            ->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'User unblocked. You will now see their content and they will see yours.']);
        }

        return back()->with('success', 'User unblocked.');
    }
}
