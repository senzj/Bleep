<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|confirmed|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]+$/',
            'timezone' => 'nullable|string|max:64',
            'profile_picture' => 'nullable|string',
        ],[
            'display_name.required' => 'Display name is required.',
            'username.required' => 'Username is required.',
            'email.required' => 'Email is required.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.regex' => 'Password must include at least one uppercase and lowercase letter, one number, and one special character.',
            'password.confirmed' => 'Password confirmation does not match.',
            'timezone.string' => 'Invalid timezone.',
        ]);

        // Fetch timezone from request or default to UTC
        $timezone = $validated['timezone'] ?? 'UTC';

        // Handle profile picture upload (convert to JPG and store under public disk)
        $profilePicturePath = null;
        if (!empty($validated['profile_picture'])) {
            // quick server-side validation of the base64 image data
            $base64 = $validated['profile_picture'];
            if (preg_match('/^data:(image\/[a-zA-Z]+);base64,/', $base64, $m)) {
                $rawBase64 = substr($base64, strpos($base64, ',') + 1);
            } else {
                $rawBase64 = $base64; // allow raw base64 too
            }
            $rawBase64 = str_replace(' ', '+', $rawBase64);
            $bin = base64_decode($rawBase64, true);
            if ($bin === false) {
                return back()->withInput()->withErrors(['profile_picture' => 'Invalid picture format (could not decode base64).']);
            }
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->buffer($bin);
            $allowed = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($detected, $allowed, true)) {
                return back()->withInput()->withErrors(['profile_picture' => 'Invalid picture format. Allowed: jpeg, png, gif.']);
            }

            // save (the saveBase64Image will re-decode and convert to jpg)
            $profilePicturePath = $this->saveBase64Image($validated['profile_picture'], $validated['username']);
        }

        // Create the user
        $user = User::create([
            'dname' => $validated['display_name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'timezone' => $timezone,
            'profile_picture' => $profilePicturePath,
        ]);

        // // Log them in
        // Auth::login($user);

        // // Redirect to home
        // return redirect('/')->with('success', 'Welcome to Bleep!');

        // redirect to login with success message
        return redirect('/login')->with('success', 'Registration successful! You can now log in.');
    }

    /**
     * Save base64 image (data URL or raw base64) to storage as JPEG and return storage path
     *
     * @param  string $base64String
     * @param  string $username
     * @return string|null  relative path on the public disk or null on failure
     */
    private function saveBase64Image(string $base64String, string $username): ?string
    {
        try {
            // If it's a data URL, strip the prefix
            if (preg_match('/^data:image\/(\w+);base64,/', $base64String, $matches)) {
                $base64String = substr($base64String, strpos($base64String, ',') + 1);
            }

            // Cleanup and decode
            $base64String = str_replace(' ', '+', $base64String);
            $imageData = base64_decode($base64String);
            if ($imageData === false) {
                return null;
            }

            // Create an image resource from the binary data
            $im = imagecreatefromstring($imageData);
            if ($im === false) {
                return null;
            }

            // Output JPEG to memory buffer
            ob_start();
            imagejpeg($im, null, 90); // quality 90
            $jpegData = ob_get_clean();
            imagedestroy($im);

            if ($jpegData === false || $jpegData === '') {
                return null;
            }

            // Build storage path: {username}/profile/{username}_profile.jpg
            $filename = $username . '_profile.jpg';
            $path = $username . '/profile/' . $filename;

            // Save to public disk
            Storage::disk('public')->put($path, $jpegData);

            return $path;
        } catch (\Throwable $e) {
            Log::error('Failed to save profile image: ' . $e->getMessage());
            return null;
        }
    }
}
