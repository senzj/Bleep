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
        Schema::create('bleep_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bleep_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('session_id')->nullable(); // For guests
            $table->timestamp('viewed_at');

            // Prevent duplicate views
            $table->unique(['bleep_id', 'user_id']);
            $table->unique(['bleep_id', 'session_id']);
            $table->index(['bleep_id', 'viewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bleep_views');
    }
};
