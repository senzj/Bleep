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
        // Only ban fields editable here (keep it simple)
        $validated = $request->validate([
            'is_banned'     => ['required', 'boolean'],
            'ban_reason'    => ['nullable', 'string', 'max:500'],
            'banned_until'  => ['nullable', 'date'], // client sends ISO (UTC) or null
        ]);

        // If unbanning, wipe fields
        if (!$validated['is_banned']) {
            $user->is_banned = false;
            $user->banned_until = null;
            $user->ban_reason = null;
            $user->save();

            return response()->json(['message' => 'User unbanned.']);
        }

        // Banning
        $user->is_banned = true;
        $user->ban_reason = $validated['ban_reason'] ?? 'Banned by admin';

        // Normalize to app timezone (configurable), if provided
        $bannedUntil = null;
        if (!empty($validated['banned_until'])) {
            // Parse incoming (UTC ISO or local ISO) safely and convert to app timezone
            $bannedUntil = Carbon::parse($validated['banned_until'])
                ->setTimezone(config('app.timezone'));
        }
        $user->banned_until = $bannedUntil;
        $user->save();

        return response()->json(['message' => 'User updated.']);
    }
}
