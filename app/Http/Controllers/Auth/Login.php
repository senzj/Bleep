<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Logs;
use App\Models\RememberedDevice;
use App\Models\User;
use App\Services\Notifications\AccountSecurityAlerts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class Login extends Controller
{
    /**
     * Alert the account owner after this many failed attempts from one address
     * within the hour. Repeats are reported every further threshold.
     */
    private const FAILED_SIGN_IN_ALERT_THRESHOLD = 3;

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

            // Re-accepting the policies at sign-in records the consent timestamps
            // for accounts created before the policy fields existed.
            $consentColumns = [
                'terms_accepted_at' => 'accept_terms',
                'privacy_accepted_at' => 'accept_privacy',
                'cookies_accepted_at' => 'accept_cookies',
                'age_confirmed_at' => 'confirm_age',
            ];

            $consentUpdates = [];

            foreach ($consentColumns as $column => $input) {
                if ($request->boolean($input) && $user->{$column} === null) {
                    $consentUpdates[$column] = now();
                }
            }

            if ($consentUpdates !== []) {
                $user->update($consentUpdates);
            }

            // Log successful login
            Logs::record($user->id, 'login', ['username' => $user->username, 'remember' => (bool) $remember], $request);

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

        $this->reportRepeatedFailures($request);

        // If login fails, redirect back with error
        return back()
            ->withErrors(['username' => 'Invalid credentials.'])
            ->onlyInput('username');
    }

    /**
     * Tell the account owner when their username keeps failing from one
     * address. The row just recorded is included in the count.
     */
    private function reportRepeatedFailures(Request $request): void
    {
        $attempts = Logs::query()
            ->where('action', 'failed_login')
            ->where('ip', $request->ip())
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($attempts === 0 || $attempts % self::FAILED_SIGN_IN_ALERT_THRESHOLD !== 0) {
            return;
        }

        $user = User::query()->where('username', $request->input('username'))->first();

        if ($user === null) {
            return;
        }

        app(AccountSecurityAlerts::class)->failedSignIn($user, $attempts, $request);
    }
}
