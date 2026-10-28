<?php

namespace Database\Factories;

use App\Helpers\AnonymousName;
use App\Models\BulletinPost;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<BulletinPost>
 */
class BulletinPostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $createdAt = fake()->dateTimeBetween('-30 days', 'now');
        $size = fake()->randomElement([
            ['width' => 1, 'height' => 1],
            ['width' => 1, 'height' => 2],
            ['width' => 1, 'height' => 3],
            ['width' => 2, 'height' => 1],
            ['width' => 2, 'height' => 2],
            ['width' => 2, 'height' => 3],
        ]);

        return [
            'anonymous_name' => AnonymousName::randomDisplayName(),
            'content' => fake()->paragraph(),
            'link_url' => fake()->optional(0.15)->url(),
            'image_url' => fake()->optional(0.15)->imageUrl(),
            ...$size,
            'paper_color' => fake()->randomElement([
                '#fef08a',
                '#fbcfe8',
                '#bbf7d0',
                '#bfdbfe',
                '#ddd6fe',
                '#fed7aa',
                '#fecaca',
            ]),
            'paper_texture' => fake()->optional(0.75)->randomElement([
                'plain',
                'torn',
                'folded',
                'crumpled',
                'scratched',
                'perforated',
                'scraps',
                'notebook',
                'torn-edges',
                'zigzag',
            ]),
            'visitor_hash' => hash('sha256', fake()->uuid()),
            'created_at' => $createdAt,
            'expires_at' => Carbon::instance($createdAt)->addMonthsNoOverflow(8),
            'archived_at' => null,
        ];
    }

    public function wide(): static
    {
        return $this->state(fn (array $attributes) => [
            'width' => 2,
            'height' => 1,
        ]);
    }

    public function tall(): static
    {
        return $this->state(fn (array $attributes) => [
            'width' => 1,
            'height' => 2,
        ]);
    }

    public function large(): static
    {
        return $this->state(fn (array $attributes) => [
            'width' => 2,
            'height' => 2,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
