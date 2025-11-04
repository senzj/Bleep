<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class BleepSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a few sample users if they don't exist
        $users = User::count() < 3
            ? collect([
                User::create([
                    'username' => 'Alice Developer',
                    'email' => 'alice@example.com',
                    'password' => bcrypt('password'),
                ]),
                User::create([
                    'username' => 'Bob Builder',
                    'email' => 'bob@example.com',
                    'password' => bcrypt('password'),
                ]),
                User::create([
                    'username' => 'Charlie Coder',
                    'email' => 'charlie@example.com',
                    'password' => bcrypt('password'),
                ]),
            ])
            : User::take(3)->get();

        // Sample bleeps
        $bleeps = [
            'Just discovered Laravel - where has this been all my life? 🚀',
            'Building something cool with Chirper today!',
            'Laravel\'s Eloquent ORM is pure magic ✨',
            'Deployed my first app with Laravel Cloud. So smooth!',
            'Who else is loving Blade components?',
            'Friday deploys with Laravel? No problem! 😎',
        ];

        // Create bleeps for random users
        foreach ($bleeps as $message) {
            $users->random()->bleeps()->create([
                'message' => $message,
                'created_at' => now()->subMinutes(rand(5, 1440)),
            ]);
        }
    }
}
