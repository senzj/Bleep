<?php

namespace App\Http\Controllers;

use App\Models\UserPreferences;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserPreferencesController extends Controller
{
    /**
     * Get current user's preferences
     */
    public function index()
    {
        $user = Auth::user();
        $preferences = $user->getPreferences();

        return response()->json($preferences);
    }

    /**
     * Update a single preference
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        // All valid preference keys
        $validKeys = [
            'nav_layout',
            'show_nsfw',
            'blur_nsfw_media',
            'autoplay_videos',
            'autoplay_audio',
            'show_reposts_in_feed',
            'show_anonymous_bleeps',
            'default_feed_sort',
            'bleeps_per_page',
            'send_notification_sound',
            'recieve_notification_sound',
            'theme',
            'private_profile',
            'block_new_followers',
            'hide_online_status',
            'hide_activity',
        ];

        $validated = $request->validate([
            'key' => ['required', 'string', 'in:' . implode(',', $validKeys)],
            'value' => ['required'],
        ]);

        // Get or create preferences
        $preferences = $user->preferences;
        if (!$preferences) {
            $preferences = $user->preferences()->create(UserPreferences::defaults());
        }

        $key = $validated['key'];
        $value = $validated['value'];

        // Handle string-type preferences
        if ($key === 'nav_layout') {
            if (!in_array($value, ['horizontal', 'vertical'])) {
                return response()->json(['error' => 'Invalid nav layout value'], 422);
            }
            $preferences->update(['nav_layout' => $value]);
        } elseif ($key === 'default_feed_sort') {
            if (!in_array($value, ['newest', 'popular', 'following'])) {
                return response()->json(['error' => 'Invalid feed sort value'], 422);
            }
            $preferences->update(['default_feed_sort' => $value]);
        } elseif ($key === 'bleeps_per_page') {
            $intValue = (int) $value;
            if (!in_array($intValue, [10, 15, 25, 50])) {
                return response()->json(['error' => 'Invalid bleeps per page value'], 422);
            }
            $preferences->update(['bleeps_per_page' => $intValue]);
        } elseif ($key === 'theme') {
            // Theme is a string value (theme name)
            $preferences->update(['theme' => (string) $value]);
        } else {
            // Boolean toggles
            $preferences->update([$key => filter_var($value, FILTER_VALIDATE_BOOLEAN)]);
        }

        return response()->json([
            'success' => true,
            'preference' => $key,
            'value' => $preferences->fresh()->{$key},
        ]);
    }

    /**
     * Batch update multiple preferences
     */
    public function batchUpdate(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.nav_layout' => ['sometimes', 'string', 'in:horizontal,vertical'],
            'preferences.show_nsfw' => ['sometimes', 'boolean'],
            'preferences.blur_nsfw_media' => ['sometimes', 'boolean'],
            'preferences.autoplay_videos' => ['sometimes', 'boolean'],
            'preferences.autoplay_audio' => ['sometimes', 'boolean'],
            'preferences.show_reposts_in_feed' => ['sometimes', 'boolean'],
            'preferences.show_anonymous_bleeps' => ['sometimes', 'boolean'],
            'preferences.default_feed_sort' => ['sometimes', 'string', 'in:newest,popular,following'],
            'preferences.bleeps_per_page' => ['sometimes', 'integer', 'in:10,15,25,50'],
            'preferences.desktop_notifications' => ['sometimes', 'boolean'],
            'preferences.theme' => ['sometimes', 'string', 'max:50'],
            'preferences.private_profile' => ['sometimes', 'boolean'],
            'preferences.block_new_followers' => ['sometimes', 'boolean'],
            'preferences.hide_online_status' => ['sometimes', 'boolean'],
            'preferences.hide_activity' => ['sometimes', 'boolean'],
        ]);

        $preferences = $user->preferences;
        if (!$preferences) {
            $preferences = $user->preferences()->create(UserPreferences::defaults());
        }

        $preferences->update($validated['preferences']);

        return response()->json([
            'success' => true,
            'preferences' => $preferences->fresh(),
        ]);
    }
}
