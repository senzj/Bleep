<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_visits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('profile_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('visitor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->date('visited_on');

            $table->timestamps();

            // One row per viewer per profile per day. The short explicit name
            // fits inside MySQL's 64-character index limit.
            $table->unique(
                ['profile_user_id', 'visitor_id', 'visited_on'],
                'profile_visits_daily_unique'
            );

            $table->index(['profile_user_id', 'visited_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_visits');
    }
};
