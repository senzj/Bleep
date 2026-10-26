<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Notification categories a user can mute. Critical account notices
     * (bans, warnings, content removal) are intentionally not listed here.
     *
     * @var array<string, bool>
     */
    private array $preferences = [
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
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            foreach ($this->preferences as $column => $default) {
                if (Schema::hasColumn('user_preferences', $column)) {
                    continue;
                }

                $table->boolean($column)->default($default);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            foreach (array_keys($this->preferences) as $column) {
                if (Schema::hasColumn('user_preferences', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
