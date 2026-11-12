<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Shared edit/delete windows for user-generated content.
 *
 * The same rules apply to bleeps, comments, chat messages and bulletin notes:
 * the author may edit for 10 minutes and delete for one week. Server checks and
 * the UI both read from here so they cannot drift apart.
 */
class ContentWindowService
{
    public const EDIT_WINDOW_MINUTES = 10;

    public const DELETE_WINDOW_DAYS = 7;

    public static function canEdit(?CarbonInterface $createdAt): bool
    {
        if (! $createdAt) {
            return false;
        }

        return Carbon::instance($createdAt)
            ->addMinutes(self::EDIT_WINDOW_MINUTES)
            ->isFuture();
    }

    public static function canDelete(?CarbonInterface $createdAt): bool
    {
        if (! $createdAt) {
            return false;
        }

        return Carbon::instance($createdAt)
            ->addDays(self::DELETE_WINDOW_DAYS)
            ->isFuture();
    }

    public static function editDeadline(?CarbonInterface $createdAt): ?CarbonInterface
    {
        return $createdAt
            ? Carbon::instance($createdAt)->addMinutes(self::EDIT_WINDOW_MINUTES)
            : null;
    }

    public static function deleteDeadline(?CarbonInterface $createdAt): ?CarbonInterface
    {
        return $createdAt
            ? Carbon::instance($createdAt)->addDays(self::DELETE_WINDOW_DAYS)
            : null;
    }
}
