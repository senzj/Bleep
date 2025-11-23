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
            'username' => 'required|string|max:255|unique:users,username|alpha_dash', // Add alpha_dash
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => [
                'required',
                'string',
                'confirmed',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]+$/'
            ],
            'timezone' => 'nullable|string|max:64',
            'profile_picture' => 'nullable|image|mimes:png,jpg,jpeg,gif,webp|max:5120',
        ], [
            // Display Name
            'display_name.required' => 'Please enter your display name.',
            'display_name.max' => 'Display name is too long (max 255 characters).',
            
            // Username
            'username.required' => 'Please choose a username.',
            'username.unique' => 'This username is already taken.',
            'username.alpha_dash' => 'Username can only contain letters, numbers, dashes and underscores.',
            'username.max' => 'Username is too long (max 255 characters).',
            
            // Email
            'email.required' => 'Please enter your email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'email.max' => 'Email is too long (max 255 characters).',
            
            // Password
            'password.required' => 'Please create a password.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.regex' => 'Password must meet all requirements above.',
            'password.confirmed' => 'Passwords do not match.',
            
            // Profile Picture
            'profile_picture.image' => 'File must be an image.',
            'profile_picture.mimes' => 'Image must be PNG, JPG, JPEG, GIF, or WEBP.',
            'profile_picture.max' => 'Image must not exceed 5MB.',
        ]);

        // Create user
        $user = User::create([
            'dname' => $validated['display_name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'timezone' => $validated['timezone'] ?? 'UTC',
        ]);

        // Handle profile picture upload
        if ($request->hasFile('profile_picture') && $request->file('profile_picture')->isValid()) {
            $user->update([
                'profile_picture' => MediaUploadService::saveProfileImage(
                    $request->file('profile_picture'),
                    $user->username
                )
            ]);
        }

        return redirect('/login')->with('success', 'Account created! Please sign in.');
    }
}