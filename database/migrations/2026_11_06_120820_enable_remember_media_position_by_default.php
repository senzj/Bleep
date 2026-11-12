<?php

use App\Models\UserPreferences;
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
            $table->boolean('remember_media_position')
                ->default(true)
                ->after('resume_media_on_view')
                ->change();
        });

        // Resume-where-you-left-off is now the default behaviour. Existing
        // preference rows were created before that default and stored `false`.
        UserPreferences::query()->update(['remember_media_position' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->boolean('remember_media_position')
                ->default(false)
                ->after('resume_media_on_view')
                ->change();
        });
    }
};
