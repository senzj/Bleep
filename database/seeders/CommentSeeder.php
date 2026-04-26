<?php

namespace Database\Seeders;

use App\Models\Bleep;
use App\Models\Comments;
use App\Models\User;
use Illuminate\Database\Seeder;

class CommentSeeder extends Seeder
{
    public function run(): void
    {
        $users  = User::all();
        $bleeps = Bleep::all();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');
            return;
        }

        if ($bleeps->isEmpty()) {
            $this->command->warn('No bleeps found. Please run BleepSeeder first.');
            return;
        }

        $totalComments = 0;
        $totalReplies  = 0;

        // Add comments to a random subset of bleeps
        $bleeps->random(min(80, $bleeps->count()))->each(function (Bleep $bleep) use ($users, &$totalComments, &$totalReplies) {

            // 1–8 top-level comments per bleep
            $commentCount = fake()->numberBetween(1, 8);

            $topLevel = Comments::factory()
                ->count($commentCount)
                ->recycle($users)
                ->create([
                    'bleep_id'  => $bleep->id,
                    'parent_id' => null,
                ]);

            $totalComments += $commentCount;

            // Some top-level comments get replies (1–4 replies each)
            $topLevel->random(min(fake()->numberBetween(0, $commentCount), $topLevel->count()))
                ->each(function (Comments $comment) use ($users, &$totalReplies) {
                    $replyCount = fake()->numberBetween(1, 4);

                    Comments::factory()
                        ->count($replyCount)
                        ->replyTo($comment)
                        ->recycle($users)
                        ->create();

                    $totalReplies += $replyCount;
                });
        });

        $this->command->info("Created {$totalComments} comments and {$totalReplies} replies across {$bleeps->count()} bleeps.");
    }
}
