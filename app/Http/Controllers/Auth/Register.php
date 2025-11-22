<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;

use App\Services\MediaUploadService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class Register extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|confirmed|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]+$/',
            'timezone' => 'nullable|string|max:64',
            'profile_picture' => 'nullable|image|mimes:png,jpg,jpeg,gif,webp|max:5120',
        ], [
            'display_name.required' => 'Display name is required.',
            'username.required' => 'Username is required.',
            'email.required' => 'Email is required.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.regex' => 'Password must include at least one uppercase and lowercase letter, one number, and one special character.',
            'password.confirmed' => 'Password confirmation does not match.',
            'profile_picture.image' => 'The profile picture must be an image.',
            'profile_picture.mimes' => 'The profile picture must be a PNG, JPG, JPEG, GIF, or WEBP file.',
            'profile_picture.max' => 'The profile picture must not exceed 5MB.',
        ]);

        // Create user
        $user = User::create([
            'dname' => $validated['display_name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'timezone' => $validated['timezone'] ?? 'UTC',
        ]);

        // Handle profile picture upload - ONE LINE!
        if ($request->hasFile('profile_picture') && $request->file('profile_picture')->isValid()) {
            $user->update([
                'profile_picture' => MediaUploadService::saveProfileImage(
                    $request->file('profile_picture'),
                    $user->username
                )
            ]);
        }

        return redirect('/login')->with('success', 'Registration successful! You can now log in.');
    }
}