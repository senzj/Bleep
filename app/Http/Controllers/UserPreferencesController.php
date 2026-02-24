<?php

namespace App\Http\Controllers;

use App\Models\UserPreferences;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserPreferencesController extends Controller
{
    /**
     * Get current user's preferences + all available sound files.
     *
     * Response shape:
     * {
     *   preferences: { ...columns },
     *   sounds: {
     *     system:   [ { source, category, name, filename, path, ext }, ... ],
     *     uploaded: [ { source, category, name, filename, path, ext }, ... ],
     *   }
     * }
     *
     * System sounds  → public/sounds/effects/ and public/sounds/notifications/
     *                  served at /sounds/{category}/{filename} (no symlink needed)
     *
     * Uploaded sounds → storage/app/public/sounds/user_{random}.{ext}
     *                   served at /storage/sounds/{filename} (via storage symlink)
     *                   accessible to all authenticated users
     */
    public function index()
    {
        $user        = Auth::user();
        $preferences = $user->getPreferences();

        return response()->json([
            'preferences' => $preferences,
            'sounds'      => self::availableSounds(),
        ]);
    }

    // ── Sound scanning ────────────────────────────────────────────────────────

    public static function availableSounds(): array
    {
        return [
            'system'   => self::scanSystemSounds(),
            'uploaded' => self::scanUploadedSounds(),
        ];
    }

    /**
     * Scan public/sounds/effects and public/sounds/notifications.
     * These are bundled with the app and served directly without storage symlink.
     */
    public static function scanSystemSounds(): array
    {
        $sounds     = [];
        $categories = [
            'effects'       => public_path('sounds/effects'),
            'notifications' => public_path('sounds/notifications'),
        ];

        foreach ($categories as $category => $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            foreach (new \DirectoryIterator($dir) as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $ext = strtolower($file->getExtension());
                if (!in_array($ext, ['mp3', 'wav', 'ogg', 'webm', 'm4a'])) {
                    continue;
                }

                $sounds[] = [
                    'source'   => 'system',
                    'category' => $category,
                    'name'     => self::humaniseName(pathinfo($file->getFilename(), PATHINFO_FILENAME)),
                    'filename' => $file->getFilename(),
                    'path'     => "/sounds/{$category}/{$file->getFilename()}",
                    'ext'      => $ext,
                ];
            }
        }

        usort($sounds, fn ($a, $b) =>
            [$a['category'], $a['name']] <=> [$b['category'], $b['name']]
        );

        return $sounds;
    }

    /**
     * Scan storage/app/public/sounds/ for user-uploaded files.
     * Only lists files matching the  user_{random}.{ext}  naming convention.
     */
    public static function scanUploadedSounds(): array
    {
        $sounds = [];
        $disk   = Storage::disk('public');

        if (!$disk->exists('sounds')) {
            return $sounds;
        }

        foreach ($disk->files('sounds') as $relativePath) {
            $filename = basename($relativePath);
            $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!in_array($ext, ['mp3', 'wav', 'ogg', 'webm', 'm4a'])) {
                continue;
            }

            // Only expose files that follow our upload naming convention
            if (!preg_match('/^user_[a-zA-Z0-9]+\.[a-z0-9]+$/', $filename)) {
                continue;
            }

            $sounds[] = [
                'source'   => 'uploaded',
                'category' => 'custom',
                'name'     => self::humaniseName(pathinfo($filename, PATHINFO_FILENAME)),
                'filename' => $filename,
                'path'     => '/storage/' . $relativePath,
                'ext'      => $ext,
            ];
        }

        return $sounds;
    }

    /**
     * "marimba-bloop-1" → "Marimba Bloop 1"
     * "user_abc123"     → "User Abc123"  (custom uploads shown generically)
     */
    public static function humaniseName(string $stem): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $stem));
    }

    /**
     * Verify a submitted sound path actually exists on disk.
     * Prevents arbitrary strings being stored in the preferences column.
     */
    protected function isValidSoundPath(string $path): bool
    {
        // System: /sounds/effects/{file} or /sounds/notifications/{file}
        if (preg_match('#^/sounds/(effects|notifications)/([^/]+)$#', $path, $m)) {
            return file_exists(public_path("sounds/{$m[1]}/{$m[2]}"));
        }

        // Uploaded: /storage/sounds/user_{random}.{ext}
        if (preg_match('#^/storage/sounds/(user_[a-zA-Z0-9]+\.[a-z0-9]+)$#', $path, $m)) {
            return Storage::disk('public')->exists("sounds/{$m[1]}");
        }

        return false;
    }

    // ── Single preference update ──────────────────────────────────────────────

    public function update(Request $request)
    {
        $user = Auth::user();

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
            'recieve_notification_sound',
            'send_notification_sound',
            'theme',
            'private_profile',
            'block_new_followers',
            'hide_online_status',
            'hide_activity',
        ];

        $validated = $request->validate([
            'key'   => ['required', 'string', 'in:' . implode(',', $validKeys)],
            'value' => ['required'],
        ]);

        $preferences = $user->preferences
            ?? $user->preferences()->create(UserPreferences::defaults());

        $key   = $validated['key'];
        $value = $validated['value'];

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
            if ($intValue < 1 || $intValue > 100) {
                return response()->json(['error' => 'Invalid bleeps per page value'], 422);
            }
            $preferences->update(['bleeps_per_page' => $intValue]);

        } elseif ($key === 'theme') {
            $preferences->update(['theme' => (string) $value]);

        } elseif (in_array($key, ['recieve_notification_sound', 'send_notification_sound'])) {
            $path = (string) $value;
            if ($path !== 'none' && !$this->isValidSoundPath($path)) {
                return response()->json(['error' => 'Invalid sound path'], 422);
            }
            $preferences->update([$key => $path]);

        } else {
            $preferences->update([$key => filter_var($value, FILTER_VALIDATE_BOOLEAN)]);
        }

        return response()->json([
            'success'    => true,
            'preference' => $key,
            'value'      => $preferences->fresh()->{$key},
        ]);
    }

    // ── Batch update ──────────────────────────────────────────────────────────

    public function batchUpdate(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'preferences'                            => ['required', 'array'],
            'preferences.nav_layout'                 => ['sometimes', 'string', 'in:horizontal,vertical'],
            'preferences.show_nsfw'                  => ['sometimes', 'boolean'],
            'preferences.blur_nsfw_media'            => ['sometimes', 'boolean'],
            'preferences.autoplay_videos'            => ['sometimes', 'boolean'],
            'preferences.autoplay_audio'             => ['sometimes', 'boolean'],
            'preferences.show_reposts_in_feed'       => ['sometimes', 'boolean'],
            'preferences.show_anonymous_bleeps'      => ['sometimes', 'boolean'],
            'preferences.default_feed_sort'          => ['sometimes', 'string', 'in:newest,popular,following'],
            'preferences.bleeps_per_page'            => ['sometimes', 'integer', 'min:1', 'max:100'],
            'preferences.recieve_notification_sound' => ['sometimes', 'string', 'max:255'],
            'preferences.send_notification_sound'    => ['sometimes', 'string', 'max:255'],
            'preferences.theme'                      => ['sometimes', 'string', 'max:50'],
            'preferences.private_profile'            => ['sometimes', 'boolean'],
            'preferences.block_new_followers'        => ['sometimes', 'boolean'],
            'preferences.hide_online_status'         => ['sometimes', 'boolean'],
            'preferences.hide_activity'              => ['sometimes', 'boolean'],
        ]);

        $preferences = $user->preferences
            ?? $user->preferences()->create(UserPreferences::defaults());

        $preferences->update($validated['preferences']);

        return response()->json([
            'success'     => true,
            'preferences' => $preferences->fresh(),
        ]);
    }

    // ── Sound upload ──────────────────────────────────────────────────────────

    /**
     * Upload a custom sound file (max 5 MB).
     * Stored at storage/app/public/sounds/user_{random12}.{ext}
     * Accessible at /storage/sounds/user_{random12}.{ext} for all users.
     *
     * Add this route to your api.php:
     *   Route::post('/api/preferences/sounds/upload',
     *       [\App\Http\Controllers\UserPreferencesController::class, 'uploadSound'])
     *       ->name('api.preferences.sounds.upload');
     */
    public function uploadSound(Request $request)
    {
        $request->validate([
            'sound' => ['required', 'file', 'mimes:mp3,wav,ogg,webm,m4a', 'max:5120'],
        ]);

        $file     = $request->file('sound');
        $ext      = strtolower($file->getClientOriginalExtension());
        $filename = 'user_' . Str::random(12) . '.' . $ext;

        $relativePath = $file->storeAs('sounds', $filename, 'public');

        return response()->json([
            'success'  => true,
            'filename' => $filename,
            'path'     => '/storage/' . $relativePath,
            'name'     => self::humaniseName(pathinfo($filename, PATHINFO_FILENAME)),
            'source'   => 'uploaded',
            'category' => 'custom',
            'ext'      => $ext,
        ], 201);
    }
}
