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
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('nav_layout', 20)->default('horizontal');

            // Content preferences
            $table->boolean('show_nsfw')->default(false);
            $table->boolean('blur_nsfw_media')->default(true);

            // only one can be true at once for autoplay, but allow both to be false
            $table->boolean('autoplay_videos')->default(true);
            $table->boolean('autoplay_audio')->default(false);

            $table->boolean('show_reposts_in_feed')->default(true);
            $table->boolean('show_anonymous_bleeps')->default(true);
            $table->string('default_feed_sort', 20)->default('newest');
            $table->integer('bleeps_per_page')->default(15);

            // System preferences
            $table->boolean('desktop_notifications')->default(false);
            $table->string('theme', 50)->default('system');

            // Privacy settings
            $table->boolean('private_profile')->default(false);
            $table->boolean('block_new_followers')->default(false);
            $table->boolean('hide_online_status')->default(false);
            $table->boolean('hide_activity')->default(false);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropColumn([
                'blur_nsfw_media',
                'autoplay_audio',
                'show_reposts_in_feed',
                'show_anonymous_bleeps',
                'default_feed_sort',
                'bleeps_per_page',
                'desktop_notifications',
                'theme',
            ]);
        });
    }
};
