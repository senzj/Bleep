<?php

namespace App\Http\Controllers\Bleep;

use App\Http\Controllers\Controller;
use App\Models\Bleep;
use App\Models\BleepViews;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class BleepAnalyticsController extends Controller
{
    use AuthorizesRequests;

    /**
     * Owner-only analytics for a single bleep.
     */
    public function show(Request $request, Bleep $bleep): View
    {
        $this->authorize('viewAnalytics', $bleep);

        $days = collect(range(13, 0))->map(fn (int $offset) => now()->subDays($offset)->startOfDay());

        $viewsByDay = BleepViews::query()
            ->where('bleep_id', $bleep->id)
            ->where('viewed_at', '>=', $days->first())
            ->get(['viewed_at', 'user_id'])
            ->groupBy(fn (BleepViews $view) => $view->viewed_at->toDateString())
            ->map(fn ($group) => [
                'total' => $group->count(),
                'signed_in' => $group->whereNotNull('user_id')->unique('user_id')->count(),
                'guests' => $group->whereNull('user_id')->count(),
            ]);

        $chart = $days->map(function (Carbon $day) use ($viewsByDay) {
            $key = $day->toDateString();
            $data = $viewsByDay->get($key);

            return [
                'date' => $day,
                'label' => $day->format('j M'),
                'total' => (int) ($data['total'] ?? 0),
                'signed_in' => (int) ($data['signed_in'] ?? 0),
                'guests' => (int) ($data['guests'] ?? 0),
            ];
        });

        $peak = max(1, $chart->max('total'));

        $likes = (int) $bleep->likeCount();
        $comments = (int) $bleep->commentCount();
        $shares = (int) $bleep->shareCount();
        $reposts = (int) $bleep->repostCount();
        $views = (int) $bleep->viewCount();

        $signedInViewers = (int) $bleep->views()->whereNotNull('user_id')->distinct('user_id')->count('user_id');
        $guestViewers = (int) $bleep->views()
            ->whereNull('user_id')
            ->whereNotNull('session_id')
            ->distinct('session_id')
            ->count('session_id');

        $totalEngagement = $likes + $comments + $shares + $reposts;

        $summary = [
            'views' => $views,
            'unique_viewers' => $signedInViewers + $guestViewers,
            'signed_in_viewers' => $signedInViewers,
            'guest_views' => (int) $bleep->views()->whereNull('user_id')->count(),
            'guest_viewers' => $guestViewers,
            'likes' => $likes,
            'comments' => $comments,
            'shares' => $shares,
            'reposts' => $reposts,
            'total_engagement' => $totalEngagement,
            'engagement_rate' => $views > 0 ? round(($totalEngagement / $views) * 100, 1) : 0.0,
            'media_count' => (int) $bleep->media()->count(),
        ];

        $periodViews = $chart->sum('total');
        $previousPeriodViews = BleepViews::query()
            ->where('bleep_id', $bleep->id)
            ->whereBetween('viewed_at', [
                now()->subDays(27)->startOfDay(),
                now()->subDays(14)->endOfDay(),
            ])
            ->count();

        $trend = $previousPeriodViews > 0
            ? round((($periodViews - $previousPeriodViews) / $previousPeriodViews) * 100, 1)
            : null;

        $bestDay = $chart->sortByDesc('total')->first();
        if (! $bestDay || (int) $bestDay['total'] === 0) {
            $bestDay = null;
        }

        return view('pages.analytics.bleep', [
            'bleep' => $bleep->loadMissing(['user', 'media']),
            'summary' => $summary,
            'chart' => $chart,
            'peak' => $peak,
            'periodViews' => $periodViews,
            'previousPeriodViews' => $previousPeriodViews,
            'trend' => $trend,
            'bestDay' => $bestDay,
        ]);
    }
}
