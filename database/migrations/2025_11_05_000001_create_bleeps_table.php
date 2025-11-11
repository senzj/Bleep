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
        Schema::create('bleeps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->string('media_path')->nullable();
            $table->integer('views')->default(0);
            $table->boolean('is_anonymous')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->boolean('deleted_by_author')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bleeps');
    }
};
