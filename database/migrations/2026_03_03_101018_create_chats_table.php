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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(); // null = DM
            $table->boolean('is_group')->default(false);
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('chat_theme')->default('default');
            $table->string('chat_bg')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index('last_message_at');
        });

        Schema::create('conversation_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->enum('role', ['admin', 'member'])->default('member');

            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('joined_at')->useCurrent();

            $table->boolean('is_muted')->default(false);
            $table->timestamp('muted_until')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->timestamp('snoozed_until')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'is_archived']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('sender_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->text('body')->nullable();
            $table->string('media_path')->nullable();
            $table->string('media_type')->nullable();

            $table->foreignId('reply_to_id')
                ->nullable()
                ->constrained('messages')
                ->nullOnDelete();

            $table->boolean('is_edited')->default(false);

            $table->softDeletes();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->unique(['conversation_id', 'sender_id']);
        });

        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('message_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->string('emoji');

            $table->timestamps();

            $table->unique(['message_id', 'user_id', 'emoji']);
        });

        Schema::create('message_mentions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('message_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            $table->timestamps();

            $table->unique(['message_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_mentions');
        Schema::dropIfExists('message_reactions');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_user');
        Schema::dropIfExists('conversations');
    }
};
