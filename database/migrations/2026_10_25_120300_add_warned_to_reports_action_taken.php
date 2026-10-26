<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACTIONS = ['none', 'bleep_deleted', 'op_banned', 'reporter_banned', 'warned'];

    private const LEGACY_ACTIONS = ['none', 'bleep_deleted', 'op_banned', 'reporter_banned'];

    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE reports MODIFY action_taken ENUM('none', 'bleep_deleted', 'op_banned', 'reporter_banned', 'warned') NULL"
            );

            return;
        }

        // Laravel's SQLite enum creates a CHECK constraint, so the column has
        // to be rebuilt rather than merely left alone.
        Schema::table('reports', function (Blueprint $table) {
            $table->enum('action_taken', self::ACTIONS)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('reports')
            ->where('action_taken', 'warned')
            ->update(['action_taken' => 'none']);

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE reports MODIFY action_taken ENUM('none', 'bleep_deleted', 'op_banned', 'reporter_banned') NULL"
            );

            return;
        }

        Schema::table('reports', function (Blueprint $table) {
            $table->enum('action_taken', self::LEGACY_ACTIONS)->nullable()->change();
        });
    }
};
