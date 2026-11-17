<?php

namespace App\Services\Notifications;

use App\Enums\NotificationType;
use App\Helpers\UserAgentParser;
use App\Mail\SecurityAlert;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Account and device security notices.
 *
 * Every sign-in, sign-out and account change lands in the bell so a member can
 * spot activity they do not recognise. Email delivery is opt-in through
 * `mail.security_alerts.enabled` and shares the same copy, so turning it on
 * later needs no rewrite.
 */
class AccountSecurityAlerts
{
    public function __construct(private NotificationService $notifications) {}

    public function signedIn(User $user, ?Request $request = null): void
    {
        $this->send(
            $user,
            NotificationType::SignedIn,
            'New sign-in to your account',
            'Your account was signed in to from this device.',
            $request,
            route('settings.logs'),
        );
    }

    public function signedOut(User $user, ?Request $request = null): void
    {
        $this->send(
            $user,
            NotificationType::SignedOut,
            'Your account was signed out',
            'This device signed out of your account.',
            $request,
            route('settings.logs'),
        );
    }

    public function failedSignIn(User $user, int $attempts, ?Request $request = null): void
    {
        $this->send(
            $user,
            NotificationType::FailedSignIn,
            'Repeated failed sign-ins on your account',
            "There have been {$attempts} failed sign-in attempts on your account in the last hour.",
            $request,
            route('settings.logs'),
            ['attempts' => $attempts],
        );
    }

    public function passwordChanged(User $user, ?Request $request = null): void
    {
        $this->send(
            $user,
            NotificationType::PasswordChanged,
            'Your password was changed',
            'Your password was just changed. If this was not you, reset it and review your active devices.',
            $request,
            route('settings.devices'),
        );
    }

    public function emailChanged(User $user, string $email, ?Request $request = null): void
    {
        $this->send(
            $user,
            NotificationType::EmailChanged,
            'Your email address was changed',
            "Your sign-in email is now {$email}. If this was not you, secure your account now.",
            $request,
            route('settings.profile'),
            ['email' => $email],
        );
    }

    public function deviceRemoved(User $user, ?Request $request = null): void
    {
        $this->send(
            $user,
            NotificationType::DeviceRemoved,
            'A device was removed from your account',
            'A remembered device can no longer sign in to your account.',
            $request,
            route('settings.devices'),
        );
    }

    public function sessionRevoked(User $user, ?Request $request = null): void
    {
        $this->send(
            $user,
            NotificationType::SessionRevoked,
            'A session was signed out',
            'An active session was signed out from your account.',
            $request,
            route('settings.devices'),
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function send(
        User $user,
        NotificationType $type,
        string $headline,
        string $notice,
        ?Request $request,
        string $url,
        array $extra = [],
    ): void {
        $data = array_filter([
            'device' => $this->device($request),
            'ip' => $request?->ip(),
        ] + $extra, fn ($value): bool => $value !== null && $value !== '');

        $this->notifications->notify($user, $type, [
            'url' => $url,
            'data' => $data,
        ]);

        $this->email($user, $headline, $notice, $data['ip'] ?? null);
    }

    private function device(?Request $request): ?string
    {
        $agent = $request?->userAgent();

        if ($agent === null || trim($agent) === '') {
            return null;
        }

        $os = UserAgentParser::parseOSName($agent);
        $browser = UserAgentParser::parseBrowserName($agent);

        if ($os === 'Unknown' && $browser === 'Other') {
            return 'Unknown device';
        }

        return trim($os.' · '.$browser, ' ·');
    }

    private function email(User $user, string $headline, string $notice, ?string $ip): void
    {
        if (! config('mail.security_alerts.enabled') || blank($user->email)) {
            return;
        }

        try {
            Mail::to($user->email)->queue(new SecurityAlert($user, $headline, $notice, $ip));
        } catch (Throwable $exception) {
            Log::warning('Security alert email could not be queued.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
