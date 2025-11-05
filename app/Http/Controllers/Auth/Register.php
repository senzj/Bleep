<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class Register extends Controller
{
    /**
     * Handle any incoming request.
     */
    public function __invoke(Request $request)
    {
        // Validate the input
        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'timezone' => 'nullable|string|timezone',
        ]);

        // Fetch timezone from request or default to UTC
        $timezone = $validated['timezone'] ?? 'UTC';

        Log::info('User timezone detected: ' . $timezone);

        // Create the user
        $user = User::create([
            'dname' => $validated['display_name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'timezone' => $timezone,
        ]);

        // Log them in
        Auth::login($user);

        // Redirect to home
        return redirect('/')->with('success', 'Welcome to Bleep!');
    }
}
