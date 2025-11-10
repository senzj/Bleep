<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bleep_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bleep_id')->constrained('bleeps')->cascadeOnDelete();
            $table->string('path');                 // storage relative path (public disk)
            $table->enum('type', ['image', 'video']);
            $table->string('original_name')->nullable();
            $table->string('mime_type', 50)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bleep_media');
    }
};
