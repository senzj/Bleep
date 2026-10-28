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
        Schema::create('bulletin_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bulletin_post_id')
                ->constrained()
                ->cascadeOnDelete();

            // Short machine-readable reason key, for example "spam".
            $table->string('reason', 24);

            // HMAC only. One anonymous visitor can report a note once.
            $table->char('visitor_hash', 64);

            $table->timestamps();

            $table->unique(
                ['bulletin_post_id', 'visitor_hash'],
                'bulletin_reports_unique'
            );
            $table->index('visitor_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulletin_reports');
    }
};
