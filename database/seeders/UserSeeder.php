<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 20 random users
        User::factory()->count(20)->create();

        // Create some specific users for testing (use updateOrCreate to avoid duplicates)
        User::updateOrCreate(
            ['username' => 'alicedev'],
            [
                'dname' => 'Alice Developer',
                'email' => 'alice@example.com',
                'bio' => 'Full-stack developer passionate about Laravel and Vue.js',
                'password' => bcrypt('password'),
            ]
        );

        User::updateOrCreate(
            ['username' => 'bobbuilder'],
            [
                'dname' => 'Bob Builder',
                'email' => 'bob@example.com',
                'bio' => 'Backend engineer | Laravel enthusiast | Coffee addict ☕',
                'password' => bcrypt('password'),
            ]
        );

        User::updateOrCreate(
            ['username' => 'charliecodes'],
            [
                'dname' => 'Charlie Coder',
                'email' => 'charlie@example.com',
                'bio' => 'Building cool stuff with code 🚀',
                'password' => bcrypt('password'),
            ]
        );
    }
}
