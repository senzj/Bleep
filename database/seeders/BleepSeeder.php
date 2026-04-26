<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Bleep;
use Illuminate\Database\Seeder;

class BleepSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all users
        $users = User::all();

        // Get all bleeps
        $bleeps = Bleep::all();

        // If there are no users, we cannot create bleeps, so we should warn the user and exit
        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');
            return;
        }

        // Create 100 random bleeps distributed among users
        Bleep::factory()
            ->count(100)
            ->recycle($users) // Recycle existing users instead of creating new ones
            ->create();

        // Create some popular bleeps
        Bleep::factory()
            ->count(10)
            ->popular()
            ->recycle($users)
            ->create();

        // Create some recent bleeps
        Bleep::factory()
            ->count(20)
            ->recent()
            ->recycle($users)
            ->create();

        // Create some NSFW bleeps
        Bleep::factory()
            ->count(10)
            ->nsfw()
            ->recycle($users)
            ->create();

        // Create some anonymous bleeps
        if (config('app.anonymity') === true) {
            Bleep::factory()
                ->count(15)
                ->anonymous()
                ->recycle($users)
                ->create();
        }

        /**
         * Create bleep comments
         * by randomly selecting some bleeps and adding comments to them
         * with random comments counts on each bleep
         * and also randomly selecting users to comment on those bleeps
         */

        // Check if bleep exists before trying to create comments for it
        if ($bleeps->isEmpty()) {
            $this->command->warn('No bleeps found. Please run BleepSeeder first.');
            return;
        }

        $this->call(CommentSeeder::class);

        $this->command->info('Created ' . Bleep::count() . ' bleeps for ' . $users->count() . ' users.');
    }
}
