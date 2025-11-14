<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function index()
    {
        return view('admin.dashboard');
    }

    public function users(Request $request)
    {
        // Basic stats
        $totalUsers  = User::count();
        $bannedUsers = User::where('is_banned', true)->count();
        $newToday    = User::whereDate('created_at', now()->toDateString())->count();

        // Online now (active in last 5 minutes)
        $onlineNow = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)
            ->distinct()
            ->count('user_id');

        // Simple search by username or email
        $q = trim($request->get('q', ''));
        $usersQuery = User::query()
            ->when($q, fn($builder) => $builder->where(function($w) use ($q) {
                $w->where('username', 'like', "%{$q}%")
                  ->orWhere('email', 'like', "%{$q}%");
            }))
            ->orderByDesc('created_at');

        $users = $usersQuery
            ->paginate(24)
            ->withQueryString();

        return view('admin.users', compact('users', 'totalUsers', 'bannedUsers', 'newToday', 'onlineNow', 'q'));
    }

    public function updateUsers(Request $request, User $user)
    {
        $validated = $request->validate([
            'role'          => ['required', \Illuminate\Validation\Rule::in(['admin','moderator','user'])],
            'is_verified'   => ['required','boolean'],

            'is_banned'     => ['required','boolean'],
            'ban_reason'    => ['nullable','string','max:500'],
            'duration_type' => ['nullable', \Illuminate\Validation\Rule::in(['temporary','permanent'])],
            'banned_until'  => ['nullable','date'],
        ]);

        // Always update role + verification
        $user->role = $validated['role'];
        $user->is_verified = (bool) $validated['is_verified'];

        if (! $validated['is_banned']) {
            // Unban
            $user->is_banned = false;
            $user->banned_until = null;
            $user->ban_reason = null;
            $user->save();
            return response()->json(['message' => 'User updated (unbanned).']);
        }

        // Ban
        $user->is_banned = true;
        $user->ban_reason = $validated['ban_reason'] ?? 'Banned by admin';

        $bannedUntil = null;
        if (($validated['duration_type'] ?? null) === 'temporary' && !empty($validated['banned_until'])) {
            $bannedUntil = \Illuminate\Support\Carbon::parse($validated['banned_until'])
                ->setTimezone(config('app.timezone'));
        }
        // If permanent, keep banned_until as null
        $user->banned_until = $bannedUntil;
        $user->save();

        return response()->json(['message' => 'User updated.']);
    }

    public function devices(Request $request)
    {
        // List of active sessions with user info
        $sessions = DB::table('sessions')
            ->whereNotNull('user_id')
            ->orderByDesc('last_activity')
            ->paginate(24);

        // Load user info
        $userIds = $sessions->pluck('user_id')->unique()->filter()->values();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        return view('admin.devices', compact('sessions', 'users'));
    }
}
