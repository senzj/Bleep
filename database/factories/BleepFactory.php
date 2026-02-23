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
            'The only thing worse than a bug in your code is a bug in your code that you can\'t reproduce.',
            'I have a love-hate relationship with regex.',
            'The best code is no code at all.',
            'Sometimes I wonder if my code is more efficient than my coffee consumption. ☕',
            'Programming is like writing a book... except if you miss a single comma on page 126 the whole thing makes no sense.',
            'In life If you can\'t beat the fear, just do it scared. - Sheryl Sandberg',
            'In three words I can sum up everything I\'ve learned about life: it goes on. - Robert Frost',
            'Life is like riding a bicycle. To keep your balance, you must keep moving. - Albert Einstein',
            'The only way to do great work is to love what you do. - Steve Jobs',
            'Success is not final, failure is not fatal: It is the courage to continue that counts. - Winston Churchill',
            'The secret of getting ahead is getting started. - Mark Twain',
            'The best time to plant a tree was 20 years ago. The second best time is now. - Chinese Proverb',
            'The secret is not to give up on your dreams, but to find a way to make them work. - Anonymous',
            'Don\'t watch the clock; do what it does. Keep going. - Sam Levenson',
            'The future belongs to those who believe in the beauty of their dreams. - Eleanor Roosevelt',
            'The secret is not to find the meaning of life, but to use your life to make things that are meaningful. - James Clear',
            'The only limit to our realization of tomorrow will be our doubts of today. - Franklin D. Roosevelt',
            'When I let go of what I am, I become what I might be. - Lao Tzu',
            'Let your mind be free to wander, but your heart should always be anchored in what matters most. - Anonymous',
            'The only limits in your life are those that you set yourself. - Celestine Chua',
            'You are confined only by the walls you build yourself. - Andrew Murphy',
            'Don\'t limit your challenges. Challenge your limits. - Anonymous',
            'The mind is the limit.  As long as the mind can envision the fact that you can do something, you can do it, as long as you really believe 100 percent. - Arnold Schwarzenegger',
            'Once we accept our limits, we go beyond them. - Albert Einstein',
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
