<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationIsEnabled
{
    /**
     * Hide the registration page when an administrator has closed sign-ups.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            (bool) config('app.registration_enabled', true),
            403,
            'Registration is currently closed.',
        );

        return $next($request);
    }
}
