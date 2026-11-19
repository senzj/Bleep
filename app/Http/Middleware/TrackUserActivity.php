<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    /**
     * Remember when a signed-in member was last active.
     *
     * The sessions table is wiped on logout and pruned on expiry, so it can
     * only answer "online now". This column keeps the real last-seen moment
     * and is written at most once every few minutes to avoid a write on every
     * request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            $cutoff = now()->subMinutes(2);

            if (! $user->last_active_at || $user->last_active_at->lt($cutoff)) {
                // Do not touch updated_at: it drives profile ordering.
                $user->timestamps = false;
                $user->forceFill(['last_active_at' => now()])->saveQuietly();
                $user->timestamps = true;
            }
        }

        return $next($request);
    }
}
