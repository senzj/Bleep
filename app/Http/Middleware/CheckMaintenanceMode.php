<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMaintenanceMode
{
    /**
     * Paths that stay reachable while the site is offline. Staff need the
     * sign-in flow and the settings/admin area to turn maintenance mode off.
     *
     * @var array<int, string>
     */
    private const STAFF_PATHS = [
        'login',
        'logout',
        'admin',
        'admin/*',
        'settings',
        'settings/*',
    ];

    /**
     * Serve the maintenance page while the site is taken offline.
     *
     * Local/debug environments stay usable so the page itself can be worked
     * on. The public site is offline for everyone, including signed-in staff;
     * they can still reach sign-in, settings and the admin area to turn it
     * back off.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.maintenance_mode.enabled')) {
            return $next($request);
        }

        if (app()->environment('local') || config('app.debug')) {
            return $next($request);
        }

        if ($request->is(...self::STAFF_PATHS)) {
            return $next($request);
        }

        $duration = config('app.maintenance_mode.duration');

        return response()
            ->view('maintenance', [
                'duration' => $duration,
                'estimatedReturn' => $this->estimatedReturn($duration),
                'image' => $this->maintenanceImage(),
            ], 503)
            ->header('Retry-After', '300');
    }

    /**
     * Pick a random meme image from the maintenance asset directory.
     */
    private function maintenanceImage(): ?string
    {
        $directory = public_path('images/maintenance');

        if (! is_dir($directory)) {
            return null;
        }

        $images = array_values(array_filter(
            scandir($directory) ?: [],
            fn (string $file): bool => preg_match('/\.(gif|jpe?g|png|webp)$/i', $file) === 1,
        ));

        if ($images === []) {
            return null;
        }

        return $images[array_rand($images)];
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
