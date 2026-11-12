<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\ProfileVisit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileAnalyticsController extends Controller
{
    /**
     * Owner-only analytics for the signed-in user's own profile.
     */
    public function show(Request $request, string $username): View
    {
        $user = User::where('username', $username)->firstOrFail();

        abort_unless(Auth::id() === $user->id, 403);

        $days = collect(range(13, 0))->map(fn (int $offset) => now()->subDays($offset)->startOfDay());

        $visitsByDay = ProfileVisit::query()
            ->where('profile_user_id', $user->id)
            ->where('visited_on', '>=', $days->first()->toDateString())
            ->get(['visited_on', 'visitor_id'])
            ->groupBy(fn (ProfileVisit $visit) => $visit->visited_on->toDateString())
            ->map(fn ($group) => [
                'total' => $group->count(),
                'unique' => $group->unique('visitor_id')->count(),
            ]);

        $chart = $days->map(function (Carbon $day) use ($visitsByDay) {
            $data = $visitsByDay->get($day->toDateString());

            return [
                'date' => $day,
                'label' => $day->format('j M'),
                'total' => (int) ($data['total'] ?? 0),
                'unique' => (int) ($data['unique'] ?? 0),
            ];
        });

        $peak = max(1, $chart->max('total'));

        $summary = [
            'profile_visits' => $user->profileVisits()->count(),
            'unique_visitors' => $user->profileVisits()->distinct('visitor_id')->count('visitor_id'),
            'followers' => $user->followers()->count(),
            'following' => $user->following()->count(),
            'bleeps' => $user->bleeps()->count(),
            'bleep_views' => (int) $user->bleeps()->sum('views'),
            'likes_received' => $user->bleeps()->withCount('likes')->get()->sum('likes_count'),
            'comments_received' => $user->bleeps()->withCount('comments')->get()->sum('comments_count'),
        ];

        return view('pages.analytics.profile', [
            'user' => $user,
            'summary' => $summary,
            'chart' => $chart,
            'peak' => $peak,
            'recentVisitors' => $user->profileVisits()
                ->with('visitor:id,username,dname,profile_picture')
                ->latest('visited_on')
                ->limit(8)
                ->get(),
        ]);
    }
}
