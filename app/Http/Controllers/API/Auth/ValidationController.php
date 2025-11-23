<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\User;
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
}
