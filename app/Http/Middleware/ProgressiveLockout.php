<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * ProgressiveLockout
 *
 * Escalating lockout durations based on failed attempt count.
 * Keyed by IP + optional route-specific prefix so /login and /register
 * track separately.
 *
 * Lockout tiers (configurable via $tiers):
 *   1–3   attempts  → no lockout, just counting
 *   4–5   attempts  → 30 second lockout
 *   6–8   attempts  → 5 minute lockout
 *   9–11  attempts  → 30 minute lockout
 *   12+   attempts  → 24 hour lockout
 *   17+   attempts  → 1 week lockout
 *   19+   attempts  → 2 week lockout
 */
class ProgressiveLockout
{
    /**
     * Lockout tiers: [min_attempts => lockout_seconds]
     * Checked in reverse order (highest first).
     */
    protected array $tiers = [
        19 => 1209600, // 2 weeks
        17 => 604800, // 1 week
        13 => 86400,  // 24 hours
        9  => 1800,   // 30 minutes
        7  => 300,    // 5 minutes
        6  => 60,     // 1 minute
        3  => 30,     // 30 seconds
    ];

    /**
     * How long to remember attempts (seconds).
     * Reset after this window if no lockout is active.
     */
    protected int $attemptWindow = 3600; // 1 hour

    public function handle(Request $request, Closure $next, string $prefix = 'default'): Response
    {
        $key        = $this->cacheKey($request, $prefix);
        $lockoutKey = $key . ':lockout';
        $attemptsKey = $key . ':attempts';

        // Check if currently locked out
        $lockedUntil = Cache::get($lockoutKey);
        if ($lockedUntil && now()->timestamp < $lockedUntil) {
            $remaining = $lockedUntil - now()->timestamp;
            return $this->lockoutResponse($remaining, Cache::get($attemptsKey, 0));
        }

        // Pass request through
        $response = $next($request);

        // On failure, increment and maybe lock out
        if ($this->isFailedAttempt($response, $request)) {
            $attempts = Cache::increment($attemptsKey);
            if ($attempts === 1) {
                // Start the sliding window on first attempt
                Cache::put($attemptsKey, 1, $this->attemptWindow);
            }

            $lockoutSeconds = $this->lockoutFor($attempts);
            if ($lockoutSeconds > 0) {
                $until = now()->timestamp + $lockoutSeconds;
                Cache::put($lockoutKey, $until, $lockoutSeconds + 60);

                Log::warning('[ProgressiveLockout] Lockout applied', [
                    'ip'       => $request->ip(),
                    'prefix'   => $prefix,
                    'attempts' => $attempts,
                    'duration' => $lockoutSeconds,
                ]);

                return $this->lockoutResponse($lockoutSeconds, $attempts);
            }
        }

        // On success, clear the counter
        if ($this->isSuccessfulAttempt($response)) {
            Cache::forget($attemptsKey);
            Cache::forget($lockoutKey);
        }

        return $response;
    }

    /**
     * Build a stable cache key from IP + optional user agent fingerprint.
     */
    protected function cacheKey(Request $request, string $prefix): string
    {
        $ip = $request->ip();
        // Optionally mix in a UA hash to avoid punishing shared IPs too harshly
        // while still catching automated tools. Comment out if not needed.
        $uaHash = substr(md5($request->userAgent() ?? ''), 0, 8);
        return "lockout:{$prefix}:{$ip}:{$uaHash}";
    }

    /**
     * Determine lockout duration for this attempt count.
     * Returns 0 if no lockout should be applied yet.
     */
    protected function lockoutFor(int $attempts): int
    {
        // Check tiers from highest threshold downward
        $sorted = $this->tiers;
        krsort($sorted);

        foreach ($sorted as $threshold => $seconds) {
            if ($attempts >= $threshold) {
                return $seconds;
            }
        }

        return 0;
    }

    /**
     * Detect a failed attempt. Works for both JSON API and redirect responses.
     *
     * - 401 Unauthorized
     * - 422 Validation error (wrong password / bad credentials)
     * - Redirect back with 'errors' in session (classic form submit)
     */
    protected function isFailedAttempt(Response $response, Request $request): bool
    {
        $status = $response->getStatusCode();

        if (in_array($status, [401, 422])) {
            return true;
        }

        // Laravel redirects back on failed auth with session errors
        if ($status === 302) {
            $session = $request->session();
            return $session->has('errors')
                && $session->get('errors')?->any();
        }

        return false;
    }

    /**
     * Detect a successful attempt (2xx or redirect to home/dashboard).
     */
    protected function isSuccessfulAttempt(Response $response): bool
    {
        $status = $response->getStatusCode();
        return $status >= 200 && $status < 300;
    }

    /**
     * Return a lockout response appropriate to the request type.
     */
    protected function lockoutResponse(int $remainingSeconds, int $attempts): Response
    {
        $minutes = ceil($remainingSeconds / 60);
        $message = $remainingSeconds < 60
            ? "Too many attempts. Try again in {$remainingSeconds} seconds."
            : "Too many attempts. Try again in {$minutes} minute(s).";

        if (request()->expectsJson() || request()->is('api/*')) {
            return response()->json([
                'message'            => $message,
                'retry_after'        => $remainingSeconds,
                'attempts'           => $attempts,
            ], 429);
        }

        return response()->view('errors.429', [
            'message'         => $message,
            'retry_after'     => $remainingSeconds,
            'retry_after_min' => $minutes,
            'attempts'        => $attempts,
        ], 429);
    }
}
