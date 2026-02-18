<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreferences extends Model
{
    protected $fillable = [
        'user_id',
        'nav_layout',

        // Content preferences
        'show_nsfw',
        'blur_nsfw_media',

        // only one can be true at once for autoplay, but allow both to be false
        'autoplay_videos',
        'autoplay_audio',

        'show_reposts_in_feed',
        'show_anonymous_bleeps',
        'default_feed_sort',
        'bleeps_per_page',

        // System preferences
        'desktop_notifications',
        'theme',

        // Privacy settings
        'private_profile',
        'block_new_followers',
        'hide_online_status',
        'hide_activity',
    ];

    protected $casts = [
        'show_nsfw' => 'boolean',
        'blur_nsfw_media' => 'boolean',
        'autoplay_videos' => 'boolean',
        'autoplay_audio' => 'boolean',
        'show_reposts_in_feed' => 'boolean',
        'show_anonymous_bleeps' => 'boolean',
        'bleeps_per_page' => 'integer',
        'desktop_notifications' => 'boolean',
        'private_profile' => 'boolean',
        'block_new_followers' => 'boolean',
        'hide_online_status' => 'boolean',
        'hide_activity' => 'boolean',
    ];

    /**
     * Default values for new preferences
     */
    public static function defaults(): array
    {
        return [
            'nav_layout' => 'horizontal',
            // Content preferences
            'show_nsfw' => false,
            'blur_nsfw_media' => true,
            'autoplay_videos' => true,
            'autoplay_audio' => false,
            'show_reposts_in_feed' => true,
            'show_anonymous_bleeps' => true,
            'default_feed_sort' => 'newest',
            'bleeps_per_page' => 15,
            // System preferences
            'desktop_notifications' => false,
            'theme' => 'system',
            // Privacy settings
            'private_profile' => false,
            'block_new_followers' => false,
            'hide_online_status' => false,
            'hide_activity' => false,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
