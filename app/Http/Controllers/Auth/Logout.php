<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str; // added for rotation
use App\Models\RememberedDevice;

class Logout extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $deviceToken = $request->cookie('device_token');
        if ($deviceToken && Auth::check()) {
            $hashed = hash('sha256', $deviceToken);
            $query = RememberedDevice::where('user_id', Auth::id())
                ->where('token', $hashed);

            // Configurable action: 'delete' (default), or 'rotate' (clear user_id & rotate token)
            $action = env('REMEMBERED_LOGOUT', 'delete');

            if ($action === 'delete') {
                $query->delete();
            } else { // rotate: keep row but clear user_id and rotate token / mark revoked
                $newPlain = Str::random(64);
                $query->update([
                    'user_id' => null,
                    'token' => hash('sha256', $newPlain),
                    'last_used_at' => null,
                    'revoked_at' => now()
                ]);
            }

            // Remove the device cookie (always)
            cookie()->queue(cookie()->forget('device_token'));
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Logged out successfully.');
    }
}
