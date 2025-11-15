<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\RememberedDevice;

class Login extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        // Validate the input
        $credentials = $request->validate([
            'username' => 'required|exists:users,username',
            'password' => 'required',
        ]);

        $remember = $request->boolean('remember');

        // Attempt to log in
        if (Auth::attempt($credentials, $remember)) {
            // Regenerate session for security
            $request->session()->regenerate();

            $user = Auth::user();

            // If "Remember Me" is checked, create/update device token
            if ($remember) {
                $token = Str::random(60);

                // Create or update the remembered device
                RememberedDevice::createOrUpdateFromRequest($request, $token);

                // Prune old devices if > 5
                $user->pruneRememberedDevices();

                // Queue cookie: store plain token in secure HttpOnly cookie
                cookie()->queue(
                    cookie()->make('device_token', $token, 60 * 24 * 30 /* 30 days */, null, null, app()->environment('production'), true, false, 'Strict')
                );
            }

            // Redirect to intended page or home
            return redirect()->intended('/')->with('success', 'Welcome back!');
        }

        // If login fails, redirect back with error
        return back()
            ->withErrors(['username' => 'Invalid credentials.'])
            ->onlyInput('username');
    }
}
