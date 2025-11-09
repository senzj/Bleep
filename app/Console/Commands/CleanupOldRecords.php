<?php

namespace App\Console\Commands;

use App\Models\Bleep;
use App\Models\Share;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOldRecords extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cleanup:old-records {--days=30 : Number of days to keep records}';

    /**
     * The console command description.
     */
    protected $description = 'Cleanup old soft-deleted bleeps and shares older than specified days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up records older than {$days} days (before {$cutoffDate->toDateString()})...");

        // Force delete soft-deleted bleeps older than cutoff date
        $deletedBleeps = Bleep::onlyTrashed()
            ->where('deleted_at', '<', $cutoffDate)
            ->count();

        if ($deletedBleeps > 0) {
            Bleep::onlyTrashed()
                ->where('deleted_at', '<', $cutoffDate)
                ->forceDelete();

            $this->info("✓ Force deleted {$deletedBleeps} old soft-deleted bleeps");
        } else {
            $this->info("✓ No old soft-deleted bleeps to clean up");
        }

        // Delete shares for force-deleted bleeps (orphaned shares)
        $orphanedShares = Share::whereDoesntHave('bleep')->count();

        if ($orphanedShares > 0) {
            Share::whereDoesntHave('bleep')->delete();
            $this->info("✓ Deleted {$orphanedShares} orphaned shares");
        } else {
            $this->info("✓ No orphaned shares to clean up");
        }

        // Delete old shares (optional - for shares pointing to deleted bleeps)
        $oldShares = Share::whereHas('bleep', function($query) use ($cutoffDate) {
            $query->onlyTrashed()->where('deleted_at', '<', $cutoffDate);
        })->count();

        if ($oldShares > 0) {
            Share::whereHas('bleep', function($query) use ($cutoffDate) {
                $query->onlyTrashed()->where('deleted_at', '<', $cutoffDate);
            })->delete();

            $this->info("✓ Deleted {$oldShares} old shares");
        } else {
            $this->info("✓ No old shares to clean up");
        }

        $this->newLine();
        $this->info('Cleanup completed successfully!');

        return Command::SUCCESS;
    }
}
