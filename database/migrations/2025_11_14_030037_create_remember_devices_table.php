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
        Schema::create('remembered_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('token', 64)->unique(); // store sha256 hashes (64 chars)
            $table->ipAddress('ip')->nullable();
            $table->text('user_agent')->nullable();

            $table->string('parsed_os', 64)->nullable()->index();
            $table->string('parsed_browser', 64)->nullable()->index();
            $table->string('parsed_device_type', 64)->nullable()->index();

            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_used_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('remembered_devices');
    }
};
