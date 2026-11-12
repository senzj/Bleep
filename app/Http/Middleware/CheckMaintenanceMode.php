<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /**
     * Serve the maintenance page while the site is taken offline.
     *
     * Local/debug environments stay usable so the page itself can be worked
     * on, and staff can still reach the site to prepare for the return.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.maintenance_mode.enabled')) {
            return $next($request);
        }

        if (app()->environment('local') || config('app.debug')) {
            return $next($request);
        }

        if (Auth::check() && Auth::user()->hasAdminAccess()) {
            return $next($request);
        }

        $duration = config('app.maintenance_mode.duration');

        return response()
            ->view('maintenance', [
                'duration' => $duration,
                'estimatedReturn' => $this->estimatedReturn($duration),
            ], 503)
            ->header('Retry-After', '300');
    }

    /**
     * Turn MAINTENANCE_DURATION into a readable return time when possible.
     *
     * Accepts a number of minutes ("45") or a date/time string.
     */
    private function estimatedReturn(mixed $duration): ?Carbon
    {
        if (! is_string($duration) || trim($duration) === '') {
            return null;
        }

        $duration = trim($duration);

        if (is_numeric($duration)) {
            return now()->addMinutes((int) $duration);
        }

        try {
            return Carbon::parse($duration);
        } catch (\Throwable) {
            return null;
        }
    }
}
