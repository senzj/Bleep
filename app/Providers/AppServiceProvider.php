<?php

namespace App\Providers;

use App\Models\Bleep;
use App\Models\Visits;
use App\Models\Comments;
use App\Policies\BleepPolicy;
use App\Policies\CommentsPolicy;
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

        // Records site visits
        Event::listen(RequestHandled::class, function (RequestHandled $event) {
            try {
                $req = $event->request;
                $ua = $req->header('User-Agent') ?? '';

                // basic platform detection
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

                // basic browser detection (order matters)
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

                // basic device type detection
                $device = 'Desktop';
                if (preg_match('/iPad|tablet|playbook|silk/i', $ua) && !preg_match('/Mobile/i', $ua)) {
                    $device = 'Tablet';
                } elseif (preg_match('/Mobile|Android.*Mobile|iPhone|iPod|BlackBerry|IEMobile|Opera Mini|Windows Phone/i', $ua)) {
                    $device = 'Mobile';
                }

                Visits::create([
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
}
