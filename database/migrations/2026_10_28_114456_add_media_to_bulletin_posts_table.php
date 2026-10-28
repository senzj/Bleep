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
        Schema::table('bulletin_posts', function (Blueprint $table) {
            $table->string('media_path', 2048)->nullable()->after('image_url');
            $table->string('media_mime', 128)->nullable()->after('media_path');
            $table->string('media_kind', 16)->nullable()->after('media_mime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bulletin_posts', function (Blueprint $table) {
            $table->dropColumn(['media_path', 'media_mime', 'media_kind']);
        });
    }
};
