<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
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
}
