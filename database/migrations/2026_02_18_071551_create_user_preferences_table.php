<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Navigation preference: 'horizontal' (default) or 'vertical'
            $table->string('nav_layout', 20)->default('horizontal');
            $table->string('theme', 50)->default('system');

            // Content preferences
            $table->boolean('show_nsfw')->default(false);
            $table->boolean('blur_nsfw_media')->default(true);  // Blur NSFW until clicked
            $table->boolean('show_reposts_in_feed')->default(true);
            $table->boolean('show_anonymous_bleeps')->default(true);
            $table->boolean('show_anonymous_comments')->default(true);
            $table->enum('default_feed_sort', ['newest', 'popular', 'following'])->default('newest');
            $table->integer('bleeps_per_page')->default(15);

            // Notification and sound preferences
            $table->string('recieve_notification_sound')->default('/sounds/notifications/marimba-bloop-1.mp3');
            $table->string('send_notification_sound')->default('/sounds/effects/bloop-1.mp3');

            // Per-category notification toggles. Critical account notices (bans,
            // warnings, content removal) always arrive regardless of these.
            $table->boolean('notify_likes')->default(true);
            $table->boolean('notify_comments')->default(true);
            $table->boolean('notify_replies')->default(true);
            $table->boolean('notify_comment_likes')->default(false);
            $table->boolean('notify_reposts')->default(true);
            $table->boolean('notify_shares')->default(true);
            $table->boolean('notify_repost_likes')->default(false);
            $table->boolean('notify_follows')->default(true);
            $table->boolean('notify_unfollows')->default(false);
            $table->boolean('notify_profile_visits')->default(false);
            $table->boolean('notify_report_updates')->default(true);
            $table->boolean('notify_moderation')->default(true);
            $table->boolean('notify_account')->default(true);
            $table->boolean('notify_staff_reports')->default(true);
            $table->boolean('notify_staff_audit')->default(true);

            // Privacy settings
            $table->boolean('private_profile')->default(false);
            $table->boolean('block_new_followers')->default(false);
            $table->boolean('hide_online_status')->default(false);
            $table->boolean('hide_activity')->default(false);
            $table->string('follower_list_visibility', 16)->default('everyone');
            $table->string('dm_privacy', 16)->default('everyone');

            // Media playback preferences
            $table->string('media_offscreen', 20)->default('pause');
            $table->boolean('media_downloads')->default(false);
            $table->boolean('resume_media_on_view')->default(false);
            $table->boolean('remember_media_position')->default(true);

            $table->boolean('autoplay_media')->default(false);

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
