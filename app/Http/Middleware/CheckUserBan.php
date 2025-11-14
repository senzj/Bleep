<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckUserBan
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Check if user is banned
            if ($user->is_banned) {
                // If ban has expired, unban them
                if ($user->banned_until && now()->isAfter($user->banned_until)) {
                    $user->update([
                        'is_banned' => false,
                        'banned_until' => null,
                        'ban_reason' => null,
                    ]);
                    return $next($request);
                }

                // Allow access to banned page and logout
                if ($request->routeIs('banned') || $request->routeIs('logout')) {
                    return $next($request);
                }

                // Redirect to banned page
                return redirect()->route('banned');
            }
        }

        return $next($request);
    }
}
