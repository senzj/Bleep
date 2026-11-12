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
        Schema::table('bleep_media', function (Blueprint $table) {
            $table->unsignedInteger('duration_seconds')
                ->nullable()
                ->after('size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bleep_media', function (Blueprint $table) {
            $table->dropColumn('duration_seconds');
        });
    }
};
