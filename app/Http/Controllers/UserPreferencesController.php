<?php

namespace App\Http\Controllers;

use App\Models\UserPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserPreferencesController extends Controller
{
    private const VALID_KEYS = [
        'nav_layout',
        'theme',
        'show_nsfw',
        'blur_nsfw_media',
        'show_reposts_in_feed',
        'show_anonymous_bleeps',
        'default_feed_sort',
        'bleeps_per_page',
        'recieve_notification_sound',
        'send_notification_sound',
        'private_profile',
        'block_new_followers',
        'hide_online_status',
        'hide_activity',
    ];

    private const BOOLEAN_KEYS = [
        'show_nsfw',
        'blur_nsfw_media',
        'show_reposts_in_feed',
        'show_anonymous_bleeps',
        'private_profile',
        'block_new_followers',
        'hide_online_status',
        'hide_activity',
    ];

    private const SOUND_KEYS = [
        'recieve_notification_sound',
        'send_notification_sound',
    ];

    private const AUDIO_EXTENSIONS = ['mp3', 'wav', 'ogg', 'webm', 'm4a'];

    // ── Public API ────────────────────────────────────────────────────────────

    public function index(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'preferences' => $user->getPreferences(),
            'sounds'      => self::availableSounds(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key'   => ['required', 'string', 'in:' . implode(',', self::VALID_KEYS)],
            'value' => ['required'],
        ]);

        $key   = $validated['key'];
        $value = $validated['value'];

        $error = $this->validateValue($key, $value);
        if ($error) {
            return response()->json(['success' => false, 'error' => $error], 422);
        }

        $preferences = $this->userPreferences();
        $preferences->update([$key => $this->castValue($key, $value)]);

        return response()->json([
            'success' => true,
            'key'     => $key,
            'value'   => $preferences->fresh()->{$key},
        ]);
    }

    public function batchUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences'                            => ['required', 'array'],
            'preferences.nav_layout'                 => ['sometimes', 'string', 'in:horizontal,vertical'],
            'preferences.theme'                      => ['sometimes', 'string', 'max:50'],
            'preferences.show_nsfw'                  => ['sometimes', 'boolean'],
            'preferences.blur_nsfw_media'            => ['sometimes', 'boolean'],
            'preferences.show_reposts_in_feed'       => ['sometimes', 'boolean'],
            'preferences.show_anonymous_bleeps'      => ['sometimes', 'boolean'],
            'preferences.default_feed_sort'          => ['sometimes', 'string', 'in:newest,popular,following'],
            'preferences.bleeps_per_page'            => ['sometimes', 'integer', 'min:1', 'max:100'],
            'preferences.recieve_notification_sound' => ['sometimes', 'string', 'max:255'],
            'preferences.send_notification_sound'    => ['sometimes', 'string', 'max:255'],
            'preferences.private_profile'            => ['sometimes', 'boolean'],
            'preferences.block_new_followers'        => ['sometimes', 'boolean'],
            'preferences.hide_online_status'         => ['sometimes', 'boolean'],
            'preferences.hide_activity'              => ['sometimes', 'boolean'],
        ]);

        $preferences = $this->userPreferences();
        $preferences->update($validated['preferences']);

        return response()->json([
            'success'     => true,
            'preferences' => $preferences->fresh(),
        ]);
    }

    public function uploadSound(Request $request): JsonResponse
    {
        $request->validate([
            'sound' => ['required', 'file', 'mimes:mp3,wav,ogg,webm,m4a', 'max:5120'],
        ]);

        $file     = $request->file('sound');
        $ext      = strtolower($file->getClientOriginalExtension());
        $filename = 'user_' . Str::random(12) . '.' . $ext;
        $path     = $file->storeAs('sounds', $filename, 'public');

        return response()->json([
            'success'  => true,
            'filename' => $filename,
            'path'     => '/storage/' . $path,
            'name'     => self::humaniseName(pathinfo($filename, PATHINFO_FILENAME)),
            'source'   => 'uploaded',
            'category' => 'custom',
            'ext'      => $ext,
        ], 201);
    }

    // ── Sound helpers ─────────────────────────────────────────────────────────

    public static function availableSounds(): array
    {
        return [
            'system'   => self::scanSystemSounds(),
            'uploaded' => self::scanUploadedSounds(),
        ];
    }

    public static function scanSystemSounds(): array
    {
        $sounds = [];

        foreach (['effects', 'notifications'] as $category) {
            $dir = public_path("sounds/{$category}");

            if (!is_dir($dir)) {
                continue;
            }

            foreach (new \DirectoryIterator($dir) as $file) {
                if (!$file->isFile()) {
                    continue;
                }

                $ext = strtolower($file->getExtension());

                if (!in_array($ext, self::AUDIO_EXTENSIONS)) {
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

        usort($sounds, fn ($a, $b) => [$a['category'], $a['name']] <=> [$b['category'], $b['name']]);

        return $sounds;
    }

    public static function scanUploadedSounds(): array
    {
        $disk = Storage::disk('public');

        if (!$disk->exists('sounds')) {
            return [];
        }

        return collect($disk->files('sounds'))
            ->filter(function (string $path) {
                $filename = basename($path);
                $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                return in_array($ext, self::AUDIO_EXTENSIONS)
                    && preg_match('/^user_[a-zA-Z0-9]+\.[a-z0-9]+$/', $filename);
            })
            ->map(function (string $path) {
                $filename = basename($path);
                $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                return [
                    'source'   => 'uploaded',
                    'category' => 'custom',
                    'name'     => self::humaniseName(pathinfo($filename, PATHINFO_FILENAME)),
                    'filename' => $filename,
                    'path'     => '/storage/' . $path,
                    'ext'      => $ext,
                ];
            })
            ->values()
            ->all();
    }

    public static function humaniseName(string $stem): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $stem));
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function userPreferences(): UserPreferences
    {
        $user = Auth::user();

        return $user->preferences
            ?? $user->preferences()->create(UserPreferences::defaults());
    }

    private function validateValue(string $key, mixed $value): ?string
    {
        return match (true) {
            $key === 'nav_layout'
                => in_array($value, ['horizontal', 'vertical'])
                    ? null : 'Invalid nav layout value',

            $key === 'default_feed_sort'
                => in_array($value, ['newest', 'popular', 'following'])
                    ? null : 'Invalid feed sort value',

            $key === 'bleeps_per_page'
                => ((int) $value >= 1 && (int) $value <= 100)
                    ? null : 'bleeps_per_page must be between 1 and 100',

            in_array($key, self::SOUND_KEYS)
                => ($value === 'none' || $this->isValidSoundPath((string) $value))
                    ? null : 'Invalid sound path',

            default => null,
        };
    }

    private function castValue(string $key, mixed $value): mixed
    {
        if (in_array($key, self::BOOLEAN_KEYS)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if ($key === 'bleeps_per_page') {
            return (int) $value;
        }

        return (string) $value;
    }

    private function isValidSoundPath(string $path): bool
    {
        if (preg_match('#^/sounds/(effects|notifications)/([^/]+)$#', $path, $m)) {
            return file_exists(public_path("sounds/{$m[1]}/{$m[2]}"));
        }

        if (preg_match('#^/storage/sounds/(user_[a-zA-Z0-9]+\.[a-z0-9]+)$#', $path, $m)) {
            return Storage::disk('public')->exists("sounds/{$m[1]}");
        }

        return false;
    }
}
