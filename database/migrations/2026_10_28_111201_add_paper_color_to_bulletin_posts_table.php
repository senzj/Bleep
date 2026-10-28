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
            // Optional visitor-picked paper colour in #rrggbb form.
            $table->char('paper_color', 7)->nullable()->after('height');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bulletin_posts', function (Blueprint $table) {
            $table->dropColumn('paper_color');
        });
    }
};
