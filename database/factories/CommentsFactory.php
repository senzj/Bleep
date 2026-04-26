<?php

namespace Database\Factories;

use App\Models\Bleep;
use App\Models\Comments;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentsFactory extends Factory
{
    protected $model = Comments::class;

    public function definition(): array
    {
        $messages = [
            'This is exactly what I needed to hear today.',
            'Couldn\'t agree more with this.',
            'Thanks for sharing this, really resonates.',
            'I felt this one deeply.',
            'This is so real, no cap.',
            'Been thinking the same thing honestly.',
            'Why does this hit different at 2am?',
            'Okay but this is actually facts.',
            'Not me crying at a bleep again.',
            'The way I relate to this on a personal level.',
            'Bro said what we were all thinking.',
            'This deserves more attention.',
            'I screenshot this for later, not gonna lie.',
            'Say it louder for the people in the back.',
            'This is the most honest thing I\'ve seen today.',
            'Real talk, appreciate you posting this.',
            'I wish more people thought like this.',
            'Adding this to my list of things to remember.',
            'You just described my entire week.',
            'Sending this to everyone I know.',
            'Hard agree. No notes.',
            'This is the energy we need more of.',
            'Facts, no printer.',
            'Why does this make me emotional?',
            'Okay I needed to see this today specifically.',
            'The accuracy of this is actually alarming.',
            'Preach. We need to talk about this more.',
            'This is giving what it\'s supposed to give.',
            'I feel personally attacked but also validated.',
            'Not me nodding along to every word.',
            'You are not alone in this, trust.',
            'This is a reminder I needed today.',
            'We don\'t talk about this enough honestly.',
            'This is genuinely helpful, thank you.',
            'Quiet but powerful. Love this.',
            'The way this is worded is perfect.',
            'Okay but can we frame this somewhere.',
            'Short but it hits hard.',
            'Saving this forever.',
            'My therapist would love this post.',
        ];

        return [
            'user_id'      => User::factory(),
            'bleep_id'     => Bleep::factory(),
            'parent_id'    => null,
            'message'      => fake()->randomElement($messages),
            'media_path'   => null,
            'is_anonymous' => fake()->boolean(10),
            'created_at'   => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function anonymous(): static
    {
        return $this->state(fn () => ['is_anonymous' => true]);
    }

    public function replyTo(Comments $parent): static
    {
        return $this->state(fn () => [
            'bleep_id'  => $parent->bleep_id,
            'parent_id' => $parent->id,
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn () => [
            'created_at' => fake()->dateTimeBetween('-1 day', 'now'),
        ]);
    }
}
