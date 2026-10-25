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
        if (Schema::hasColumn('user_preferences', 'hide_activity')) {
            return;
        }

        Schema::table('user_preferences', function (Blueprint $table) {
            $table->boolean('hide_activity')->default(false)->after('hide_online_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('user_preferences', 'hide_activity')) {
            return;
        }

        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropColumn('hide_activity');
        });
    }
};
