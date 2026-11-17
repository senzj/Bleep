<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Failed sign-ins happen before there is an account to attribute them to,
     * so the row has to survive without a user id. The cascade on the account
     * is kept for the rows that do belong to a member.
     */
    public function up(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
