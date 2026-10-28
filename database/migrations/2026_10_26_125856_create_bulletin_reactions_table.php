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
        Schema::create('bulletin_reactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bulletin_post_id')
                ->constrained()
                ->cascadeOnDelete();

            // Short machine-readable key, for example "heart" or "thumbs_up".
            $table->string('reaction_type', 16);

            // HMAC only. One anonymous visitor picks at most one reaction per post.
            $table->char('visitor_hash', 64);

            $table->timestamps();

            $table->unique(
                ['bulletin_post_id', 'visitor_hash'],
                'bulletin_reactions_unique'
            );
            $table->index('visitor_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bulletin_reactions');
    }
};
