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
            'Woke up tired again but still pushing. One day at a time.',
            'Anyone else feel like life is moving too fast lately?',
            'I miss when weekends felt longer than two hours.',
            'Trying to stay positive, but today tested my patience.',
            'Reminder to self: rest is productive too.',
            'Some days you are motivated, some days you survive. Both count.',
            'Not every thought needs to become anxiety. Breathe.',
            'I am learning to stop apologizing for taking up space.',
            'Small win: I finally cleaned my room and my mind feels lighter.',
            'Drank water, answered emails, did laundry. Adulting complete.',
            'Why is customer support always "we value your time" and then 45 minutes on hold?',
            'My internet only fails when I am in an important meeting.',
            'Public transport was late again. We deserve better.',
            'Rent keeps going up, salaries stay still. Make it make sense.',
            'Phone battery at 12 percent is a personality test.',
            'People are tired, not lazy. The system is exhausting.',
            'Freedom of speech should include room for disagreement without hate.',
            'You do not have to agree with me, but you should let me speak.',
            'Healthy debate is better than silent resentment.',
            'If your opinion needs insults to survive, it is probably weak.',
            'Respect is not censorship. It is basic maturity.',
            'My timeline feels like shouting. I miss actual conversations.',
            'Quote of the day: "Discipline is choosing what you want most over what you want now."',
            'Quote I needed today: "You cannot heal in the same environment that hurt you."',
            '"Courage is not loud. Sometimes it is just getting up again."',
            '"Do it scared" has carried me further than confidence ever did.',
            '"Be kind, but do not be easy to misuse."',
            'Morning update: coffee made, playlist on, trying again.',
            'Just finished a workout after skipping all week. Progress is messy.',
            'Life update: I am learning boundaries and sleeping better because of it.',
            'I said no without guilt today. Growth.',
            'Late-night thought: maybe I am not behind, maybe I am on my own timeline.',
            'If you are reading this, drink water and unclench your jaw.',
            'News check: heavy rain expected tonight, please drive safe everyone.',
            'Local update: power is back in our area after 3 hours.',
            'Community alert: missing dog near Riverside, brown collar, name is Milo.',
            'Market prices are getting wild. How are families supposed to cope?',
            'Road near Central Avenue is blocked due to construction. Take the side streets.',
            'Who else is tired of fake headlines made just for clicks?',
            'Gaming night went from one match to six hours. No regrets.',
            'I swear ranked mode is 20 percent skill and 80 percent mental strength.',
            'Patch notes dropped and they nerfed my main again.',
            'Looking for chill teammates, no toxicity, just good vibes.',
            'Single-player games are therapy and nobody can convince me otherwise.',
            'Today I lost 7 in a row and still queued again. Character development.',
            'Big respect to streamers who stay positive during chaos.',
            'Dev update: fixed one bug, discovered three more. Classic.',
            'Shipped a small feature today and I am weirdly proud of it.',
            'Anyone else spend 2 hours fixing a bug caused by one typo?',
            'Testing in production is not a strategy, it is a cry for help.',
            'Code review should fix code, not crush confidence.',
            'Just learned a shortcut that saved me 30 minutes every day.',
            'Community question: what local businesses should we support this month?',
            'If you are promoting your work, drop it below. Let us boost each other.',
            'Need recommendations for affordable therapists in town.',
            'Can someone suggest beginner-friendly gyms with no judgment?',
            'Anyone hosting a clean-up drive this weekend? I want to join.',
            'Open thread: what is one thing you are grateful for today?',
            'Checking in on everyone dealing with burnout. You are not alone.',
            'Hot take: being soft-hearted is a strength, not a weakness.',
            'I do not need everyone to understand me. Peace is enough.',
            'This app feels better when people are honest, not performative.',
            'If your week has been rough, I hope tomorrow is gentler.',
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

    /**
     * Indicate that the bleep is NSFW.
     */
    public function nsfw(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_nsfw' => true,
        ]);
    }
}
