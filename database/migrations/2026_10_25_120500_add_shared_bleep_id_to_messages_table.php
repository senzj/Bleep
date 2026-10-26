<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('shared_bleep_id')
                ->nullable()
                ->after('reply_to_id')
                ->constrained('bleeps')
                ->nullOnDelete();

            $table->index('shared_bleep_id');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['shared_bleep_id']);
            $table->dropIndex(['shared_bleep_id']);
            $table->dropColumn('shared_bleep_id');
        });
    }
};
