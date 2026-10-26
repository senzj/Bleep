<?php

namespace App\Services\Moderation;

use App\Enums\NotificationType;
use App\Models\Reports;
use App\Models\User;
use App\Models\UserWarning;
use App\Services\Notifications\NotificationService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Warnings are the source of truth for strikes. `users.report_strikes` is kept
 * as a cache so existing moderation screens and the auto-ban ladder keep
 * working without an extra query per row.
 */
class WarningService
{
    public function __construct(
        private NotificationService $notifications,
        private BanService $bans,
    ) {}

    /**
     * @return array{warning: UserWarning, banned: bool, strikes: int}
     */
    public function issue(
        User $user,
        string $reason,
        ?string $notes = null,
        ?User $moderator = null,
        ?Reports $report = null,
        ?CarbonInterface $expiresAt = null,
    ): array {
        $expiresAt ??= now()->addDays((int) config('moderation.warning_expiry_days'));

        $warning = $user->warnings()->create([
            'moderator_id' => $moderator?->id,
            'report_id' => $report?->id,
            'reason' => $reason,
            'notes' => $notes,
            'expires_at' => $expiresAt,
        ]);

        $user->forceFill(['last_strike_at' => now()])->save();
        $strikes = $this->syncStrikes($user);

        $expires = $expiresAt ? Carbon::instance($expiresAt)->toFormattedDateString() : null;

        $this->notifications->notify($user, NotificationType::WarningIssued, [
            'url' => route('notifications.index'),
            'data' => [
                'reason' => ucfirst($reason),
                'notes' => $notes,
                'expires_at' => $expires,
                'strikes' => $strikes,
            ],
        ]);

        $this->notifications->notifyStaff(NotificationType::AdminWarningIssued, [
            'subject' => $user,
            'url' => route('admin.users', ['q' => $user->username]),
            'data' => [
                'target_name' => $user->dname.' (@'.$user->username.')',
                'reason' => ucfirst($reason),
                'notes' => $notes,
                'strikes' => $strikes,
            ],
        ], $moderator);

        $banned = $this->maybeAutoBan($user, $strikes);

        return [
            'warning' => $warning,
            'banned' => $banned,
            'strikes' => $strikes,
        ];
    }

    public function revoke(UserWarning $warning, ?User $moderator = null): UserWarning
    {
        if ($warning->revoked_at === null) {
            $warning->forceFill([
                'revoked_at' => now(),
                'revoked_by' => $moderator?->id,
            ])->save();
        }

        $this->syncStrikes($warning->user);

        return $warning;
    }

    /**
     * Recompute the cached strike count from active warnings.
     */
    public function syncStrikes(User $user): int
    {
        $count = $this->activeCount($user);

        $user->forceFill(['report_strikes' => $count])->save();

        return $count;
    }

    public function activeCount(User $user): int
    {
        return $user->warnings()->active()->count();
    }

    private function maybeAutoBan(User $user, int $strikes): bool
    {
        $threshold = (int) config('moderation.auto_ban_at_strikes');

        if ($threshold <= 0 || $strikes < $threshold || $user->is_banned) {
            return false;
        }

        $this->bans->ban(
            $user,
            $this->bans->nextBanDuration($user),
            "Accumulated {$strikes} active warnings",
        );

        return true;
    }
}
