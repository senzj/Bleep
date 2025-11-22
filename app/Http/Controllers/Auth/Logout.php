<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

use App\Models\RememberedDevice;
use App\Models\Logs;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Logout extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $deviceToken = $request->cookie('device_token');
        $currentUserId = Auth::id();

        if ($deviceToken && Auth::check()) {
            $hashed = hash('sha256', $deviceToken);
            $query = RememberedDevice::where('user_id', Auth::id())
                ->where('token', $hashed);

            $action = env('REMEMBERED_LOGOUT', 'delete');

            if ($action === 'delete') {
                $deleted = $query->delete();
                if ($deleted) {
                    Logs::record($currentUserId, 'device_removed', ['method' => 'logout_cookie_delete', 'token_hash' => $hashed], $request);
                }
            } else { // rotate
                $newPlain = Str::random(64);
                $query->update([
                    'user_id' => null,
                    'token' => hash('sha256', $newPlain),
                    'last_used_at' => null,
                    'revoked_at' => now()
                ]);
                Logs::record($currentUserId, 'device_removed', ['method' => 'logout_rotate', 'rotated_to' => null], $request);
            }

            // Remove the device cookie (always)
            cookie()->queue(cookie()->forget('device_token'));
        }

        // Log logout (user id captured above)
        Logs::record($currentUserId, 'logout', null, $request);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Logged out successfully.');
    }
}
