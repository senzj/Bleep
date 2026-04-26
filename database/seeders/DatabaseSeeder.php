<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create or update test user
        User::updateOrCreate(
            ['username' => 'testuser'],
            [
                'dname' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
            ]
        );

        // Call other seeders
        $this->call([
            UserSeeder::class,
            BleepSeeder::class,
            CommentSeeder::class,
        ]);
    }
}
