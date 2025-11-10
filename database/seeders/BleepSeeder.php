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

        // Create some anonymous bleeps
        Bleep::factory()
            ->count(15)
            ->anonymous()
            ->recycle($users)
            ->create();

        $this->command->info('Created ' . Bleep::count() . ' bleeps for ' . $users->count() . ' users.');
    }
}
