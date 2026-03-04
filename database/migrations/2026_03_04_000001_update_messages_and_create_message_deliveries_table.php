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
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'conversation_id') && Schema::hasColumn('messages', 'sender_id')) {
                $table->dropUnique('messages_conversation_id_sender_id_unique');
            }

            $table->string('media_kind')->default('none')->after('media_type');
            $table->string('client_uuid', 64)->nullable()->after('is_edited');
            $table->index(['conversation_id', 'client_uuid']);
        });

        Schema::create('message_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_deliveries');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_conversation_id_client_uuid_index');
            $table->dropColumn(['media_kind', 'client_uuid']);

            $table->unique(['conversation_id', 'sender_id']);
        });
    }
};
