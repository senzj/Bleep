<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bleep>
 */
class BleepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $messages = [
            'Just shipped a new feature! 🚀',
            'Coffee first, code later ☕',
            'Debugging is like being a detective in a crime movie where you are also the murderer.',
            'There are only two hard things in Computer Science: cache invalidation and naming things.',
            'It works on my machine! 🤷‍♂️',
            'Code never lies, comments sometimes do.',
            'Learning Laravel has been a game changer for me!',
            'Who else loves writing clean code? ✨',
            'Just refactored 500 lines into 50. Feels good!',
            'Backend dev by day, bug hunter by night 🦸',
            'That moment when your code works on the first try... suspicious 🤔',
            'Remember: A good developer is a lazy developer.',
            'Git commit -m "fixed stuff"',
            'Why do programmers prefer dark mode? Because light attracts bugs! 🐛',
            'Just discovered a cool Laravel package!',
            'Pair programming = two devs, one keyboard, infinite debates',
            'The best error message is the one that never shows up.',
            'My code is like my joke - only I understand it.',
            'HTML is a programming language. Change my mind. (Just kidding 😄)',
            'CSS is awesome until you need to center a div.',
            'JavaScript: The language where 0.1 + 0.2 !== 0.3',
            'PHP is not dead, it\'s just getting started!',
            'Laravel makes PHP fun again! 💜',
            'Writing tests today so I can sleep better tonight.',
            'Documentation is a love letter that you write to your future self.',
            'Clean code always looks like it was written by someone who cares.',
            'First rule of programming: if it works, don\'t touch it.',
            'Semicolons are optional in JavaScript; anxiety is not.',
            'I don\'t always test my code, but when I do, I do it in production.',
            'Coding is 10% writing code and 90% figuring out why it doesn\'t work.',
        ];

        return [
            'user_id' => User::factory(),
            'message' => fake()->randomElement($messages),
            'media_path' => null,
            'views' => fake()->numberBetween(0, 10000),
            'is_anonymous' => fake()->boolean(20), // 20% chance of being anonymous
            'deleted_by_author' => false,
            'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Indicate that the bleep is anonymous.
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_anonymous' => true,
        ]);
    }

    /**
     * Indicate that the bleep is popular (high views).
     */
    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'views' => fake()->numberBetween(10000, 1000000),
        ]);
    }

    /**
     * Indicate that the bleep was recently created.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => fake()->dateTimeBetween('-1 day', 'now'),
        ]);
    }
}
