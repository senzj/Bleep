<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class UserTime
{
    /**
     * The timezone the signed-in viewer expects to read dates in.
     */
    public static function zone(): string
    {
        return auth()->user()?->timezone ?: config('app.timezone');
    }

    /**
     * Format a stored (UTC) timestamp in the viewer's own timezone.
     */
    public static function at(?CarbonInterface $value, string $format = 'M d, Y H:i'): string
    {
        return $value === null ? '-' : $value->copy()->timezone(self::zone())->format($format);
    }

    /**
     * Format a timestamp read from a plain-text source such as the app log.
     */
    public static function atString(?string $value, string $format = 'Y-m-d H:i:s'): string
    {
        if ($value === null || trim($value) === '') {
            return '-';
        }

        try {
            return Carbon::parse($value, config('app.timezone'))
                ->timezone(self::zone())
                ->format($format);
        } catch (\Throwable) {
            return $value;
        }
    }
}
