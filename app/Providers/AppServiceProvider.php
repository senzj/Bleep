<?php

namespace App\Providers;

use App\Models\Bleep;
use App\Models\Visits;
use App\Models\Comments;
use App\Policies\BleepPolicy;
use App\Policies\CommentsPolicy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Http\Events\RequestHandled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Bleep::class => BleepPolicy::class,
        Comments::class => CommentsPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Admin or Moderator Gate
        Gate::define('is_admin', function ($user) {
            return $user->hasAdminAccess();
        });

        // Records site visits ONLY for landing pages to avoid double logging
        Event::listen(RequestHandled::class, function (RequestHandled $event) {
            try {
                $req = $event->request;
                $path = $req->path();

                // Only track specific landing pages and entry points
                // Skip API routes, static assets, and non-GET requests
                $trackedPages = [
                    '/' => 'Homepage',                              // Home/feed
                    'bleeper/*' => 'Profile',                       // User profiles
                    'bleeps/*' => 'Post',                           // Individual post view
                ];

                // Check if route should be tracked
                $shouldTrack = false;
                foreach ($trackedPages as $pattern => $label) {
                    if ($this->pathMatches($path, $pattern)) {
                        $shouldTrack = true;
                        break;
                    }
                }

                // Skip if:
                // 1. Not a GET request
                // 2. Not a tracked page
                // 3. API routes
                // 4. Static assets
                if (!$shouldTrack || $req->method() !== 'GET' ||
                    str_starts_with($path, 'api/') ||
                    str_starts_with($path, 'admin/')) {
                    return;
                }

                $ua = $req->header('User-Agent') ?? '';

                // Basic platform detection
                $platform = 'Unknown';
                if (preg_match('/Windows NT/i', $ua)) {
                    $platform = 'Windows';
                } elseif (preg_match('/Mac OS X|Macintosh/i', $ua)) {
                    $platform = 'macOS';
                } elseif (preg_match('/Android/i', $ua)) {
                    $platform = 'Android';
                } elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) {
                    $platform = 'iOS';
                } elseif (preg_match('/Linux/i', $ua)) {
                    $platform = 'Linux';
                }

                // Basic browser detection (order matters)
                $browser = 'Unknown';
                if (preg_match('/EdgA|Edg|Edge\/|Edge/i', $ua)) {
                    $browser = 'Edge';
                } elseif (preg_match('/OPR\/|Opera/i', $ua)) {
                    $browser = 'Opera';
                } elseif (preg_match('/CriOS\/|Chrome\/([0-9.]+)/i', $ua) && !preg_match('/Edg|OPR|Opera/i', $ua)) {
                    $browser = 'Chrome';
                } elseif (preg_match('/Firefox\/([0-9.]+)/i', $ua)) {
                    $browser = 'Firefox';
                } elseif (preg_match('/MSIE |Trident\//i', $ua)) {
                    $browser = 'Internet Explorer';
                } elseif (preg_match('/Version\/([0-9.]+).*Safari/i', $ua) && !preg_match('/Chrome|CriOS|Chromium/i', $ua)) {
                    $browser = 'Safari';
                }

                // Basic device type detection
                $device = 'Desktop';
                if (preg_match('/iPad|tablet|playbook|silk/i', $ua) && !preg_match('/Mobile/i', $ua)) {
                    $device = 'Tablet';
                } elseif (preg_match('/Mobile|Android.*Mobile|iPhone|iPod|BlackBerry|IEMobile|Opera Mini|Windows Phone/i', $ua)) {
                    $device = 'Mobile';
                }

                // Track visit with user_id (null for anonymous/unregistered users)
                Visits::create([
                    'user_id'    => Auth::id() ?? null,
                    'ip_address' => $req->ip() ?? '0.0.0.0',
                    'user_agent' => substr($ua, 0, 191),
                    'browser'    => substr($browser, 0, 64),
                    'device'     => substr($device, 0, 64),
                    'platform'   => substr($platform, 0, 64),
                ]);
            } catch (\Throwable $e) {
                // swallow errors so site is not impacted
            }
        });
    }

    /**
     * Check if a path matches a pattern (supports wildcards)
     */
    private function pathMatches(string $path, string $pattern): bool
    {
        // Convert pattern to regex
        $pattern = str_replace('\*', '.*', preg_quote($pattern, '/'));
        return (bool) preg_match('/^' . $pattern . '$/', $path);
    }
}
