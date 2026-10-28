<?php

namespace App\Console\Commands;

use App\Services\Bulletin\BulletinBoardService;
use Illuminate\Console\Command;

class BulletinPrune extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bulletin:prune {--keep-archived=30 : Days to keep archived notes before deleting them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archive expired bulletin notes and delete old archived ones';

    /**
     * Execute the console command.
     */
    public function handle(BulletinBoardService $board): int
    {
        $result = $board->prune((int) $this->option('keep-archived'));

        $this->info("Archived {$result['archived']} expired notes and deleted {$result['deleted']} old notes.");

        return Command::SUCCESS;
    }
}
