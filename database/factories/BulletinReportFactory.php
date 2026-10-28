<?php

namespace Database\Factories;

use App\Enums\BulletinReportReason;
use App\Models\BulletinPost;
use App\Models\BulletinReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BulletinReport>
 */
class BulletinReportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bulletin_post_id' => BulletinPost::factory(),
            'reason' => fake()->randomElement(BulletinReportReason::values()),
            'visitor_hash' => hash('sha256', fake()->uuid()),
        ];
    }
}
