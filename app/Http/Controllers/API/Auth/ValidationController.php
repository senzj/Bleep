<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\User;
use App\Traits\HasAnonymousName;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ValidationController extends Controller
{
    public function checkUsername(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:255'
        ]);

        $username = $request->input('username');

        if (!preg_match('/^[a-zA-Z0-9_.]{3,20}$/', $username)) {
            return response()->json([
                'available' => false,
                'message' => 'Invalid username format'
            ]);
        }

        $exists = User::where('username', $username)->exists();

        return response()->json([
            'available' => !$exists,
            'message' => $exists ? 'Username already taken' : 'Username available'
        ]);
    }

    public function checkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $email = $request->input('email');
        $exists = User::where('email', $email)->exists();

        return response()->json([
            'available' => !$exists,
            'message' => $exists ? 'Email already registered' : 'Email available'
        ]);
    }

    /**
     * Generate a random username with embedded digits.
     * Keeps generating until an available username is found.
     */
    public function generateRandomUsername(Request $request)
    {
        $maxAttempts = 10;
        $attempt = 0;

        do {
            $username = HasAnonymousName::generateRandomUsername();
            $exists = User::where('username', $username)->exists();
            $attempt++;
        } while ($exists && $attempt < $maxAttempts);

        // If still not found after max attempts, append random numbers
        if ($exists) {
            $username = HasAnonymousName::generateRandomUsername() . rand(1000, 9999);
        }

        return response()->json([
            'username' => $username,
            'available' => true,
            'message' => 'Random username generated successfully'
        ]);
    }
}
