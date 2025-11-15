<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Session;
use App\Models\Device;
use App\Models\RememberedDevice;

class SettingsController extends Controller
{
    /**
     * Profile Management
     */
    public function editProfile(Request $request)
    {
        return view('settings.profile', [
            'user' => $request->user(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'dname' => ['required', 'string', 'max:60'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'bio' => ['nullable', 'string', 'max:300'],
            'profile_picture' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('profile_picture')) {
            // Delete old picture if exists
            if ($user->profile_picture) {
                Storage::disk('public')->delete($user->profile_picture);
            }

            $file = $request->file('profile_picture');
            $path = $file->store('avatars', 'public');
            $validated['profile_picture'] = $path;
        }

        // Update timezone if it has changed
        $detectedTimezone = $request->header('X-Timezone');
        if ($detectedTimezone && $user->timezone !== $detectedTimezone) {
            $validated['timezone'] = $detectedTimezone;
        }

        $user->update($validated);

        return redirect()->route('settings.profile')->with('success', 'Profile updated.');
    }

    /**
     * Password Management
     */
    public function editPassword(Request $request)
    {
        return view('settings.password');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('settings.password')->with('success', 'Password updated.');
    }

    /**
     * Device and Session Management
     */
    public function devices(Request $request)
    {
        $user = $request->user();

        // active sessions for the current user (paginated)
        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderBy('last_activity', 'desc')
            ->paginate(10);

        // remembered devices (paginated)
        $devices = RememberedDevice::where('user_id', $user->id)
            ->orderBy('last_used_at', 'desc')
            ->paginate(10);

        // helper users map for sessions view (load users for session entries, optional)
        $userIds = $sessions->pluck('user_id')->filter()->unique()->toArray();
        $users = \App\Models\User::whereIn('id', $userIds)->get()->keyBy('id');

        // current device token: pass hashed token to compare with stored token
        $plainCookie = $request->cookie('device_token');
        $currentDeviceToken = $plainCookie ? hash('sha256', $plainCookie) : null;

        $currentSessionId = session()->getId();

        return view('settings.devices', [
            'sessions' => $sessions,
            'devices' => $devices,
            'users' => $users,
            'currentDeviceToken' => $currentDeviceToken,
            'currentSessionId' => $currentSessionId,
        ]);
    }

    public function revokeSession(Request $request, $sessionId)
    {
        $user = $request->user();

        $deleted = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->id)
            ->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => $deleted > 0,
                'message' => $deleted > 0 ? 'Session logged out successfully.' : 'Session not found.'
            ]);
        }

        return redirect()->route('settings.devices')->with('success', 'Device session revoked.');
    }

    public function revokeDevice(Request $request, $deviceId)
    {
        $user = $request->user();

        $deleted = DB::table('remembered_devices')
            ->where('id', $deviceId)
            ->where('user_id', $user->id)
            ->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => $deleted > 0,
                'message' => $deleted > 0 ? 'Device removed successfully.' : 'Device not found.'
            ]);
        }

        return redirect()->route('settings.devices')->with('success', 'Device revoked.');
    }

    /**
     * Account Logs
     */
}
