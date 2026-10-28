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
            // Optional visitor-picked paper design; null falls back to a stable
            // per-note texture derived from the public id.
            $table->string('paper_texture', 20)->nullable()->after('paper_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bulletin_posts', function (Blueprint $table) {
            $table->dropColumn('paper_texture');
        });
    }
};
