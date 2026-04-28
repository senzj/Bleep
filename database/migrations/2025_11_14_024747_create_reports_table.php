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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            // Polymorphic target (bleep OR comment)
            $table->morphs('reportable'); // reportable_id, reportable_type

            // Who reported
            $table->foreignId('reporter_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->text('reason')->nullable();

            // Status tracking
            $table->enum('status', ['pending', 'dismissed', 'resolved'])
                ->default('pending');

            $table->enum('category', ['spam', 'harassment', 'hate', 'nsfw', 'illegal', 'other'])
                ->default('other');

            $table->enum('action_taken', ['none', 'bleep_deleted', 'op_banned', 'reporter_banned'])
                ->nullable();

            $table->timestamp('reviewed_at')->nullable();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Index for moderation queries
            $table->index(['status', 'created_at']);

            // Core rule enforcement (THIS replaces your old uniques)
            $table->unique(['reportable_id', 'reportable_type', 'reporter_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
