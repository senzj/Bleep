<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable()->after('timezone');
            $table->timestamp('privacy_accepted_at')->nullable()->after('terms_accepted_at');
            $table->timestamp('cookies_accepted_at')->nullable()->after('privacy_accepted_at');
            $table->timestamp('age_confirmed_at')->nullable()->after('cookies_accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'terms_accepted_at',
                'privacy_accepted_at',
                'cookies_accepted_at',
                'age_confirmed_at',
            ]);
        });
    }
};
