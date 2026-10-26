<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPreferences extends Model
{
    protected $fillable = [
        'user_id',
        'nav_layout',
        'theme',

        // Content preferences
        'show_nsfw',
        'blur_nsfw_media',
        'show_reposts_in_feed',
        'show_anonymous_bleeps', // hidden if nt enabled
        'default_feed_sort',
        'bleeps_per_page',

        // notifications
        'recieve_notification_sound',
        'send_notification_sound',
        'notify_likes',
        'notify_comments',
        'notify_replies',
        'notify_comment_likes',
        'notify_reposts',
        'notify_shares',
        'notify_repost_likes',
        'notify_follows',
        'notify_unfollows',
        'notify_profile_visits',
        'notify_report_updates',
        'notify_moderation',
        'notify_account',
        'notify_staff_reports',
        'notify_staff_audit',

        // Privacy settings
        'private_profile',
        'block_new_followers',
        'hide_online_status',
        'hide_activity',
    ];

    protected $casts = [
        'nav_layout' => 'string',
        'theme' => 'string',

        'show_nsfw' => 'boolean',
        'blur_nsfw_media' => 'boolean',
        'show_reposts_in_feed' => 'boolean',
        'show_anonymous_bleeps' => 'boolean',
        'default_feed_sort' => 'string',
        'bleeps_per_page' => 'integer',

        'recieve_notification_sound' => 'string',
        'send_notification_sound' => 'string',

        'notify_likes' => 'boolean',
        'notify_comments' => 'boolean',
        'notify_replies' => 'boolean',
        'notify_comment_likes' => 'boolean',
        'notify_reposts' => 'boolean',
        'notify_shares' => 'boolean',
        'notify_repost_likes' => 'boolean',
        'notify_follows' => 'boolean',
        'notify_unfollows' => 'boolean',
        'notify_profile_visits' => 'boolean',
        'notify_report_updates' => 'boolean',
        'notify_moderation' => 'boolean',
        'notify_account' => 'boolean',
        'notify_staff_reports' => 'boolean',
        'notify_staff_audit' => 'boolean',

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
            // Theme and layout preferences
            'nav_layout' => 'horizontal',
            'theme' => 'system',

            // Content preferences
            'show_nsfw' => false,
            'blur_nsfw_media' => true,
            'show_reposts_in_feed' => true,
            'show_anonymous_bleeps' => true,
            'default_feed_sort' => 'newest',
            'bleeps_per_page' => 15,

            // notifications
            'recieve_notification_sound' => '/sounds/notifications/marimba-bloop-1.mp3',
            'send_notification_sound' => '/sounds/effects/bloop-1.mp3',

            'notify_likes' => true,
            'notify_comments' => true,
            'notify_replies' => true,
            'notify_comment_likes' => false,
            'notify_reposts' => true,
            'notify_shares' => true,
            'notify_repost_likes' => false,
            'notify_follows' => true,
            'notify_unfollows' => false,
            'notify_profile_visits' => false,
            'notify_report_updates' => true,
            'notify_moderation' => true,
            'notify_account' => true,
            'notify_staff_reports' => true,
            'notify_staff_audit' => true,

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
