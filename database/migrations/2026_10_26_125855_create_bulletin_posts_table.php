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
        Schema::create('bulletin_posts', function (Blueprint $table) {
            $table->id();

            // Public identifier used in URLs instead of the numeric primary key.
            $table->char('public_id', 26)->unique();

            $table->string('anonymous_name', 64);
            $table->text('content');
            $table->string('link_url', 2048)->nullable();
            $table->string('image_url', 2048)->nullable();

            // Relative board size in grid cells: 1x1, 1x2, 2x1, or 2x2.
            $table->unsignedTinyInteger('width')->default(1);
            $table->unsignedTinyInteger('height')->default(1);

            // HMAC only. The raw visitor identity and IP are never stored here.
            $table->char('visitor_hash', 64);

            $table->timestamp('expires_at');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index('expires_at');
            $table->index(['archived_at', 'expires_at']);
            $table->index('visitor_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulletin_posts');
    }
};
