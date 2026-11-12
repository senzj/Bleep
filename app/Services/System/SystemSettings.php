<?php

namespace App\Services\System;

use App\Models\SystemSetting;
use App\Models\UserPreferences;
use Illuminate\Support\Facades\Config;

class SystemSettings
{
    /**
     * Every editable setting, its persisted type, and how it maps onto the
     * application configuration or a user's personal preferences.
     *
     * @return array<string, array{
     *     label: string,
     *     description: string,
     *     group: string,
     *     type: string,
     *     default: mixed,
     *     env: string|null,
     *     config: string|null,
     *     preference: string|null,
     *     options: array<string, string>|null,
     *     min: int|null,
     *     max: int|null
     * }>
     */
    public static function definitions(): array
    {
        return [
            'registration_enabled' => [
                'label' => 'Registration open',
                'description' => 'Allow new visitors to create an account. Closing registration hides the sign-up page.',
                'group' => 'Access',
                'type' => 'boolean',
                'default' => (bool) config('app.registration_enabled', true),
                'env' => 'REGISTRATION_ENABLED',
                'config' => 'app.registration_enabled',
                'preference' => null,
                'options' => null,
                'min' => null,
                'max' => null,
            ],

            'anonymous_posting_enabled' => [
                'label' => 'Anonymous posting',
                'description' => 'Allow signed-in users to hide their identity when posting bleeps and comments.',
                'group' => 'Access',
                'type' => 'boolean',
                'default' => (bool) config('app.anonymity', true),
                'env' => 'ANONYMITY',
                'config' => 'app.anonymity',
                'preference' => null,
                'options' => null,
                'min' => null,
                'max' => null,
            ],

            'maintenance_mode_enabled' => [
                'label' => 'Maintenance mode',
                'description' => 'Show the maintenance page to visitors. Admins and moderators can still sign in.',
                'group' => 'Maintenance',
                'type' => 'boolean',
                'default' => (bool) config('app.maintenance_mode.enabled', false),
                'env' => 'MAINTENANCE_MODE',
                'config' => 'app.maintenance_mode.enabled',
                'preference' => null,
                'options' => null,
                'min' => null,
                'max' => null,
            ],

            'maintenance_duration' => [
                'label' => 'Maintenance duration',
                'description' => 'A number of minutes, or a date/time string, shown on the maintenance page.',
                'group' => 'Maintenance',
                'type' => 'string',
                'default' => config('app.maintenance_mode.duration'),
                'env' => 'MAINTENANCE_DURATION',
                'config' => 'app.maintenance_mode.duration',
                'preference' => null,
                'options' => null,
                'min' => null,
                'max' => null,
            ],

            'max_bleep_file_kb' => [
                'label' => 'Maximum bleep upload (KB)',
                'description' => 'Largest image, video, or audio file accepted for a bleep or comment.',
                'group' => 'Uploads',
                'type' => 'integer',
                'default' => (int) config('media.max_bleep_file_kb', 2 * 1024 * 1024),
                'env' => 'MEDIA_MAX_BLEEP_FILE_KB',
                'config' => 'media.max_bleep_file_kb',
                'preference' => null,
                'options' => null,
                'min' => 1024,
                'max' => 2 * 1024 * 1024,
            ],

            'max_chat_file_kb' => [
                'label' => 'Maximum chat upload (KB)',
                'description' => 'Largest image, video, or audio file accepted in direct messages.',
                'group' => 'Uploads',
                'type' => 'integer',
                'default' => (int) config('media.max_chat_file_kb', 2 * 1024 * 1024),
                'env' => 'MEDIA_MAX_CHAT_FILE_KB',
                'config' => 'media.max_chat_file_kb',
                'preference' => null,
                'options' => null,
                'min' => 1024,
                'max' => 2 * 1024 * 1024,
            ],

            'chunk_size_kb' => [
                'label' => 'Upload chunk size (KB)',
                'description' => 'Size of each resumable upload chunk. Larger chunks need fewer requests.',
                'group' => 'Uploads',
                'type' => 'integer',
                'default' => (int) config('media.chunk_size_kb', 8 * 1024),
                'env' => 'MEDIA_CHUNK_SIZE_KB',
                'config' => 'media.chunk_size_kb',
                'preference' => null,
                'options' => null,
                'min' => 256,
                'max' => 64 * 1024,
            ],

            'chunked_upload_threshold_kb' => [
                'label' => 'Resumable upload threshold (KB)',
                'description' => 'Files at or above this size use the resumable chunked uploader.',
                'group' => 'Uploads',
                'type' => 'integer',
                'default' => (int) config('media.chunked_upload_threshold_kb', 8 * 1024),
                'env' => 'MEDIA_CHUNKED_UPLOAD_THRESHOLD_KB',
                'config' => 'media.chunked_upload_threshold_kb',
                'preference' => null,
                'options' => null,
                'min' => 1024,
                'max' => 2 * 1024 * 1024,
            ],

            'default_theme' => [
                'label' => 'Default theme',
                'description' => 'Theme applied to new accounts and any account without a preference.',
                'group' => 'Personal defaults',
                'type' => 'select',
                'default' => 'system',
                'env' => null,
                'config' => null,
                'preference' => 'theme',
                'options' => [
                    'system' => 'Follow system',
                    'light' => 'Light',
                    'dark' => 'Dark',
                    'acid' => 'Acid',
                    'aqua' => 'Aqua',
                    'autumn' => 'Autumn',
                    'black' => 'Black',
                    'bumblebee' => 'Bumblebee',
                    'business' => 'Business',
                    'cmyk' => 'CMYK',
                    'coffee' => 'Coffee',
                    'corporate' => 'Corporate',
                    'cupcake' => 'Cupcake',
                    'cyberpunk' => 'Cyberpunk',
                    'dim' => 'Dim',
                    'dracula' => 'Dracula',
                    'emerald' => 'Emerald',
                    'fantasy' => 'Fantasy',
                    'forest' => 'Forest',
                    'garden' => 'Garden',
                    'halloween' => 'Halloween',
                    'lemonade' => 'Lemonade',
                    'lofi' => 'Lofi',
                    'luxury' => 'Luxury',
                    'night' => 'Night',
                    'nord' => 'Nord',
                    'pastel' => 'Pastel',
                    'retro' => 'Retro',
                    'sunset' => 'Sunset',
                    'synthwave' => 'Synthwave',
                    'valentine' => 'Valentine',
                    'wireframe' => 'Wireframe',
                    'winter' => 'Winter',
                ],
                'min' => null,
                'max' => null,
            ],

            'default_nav_layout' => [
                'label' => 'Default navigation',
                'description' => 'Navigation layout applied to new accounts.',
                'group' => 'Personal defaults',
                'type' => 'select',
                'default' => 'horizontal',
                'env' => null,
                'config' => null,
                'preference' => 'nav_layout',
                'options' => ['horizontal' => 'Horizontal', 'vertical' => 'Vertical'],
                'min' => null,
                'max' => null,
            ],

            'default_feed_sort' => [
                'label' => 'Default feed sort',
                'description' => 'How new accounts see the home feed until they change it.',
                'group' => 'Personal defaults',
                'type' => 'select',
                'default' => 'newest',
                'env' => null,
                'config' => null,
                'preference' => 'default_feed_sort',
                'options' => ['newest' => 'Newest', 'popular' => 'Popular', 'following' => 'Following'],
                'min' => null,
                'max' => null,
            ],

            'default_bleeps_per_page' => [
                'label' => 'Bleeps per page',
                'description' => 'Default number of bleeps loaded per page for new accounts.',
                'group' => 'Personal defaults',
                'type' => 'integer',
                'default' => 15,
                'env' => null,
                'config' => null,
                'preference' => 'bleeps_per_page',
                'options' => null,
                'min' => 10,
                'max' => 50,
            ],

            'default_media_downloads' => [
                'label' => 'Allow media downloads',
                'description' => 'Default download button visibility for new accounts.',
                'group' => 'Personal defaults',
                'type' => 'boolean',
                'default' => false,
                'env' => null,
                'config' => null,
                'preference' => 'media_downloads',
                'options' => null,
                'min' => null,
                'max' => null,
            ],

            'default_blur_nsfw_media' => [
                'label' => 'Blur NSFW media',
                'description' => 'Default NSFW blur behavior for new accounts.',
                'group' => 'Personal defaults',
                'type' => 'boolean',
                'default' => true,
                'env' => null,
                'config' => null,
                'preference' => 'blur_nsfw_media',
                'options' => null,
                'min' => null,
                'max' => null,
            ],

            'default_remember_media_position' => [
                'label' => 'Remember media position',
                'description' => 'Resume audio and video where the viewer left off by default.',
                'group' => 'Personal defaults',
                'type' => 'boolean',
                'default' => true,
                'env' => null,
                'config' => null,
                'preference' => 'remember_media_position',
                'options' => null,
                'min' => null,
                'max' => null,
            ],
        ];
    }

    /**
     * Current values, merging database overrides over environment defaults.
     *
     * @return array<string, mixed>
     */
    public static function values(): array
    {
        $definitions = static::definitions();
        $stored = SystemSetting::allCached();
        $values = [];

        foreach ($definitions as $key => $definition) {
            $values[$key] = array_key_exists($key, $stored)
                ? static::cast($definition, $stored[$key])
                : $definition['default'];
        }

        return $values;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $definition = static::definitions()[$key] ?? null;

        if ($definition === null) {
            return $default;
        }

        $stored = SystemSetting::allCached();

        return array_key_exists($key, $stored)
            ? static::cast($definition, $stored[$key])
            : $definition['default'];
    }

    /**
     * Persist only known settings and refresh configuration immediately.
     *
     * @param  array<string, mixed>  $values
     */
    public static function update(array $values): void
    {
        $definitions = static::definitions();
        $prepared = [];

        foreach ($values as $key => $value) {
            if (! isset($definitions[$key])) {
                continue;
            }

            $prepared[$key] = static::cast($definitions[$key], $value);
        }

        if ($prepared === []) {
            return;
        }

        SystemSetting::putMany($prepared);
        static::applyToConfig();
    }

    /**
     * Push database-backed settings into the framework configuration.
     */
    public static function applyToConfig(): void
    {
        $config = [];

        foreach (static::definitions() as $key => $definition) {
            if (! $definition['config']) {
                continue;
            }

            $config[$definition['config']] = static::get($key);
        }

        if ($config !== []) {
            Config::set($config);
        }
    }

    /**
     * Personal preference values used when a user has not chosen their own.
     *
     * @return array<string, mixed>
     */
    public static function userPreferenceDefaults(): array
    {
        $defaults = [];

        foreach (static::definitions() as $key => $definition) {
            if (! $definition['preference']) {
                continue;
            }

            $defaults[$definition['preference']] = static::get($key);
        }

        return $defaults;
    }

    /**
     * Overwrite existing users' personal preferences with the current defaults.
     */
    public static function applyDefaultsToExistingUsers(): int
    {
        $values = static::userPreferenceDefaults();

        if ($values === []) {
            return 0;
        }

        return UserPreferences::query()->update($values);
    }

    private static function cast(array $definition, mixed $value): mixed
    {
        return match ($definition['type']) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
            'integer' => (int) $value,
            'select' => is_string($value) ? $value : (string) $value,
            default => $value === null ? null : (string) $value,
        };
    }
}
