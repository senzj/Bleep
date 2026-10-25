<?php

use App\Models\Bleep;
use App\Models\Following;
use App\Models\User;
use App\Models\UserPreferences;
use Illuminate\Support\Facades\DB;

it('shows public bleeps on the home feed', function () {
    Bleep::factory()->create([
        'message' => 'Feed smoke test bleep',
        'is_anonymous' => false,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Feed smoke test bleep');
});

it('does not show bleeps from private profiles to guests', function () {
    $privateUser = User::factory()->create();
    $privateUser->preferences()->create([
        ...UserPreferences::defaults(),
        'private_profile' => true,
    ]);

    Bleep::factory()->for($privateUser)->create([
        'message' => 'Private profile bleep',
        'is_anonymous' => false,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('Private profile bleep');
});

it('returns an empty following feed when the viewer follows nobody', function () {
    Bleep::factory()->count(2)->create(['is_anonymous' => false]);

    // Guests have no following graph at all, so the scoped tab must stay empty
    // instead of leaking the global feed.
    $guestResponse = $this->getJson('/bleeps/lazy-load?page=1&tab=following')
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(trim($guestResponse->json('html')))->toBe('');

    $viewer = User::factory()->create();

    $response = $this->actingAs($viewer)
        ->getJson('/bleeps/lazy-load?page=1&tab=following')
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(trim($response->json('html')))->toBe('');
});

it('returns followed users bleeps on the following feed', function () {
    $viewer = User::factory()->create();
    $followed = User::factory()->create();

    Following::create([
        'follower_id' => $viewer->id,
        'followed_id' => $followed->id,
    ]);

    Bleep::factory()->for($followed)->create([
        'message' => 'Followed user bleep',
        'is_anonymous' => false,
    ]);

    $this->actingAs($viewer)
        ->getJson('/bleeps/lazy-load?page=1&tab=following')
        ->assertOk()
        ->assertSee('Followed user bleep');
});

it('returns the liked state and fresh count from the like endpoint', function () {
    $user = User::factory()->create();
    $bleep = Bleep::factory()->create();

    $this->actingAs($user)
        ->postJson("/bleeps/{$bleep->id}/like")
        ->assertOk()
        ->assertJson(['liked' => true, 'count' => 1]);

    $this->actingAs($user)
        ->postJson("/bleeps/{$bleep->id}/like")
        ->assertOk()
        ->assertJson(['liked' => false, 'count' => 0]);
});

it('renders the home feed with a query count that does not grow per card', function () {
    $viewer = User::factory()->create();
    $author = User::factory()->create();

    Following::create([
        'follower_id' => $viewer->id,
        'followed_id' => $author->id,
    ]);

    Bleep::factory()->count(20)->for($author)->create(['is_anonymous' => false]);

    $this->actingAs($viewer);

    // Follow state still renders correctly from the shared context.
    $this->get('/')->assertOk()->assertSee('data-following="1"', false);

    $homeQueryCount = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get('/')->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    $baseline = $homeQueryCount();

    // Doubling the number of cards must not add per-card queries.
    Bleep::factory()->count(20)->for($author)->create(['is_anonymous' => false]);

    $withMoreCards = $homeQueryCount();

    expect($baseline)->toBeLessThanOrEqual(60)
        ->and($withMoreCards)->toBeLessThanOrEqual($baseline + 5);
});
