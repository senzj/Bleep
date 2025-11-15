<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\RememberedDevice;
use Symfony\Component\HttpFoundation\Response;

class CheckRememberedDevice
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $deviceToken = $request->cookie('device_token');

            if ($deviceToken) {
                $hashedToken = hash('sha256', $deviceToken);
                $device = RememberedDevice::where('user_id', Auth::id())
                    ->where('token', $hashedToken)
                    ->first();

                if ($device) {
                    $device->update(['last_used_at' => now()]);
                } else {
                    // Invalid token, clear cookie and log out
                    cookie()->queue(cookie()->forget('device_token'));
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                    return redirect()->route('login')->with('error', 'Device not recognized. Please log in again.');
                }
            }
        }

        return $next($request);
    }
}
