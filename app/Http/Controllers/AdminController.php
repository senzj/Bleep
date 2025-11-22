<?php

namespace App\Http\Controllers;

use Throwable;
use App\Models\Logs;
use App\Models\User;
use App\Models\Bleep;
use App\Models\Likes;
use App\Models\Share;
use App\Models\Repost;
use App\Models\Reports;
use App\Models\Comments;
use App\Models\RememberedDevice;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Carbon\CarbonInterval;
use Carbon\CarbonPeriod;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $totalUsers = User::count();
        $newToday = User::whereDate('created_at', now()->toDateString())->count();
        $bannedUsers = User::where('is_banned', true)->count();

        $totalSessions = DB::table('sessions')->count();
        $activeSessions = DB::table('sessions')
            ->whereNotNull('user_id')
            ->where('last_activity', '>=', now()->subMinutes(5)->timestamp)
            ->distinct()
            ->count('user_id');

        $totalDevices = RememberedDevice::count();

        // Reports counts (fallback to 0 if table doesn't exist)
        try {
            $reportsPending = DB::table('reports')->where('status', 'pending')->count();
            $reportsOngoing = DB::table('reports')->whereIn('status', ['ongoing','open','in_progress'])->count();
        } catch (\Throwable $e) {
            $reportsPending = 0;
            $reportsOngoing = 0;
        }

        return view('admin.dashboard', compact(
            'totalUsers',
            'newToday',
            'bannedUsers',
            'totalSessions',
            'activeSessions',
            'totalDevices',
            'reportsPending',
            'reportsOngoing'
        ));
    }

    public function dashboardChartData(Request $request)
    {
        $range = $request->get('range', 'daily');

        try {
            $payload = Cache::remember("admin:stats:dashboard:{$range}", 30, function () use ($range) {
                $now = now();

                switch ($range) {
                    case 'weekly':
                        $start = $now->copy()->subWeeks(12)->startOfWeek();
                        $interval = 'week';
                        $format = 'Y-m-d';
                        break;
                    case 'monthly':
                        $start = $now->copy()->subMonths(12)->startOfMonth();
                        $interval = 'month';
                        $format = 'Y-m';
                        break;
                    case 'yearly':
                        $start = $now->copy()->subYears(5)->startOfYear();
                        $interval = 'year';
                        $format = 'Y';
                        break;
                    case 'daily':
                    default:
                        $start = $now->copy()->subDays(30)->startOfDay();
                        $interval = 'day';
                        $format = 'Y-m-d';
                        break;
                }
                $end = $now;

                // Users time series
                $dateFormat = $interval === 'month' ? '%Y-%m' : ($interval === 'year' ? '%Y' : '%Y-%m-%d');
                $usersTimeseries = DB::table('users')
                    ->select(DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as period"), DB::raw('COUNT(*) as total'))
                    ->whereBetween('created_at', [$start, $end])
                    ->groupBy('period')
                    ->orderBy('period')
                    ->pluck('total', 'period')
                    ->toArray();

                $totalUsers = DB::table('users')->count();
                $activeUsers = DB::table('sessions')->where('last_activity', '>=', now()->subMinutes(5)->timestamp)->distinct()->count('user_id');

                // Devices
                $osCounts = RememberedDevice::select(DB::raw("COALESCE(parsed_os, 'Unknown') as label"), DB::raw("COUNT(*) as total"))
                    ->groupBy('label')->orderByRaw('total DESC')->limit(10)
                    ->get()->map(fn($r) => ['label' => $r->label, 'value' => (int)$r->total])->values();

                $browserCounts = RememberedDevice::select(DB::raw("COALESCE(parsed_browser, 'Unknown') as label"), DB::raw("COUNT(*) as total"))
                    ->groupBy('label')->orderByRaw('total DESC')->limit(10)
                    ->get()->map(fn($r) => ['label' => $r->label, 'value' => (int)$r->total])->values();

                $deviceTypeCounts = RememberedDevice::select(DB::raw("COALESCE(parsed_device_type, 'Unknown') as label"), DB::raw("COUNT(*) as total"))
                    ->groupBy('label')->orderByRaw('total DESC')
                    ->get()->map(fn($r) => ['label' => $r->label, 'value' => (int)$r->total])->values();

                // Sessions - hourly/daily
                $hourlyRows = DB::table('sessions')
                    ->select(DB::raw("HOUR(FROM_UNIXTIME(last_activity)) as hour"), DB::raw("COUNT(*) as total"))
                    ->whereBetween('last_activity', [now()->subDays(7)->timestamp, now()->timestamp])
                    ->groupBy('hour')->orderBy('hour')->get()->pluck('total', 'hour')->toArray();

                $dailyRows = DB::table('sessions')
                    ->select(DB::raw("DAYOFWEEK(FROM_UNIXTIME(last_activity)) as day"), DB::raw("COUNT(*) as total"))
                    ->whereBetween('last_activity', [now()->subWeeks(8)->timestamp, now()->timestamp])
                    ->groupBy('day')->orderBy('day')->get()->pluck('total', 'day')->toArray();

                $activeSessions = DB::table('sessions')->where('last_activity', '>=', now()->subMinutes(5)->timestamp)->count();
                $peakHour = collect($hourlyRows)->sortDesc()->keys()->first();
                $peakDay = collect($dailyRows)->sortDesc()->keys()->first();

                // Bleeps & engagement
                $totalBleeps = Bleep::count();
                $likesCount = Likes::count();
                $sharesCount = Share::count();
                $repostCount = Repost::count();
                $commentsCount = Comments::count();
                $engagementTotal = $likesCount + $sharesCount + $repostCount + $commentsCount;
                $engagementRate = $totalBleeps ? round($engagementTotal / $totalBleeps, 2) : 0;

                $topBleeps = Bleep::where('created_at', '>=', now()->subDays(90))
                    ->withCount('likes')->with('user')->orderByDesc('likes_count')->limit(10)
                    ->get(['id', 'message', 'user_id', 'created_at'])
                    ->map(fn($b) => [
                        'id' => $b->id,
                        'message' => \Illuminate\Support\Str::limit($b->message, 120),
                        'likes' => $b->likes_count,
                        'user' => ['id' => $b->user_id, 'dname' => optional($b->user)->dname],
                        'created_at' => $b->created_at,
                    ]);

                // Reports
                $reportsCounts = DB::table('reports')
                    ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d') as day"), DB::raw("COUNT(*) as total"))
                    ->whereBetween('created_at', [now()->subDays(90), now()])
                    ->groupBy('day')->pluck('total', 'day')->toArray();

                $reportsByCategory = DB::table('reports')
                    ->select('category', DB::raw('COUNT(*) as total'))
                    ->groupBy('category')->pluck('total', 'category')->toArray();

                $pending = DB::table('reports')->where('status', 'pending')->count();
                $ongoingCount = DB::table('reports')->whereIn('status', ['ongoing', 'open', 'in_progress'])->count();
                $resolved = DB::table('reports')->where('status', 'resolved')->count();

                // Build labels for series using CarbonInterval
                if ($interval === 'day') {
                    $step = CarbonInterval::day();
                } elseif ($interval === 'week') {
                    $step = CarbonInterval::week();
                } elseif ($interval === 'month') {
                    $step = CarbonInterval::month();
                } else {
                    $step = CarbonInterval::year();
                }
                $period = CarbonPeriod::create($start, $step, $end);
                $labels = [];
                foreach ($period as $dt) {
                    $labels[] = $dt->format($format);
                }
                $userSeries = array_map(fn($l) => (int)($usersTimeseries[$l] ?? 0), $labels);

                return [
                    'meta' => ['range' => $range, 'start' => $start->toDateString(), 'end' => $end->toDateString()],
                    'users' => ['total' => $totalUsers, 'active' => $activeUsers, 'labels' => $labels, 'series' => [$userSeries]],
                    'devices' => ['os' => $osCounts, 'browser' => $browserCounts, 'deviceType' => $deviceTypeCounts],
                    'sessions' => ['activeSessions' => $activeSessions, 'hourly' => $hourlyRows, 'peakHour' => $peakHour, 'peakDay' => $peakDay],
                    'bleeps' => ['total' => $totalBleeps, 'likes' => $likesCount, 'shares' => $sharesCount, 'reposts' => $repostCount, 'comments' => $commentsCount, 'engagementRate' => $engagementRate, 'top' => $topBleeps],
                    'reports' => ['pending' => $pending, 'ongoing' => $ongoingCount, 'resolved' => $resolved, 'byCategory' => $reportsByCategory, 'labels' => array_keys($reportsCounts), 'series' => [array_values($reportsCounts)]],
                ];
            });

            return response()->json($payload);
        } catch (Throwable $e) {
            Log::error('admin.dashboard.chart-data error: ' . $e->getMessage(), ['exception' => $e]);
            $message = config('app.debug') ? $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() : 'Internal server error';
            return response()->json(['error' => $message], 500);
        }
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
        $search = $request->get('q', null);
        $filter = $request->get('filter', 'all');

        // Stats (unchanged)
        $totalSessions = DB::table('sessions')->count();
        $activeSessions = DB::table('sessions')->where('last_activity', '>=', now()->subMinutes(5)->timestamp)->count();
        $uniqueUsers = DB::table('sessions')->whereNotNull('user_id')->distinct()->count('user_id');

        $totalDevices = RememberedDevice::count();
        $activeDevices = RememberedDevice::where('last_used_at', '>=', now()->subDays(7))->count();

        // Sessions query
        $sessionsQuery = DB::table('sessions')
            ->leftJoin('users', 'sessions.user_id', '=', 'users.id')
            ->select('sessions.*')
            ->whereNotNull('sessions.user_id')
            ->orderByDesc('last_activity');

        if ($filter === 'online') {
            $sessionsQuery->where('last_activity', '>=', now()->subMinutes(5)->timestamp);
        } elseif ($filter === 'offline') {
            $sessionsQuery->where('last_activity', '<', now()->subMinutes(5)->timestamp);
        }

        if ($search) {
            $sessionsQuery->where(function($q) use ($search) {
                $q->where('users.username', 'like', "%{$search}%")
                  ->orWhere('users.dname', 'like', "%{$search}%")
                  ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        $sessions = $sessionsQuery->paginate(12, ['*'], 'sessions_page')->withQueryString();

        // Remembered devices query
        $devicesQuery = RememberedDevice::with('user')->orderByDesc('last_used_at');

        if ($filter === 'online') {
            $devicesQuery->where('last_used_at', '>=', now()->subMinutes(5));
        } elseif ($filter === 'offline') {
            $devicesQuery->where('last_used_at', '<', now()->subMinutes(5));
        }

        if ($search) {
            $devicesQuery->whereHas('user', function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('dname', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $devices = $devicesQuery->paginate(12, ['*'], 'devices_page')->withQueryString();

        // load user info for sessions
        $userIds = $sessions->pluck('user_id')->unique()->filter()->values();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        return view('admin.devices', compact(
            'sessions',
            'users',
            'devices',
            'totalSessions',
            'activeSessions',
            'uniqueUsers',
            'totalDevices',
            'activeDevices',
            'filter',
            'search'
        ));
    }

    public function revokeSession(Request $request, $sessionId)
    {
        $deleted = DB::table('sessions')->where('id', $sessionId)->delete();
        if ($deleted) {
            Logs::record(Auth::id(), 'session_removed', ['session_id' => $sessionId, 'by_admin' => Auth::id()], $request);
            return response()->json(['message' => 'Session revoked.']);
        }
        return response()->json(['message' => 'Session not found.'], 404);
    }

    public function revokeDevice(Request $request, RememberedDevice $device)
    {
        $device->delete();
        Logs::record(Auth::id(), 'device_removed', ['device_id' => $device->id, 'target_user' => $device->user_id], $request);
        return response()->json(['message' => 'Device removed.']);
    }

    /**
     * Logs page
     */
    public function logs(Request $request)
    {
        // Base query
        $query = Logs::query()->with('user')->orderByDesc('created_at');

        // Quick search across user display name, username, email and logs.ip
        $q = trim($request->get('q', ''));
        if ($q !== '') {
            // join users only when needed for searching user fields
            $query = $query->leftJoin('users', 'logs.user_id', '=', 'users.id')
                ->select('logs.*')
                ->where(function ($w) use ($q) {
                    $w->where('users.username', 'like', "%{$q}%")
                      ->orWhere('users.dname', 'like', "%{$q}%")
                      ->orWhere('users.email', 'like', "%{$q}%")
                      ->orWhere('logs.ip', 'like', "%{$q}%");
                });
        }

        // Dynamic filter values (populated from DB)
        $actions = Logs::select('action')
            ->distinct()
            ->whereNotNull('action')
            ->orderBy('action')
            ->pluck('action')
            ->filter()
            ->values()
            ->toArray();

        $oses = RememberedDevice::selectRaw("COALESCE(parsed_os, 'Unknown') as val")
            ->distinct()
            ->pluck('val')
            ->filter()
            ->values()
            ->toArray();

        $browsers = RememberedDevice::selectRaw("COALESCE(parsed_browser, 'Unknown') as val")
            ->distinct()
            ->pluck('val')
            ->filter()
            ->values()
            ->toArray();

        // Apply filters from request
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->get('action'));
        }

        // device filters: we filter logs by matching user_agent content (best-effort)
        if ($request->filled('device_os')) {
            $query->where('user_agent', 'like', '%'.$request->get('device_os').'%');
        }
        if ($request->filled('device_browser')) {
            $query->where('user_agent', 'like', '%'.$request->get('device_browser').'%');
        }

        // date filters
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->get('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->get('date_to'));
        }

        $logs = $query->paginate(25)->withQueryString();

        // Pass dynamic lists and current query values to the view
        return view('admin.logs', [
            'logs' => $logs,
            'actions' => $actions,
            'oses' => $oses,
            'browsers' => $browsers,
            'q' => $q,
            'userId' => $request->get('user_id'),
            'action' => $request->get('action'),
            'device_os' => $request->get('device_os'),
            'device_browser' => $request->get('device_browser'),
            'dateFrom' => $request->get('date_from'),
            'dateTo' => $request->get('date_to'),
        ]);
    }


}
