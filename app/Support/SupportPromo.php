<?php

namespace App\Support;

class SupportPromo
{
    /**
     * Chance that the very first feed render of a session shows the card.
     */
    public const FIRST_FETCH_CHANCE = 0.5;

    /**
     * Consecutive skipped renders after a card before the next one is
     * guaranteed, so a run of unlucky rolls cannot hide the card forever.
     */
    public const SKIPS_BEFORE_GUARANTEE = 2;

    /**
     * Decide where the support card belongs in a rendered page of bleeps.
     *
     * The first render of a session rolls a coin flip and the second is
     * guaranteed when the first skipped it. From then on each render uses the
     * configured chance, except: the render straight after a card never shows
     * one, and two quiet renders in a row force the next one through.
     *
     * @return int|null Zero-based index of the bleep the card sits before, or
     *                  null when this render should not show the card.
     */
    public static function position(int $bleepCount): ?int
    {
        if ($bleepCount < 1) {
            return null;
        }

        $fetches = (int) session('support_promo.fetches', 0) + 1;
        $lastShownAt = session('support_promo.last_shown_at');
        $lastShownAt = is_numeric($lastShownAt) ? (int) $lastShownAt : null;

        session(['support_promo.fetches' => $fetches]);

        if (! self::shouldShow($fetches, $lastShownAt, self::roll())) {
            return null;
        }

        session(['support_promo.last_shown_at' => $fetches]);

        return random_int(0, $bleepCount - 1);
    }

    /**
     * Decide a single render. Kept separate from the session bookkeeping so
     * the whole schedule can be tested without relying on randomness.
     *
     * @param  int  $fetches  Feed renders this session has made, including this one.
     * @param  int|null  $lastShownAt  Fetch number that last showed the card.
     * @param  float  $roll  Random value in [0, 1).
     */
    public static function shouldShow(int $fetches, ?int $lastShownAt, float $roll): bool
    {
        $chance = (float) config('features.support_promo_chance', 0.2);

        // Zero is the kill switch: no card, whatever the schedule says.
        if ($chance <= 0) {
            return false;
        }

        if ($fetches === 1) {
            return $roll < self::FIRST_FETCH_CHANCE;
        }

        // Second look: the first render skipped it, so this one is guaranteed.
        if ($fetches === 2 && $lastShownAt === null) {
            return true;
        }

        // Never back to back: the render after a card always stays quiet.
        if ($lastShownAt !== null && $fetches - $lastShownAt === 1) {
            return false;
        }

        // A short unlucky streak ends with a guaranteed card.
        $skipped = $fetches - 1 - ($lastShownAt ?? 0);

        if ($skipped >= self::SKIPS_BEFORE_GUARANTEE) {
            return true;
        }

        return $roll < min($chance, 1);
    }

    private static function roll(): float
    {
        return random_int(0, 9999) / 10000;
    }
}
