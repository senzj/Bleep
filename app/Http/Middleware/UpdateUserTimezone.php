<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UpdateUserTimezone
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            $detectedTimezone = $request->header('X-Timezone');

            // Only update if timezone is different and detected
            if ($detectedTimezone && $user->timezone !== $detectedTimezone) {
                $user->update(['timezone' => $detectedTimezone]);
            }
        }

        return $next($request);
    }
}
