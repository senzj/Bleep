<?php

namespace App\Services\Moderation;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Owns the suspension ladder and every ban/unban side effect so the report
 * dashboard and the admin user editor cannot drift apart.
 */
class BanService
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * Duration of the next ban in the ladder, based on how many times the user
     * has already been suspended.
     */
    public function nextBanDuration(User $user): Carbon
    {
        return match ((int) $user->suspension_count) {
            0 => now()->addDay(),
            1 => now()->addWeek(),
            2 => now()->addMonth(),
            3 => now()->addMonths(3),
            4 => now()->addMonths(6),
            default => now()->addYear(),
        };
    }

    /**
     * @param  CarbonInterface|null  $until  null means a permanent ban.
     */
    public function ban(User $user, ?CarbonInterface $until, string $reason, ?User $issuer = null): User
    {
        $user->forceFill([
            'is_banned' => true,
            'banned_until' => $until,
            'ban_reason' => $reason,
            'suspension_count' => (int) $user->suspension_count + 1,
        ])->save();

        $expires = $until ? Carbon::instance($until)->toFormattedDateString() : null;

        $this->notifications->notify($user, NotificationType::AccountBanned, [
            'url' => route('banned'),
            'data' => [
                'reason' => $reason,
                'expires_at' => $expires,
            ],
        ]);

        $this->notifications->notifyStaff(NotificationType::AdminBanIssued, [
            'subject' => $user,
            'url' => route('admin.users', ['q' => $user->username]),
            'data' => [
                'target_name' => $this->displayName($user),
                'reason' => $reason,
                'expires_at' => $expires,
                'actor_name' => $issuer ? null : 'System',
            ],
        ], $issuer);

        return $user;
    }

    public function unban(User $user, ?User $issuer = null): User
    {
        $user->forceFill([
            'is_banned' => false,
            'banned_until' => null,
            'ban_reason' => null,
        ])->save();

        $this->notifications->notify($user, NotificationType::AccountUnbanned, [
            'url' => route('home'),
        ]);

        $this->notifications->notifyStaff(NotificationType::AdminUnbanned, [
            'subject' => $user,
            'url' => route('admin.users', ['q' => $user->username]),
            'data' => [
                'target_name' => $this->displayName($user),
                'actor_name' => $issuer ? null : 'System',
            ],
        ], $issuer);

        return $user;
    }

    private function displayName(User $user): string
    {
        return $user->dname.' (@'.$user->username.')';
    }
}
