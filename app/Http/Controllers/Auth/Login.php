<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

use App\Models\RememberedDevice;
use App\Models\Logs;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Carbon\Carbon;

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

            // Log successful login
            Logs::record($user->id, 'login', ['username' => $user->username, 'remember' => (bool)$remember], $request);

            // If "Remember Me" is checked, create/update device token
            if ($remember) {
                // plain token stored in cookie; model will hash it when persisting comparisons are needed
                $plainToken = Str::random(64);

                // create or update remembered device row (handles existing cookie-device update)
                $device = RememberedDevice::createOrUpdateFromRequest($request, $plainToken);

                // Ensure user has at most 5 remembered devices
                $user->pruneRememberedDevices();

                // set cookie with plain token (minutes: 60*24*30 = 30 days)
                Cookie::queue('device_token', $plainToken, 60 * 24 * 30);
            }

            // Redirect to intended page or home
            return redirect()->intended('/')->with('success', 'Welcome back!');
        }

        // Log failed login attempt
        Logs::record(null, 'failed_login', ['username' => $request->input('username')], $request);

        // If login fails, redirect back with error
        return back()
            ->withErrors(['username' => 'Invalid credentials.'])
            ->onlyInput('username');
    }
}
