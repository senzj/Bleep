<?php

use App\Models\Bleep;
use App\Models\User;
use App\Support\SupportPromo;

it('never places the card on an empty feed', function () {
    expect(SupportPromo::position(0))->toBeNull();
});

it('rolls a coin flip on the first feed render', function () {
    expect(SupportPromo::shouldShow(1, null, 0.49))->toBeTrue()
        ->and(SupportPromo::shouldShow(1, null, 0.5))->toBeFalse();
});

it('guarantees the second render when the first skipped the card', function () {
    expect(SupportPromo::shouldShow(2, null, 0.99))->toBeTrue();
});

it('never shows again on the render straight after a card', function () {
    expect(SupportPromo::shouldShow(2, 1, 0.0))->toBeFalse();
});

it('rolls the configured chance on the second render after a card', function () {
    config(['features.support_promo_chance' => 0.2]);

    expect(SupportPromo::shouldShow(3, 1, 0.19))->toBeTrue()
        ->and(SupportPromo::shouldShow(3, 1, 0.2))->toBeFalse();
});

it('guarantees the card after two quiet renders', function () {
    config(['features.support_promo_chance' => 0.2]);

    // Card was on render 3: 4 is forced quiet, 5 rolled and missed, so 6 lands.
    expect(SupportPromo::shouldShow(6, 3, 0.99))->toBeTrue();
});

it('treats a zero chance as an off switch', function () {
    config(['features.support_promo_chance' => 0]);

    expect(SupportPromo::shouldShow(1, null, 0.0))->toBeFalse()
        ->and(SupportPromo::shouldShow(2, null, 0.0))->toBeFalse()
        ->and(SupportPromo::shouldShow(10, 1, 0.0))->toBeFalse();
});

it('shows the support card inside the feed', function () {
    config(['features.support_promo_chance' => 1]);

    Bleep::factory()->create(['message' => 'Support card bleep', 'is_anonymous' => false]);

    $body = $this->withSession(['support_promo.fetches' => 10])
        ->get('/')
        ->assertOk()
        ->getContent();

    expect(substr_count($body, 'data-support-card'))->toBe(1)
        ->and($body)->toContain('Support the project')
        ->and($body)->toContain(config('features.support_url'));

    $cardAt = strpos($body, 'data-support-card');

    expect($cardAt)->toBeGreaterThan(strpos($body, 'id="bleeps-container"'))
        ->and($cardAt)->toBeLessThan(strpos($body, 'id="following-container"'));
});

it('points the support card at the configured support page', function () {
    config([
        'features.support_promo_chance' => 1,
        'features.support_url' => 'https://example.test/support-the-project',
    ]);

    Bleep::factory()->create(['is_anonymous' => false]);

    $this->withSession(['support_promo.fetches' => 10])
        ->get('/')
        ->assertOk()
        ->assertSee('https://example.test/support-the-project', false);
});

it('shows the support card to guests', function () {
    config(['features.support_promo_chance' => 1]);

    Bleep::factory()->create(['is_anonymous' => false]);

    $this->withSession(['support_promo.fetches' => 10])
        ->get('/')
        ->assertOk()
        ->assertSee('data-support-card', false);
});

it('shows the support card to signed-in members', function () {
    config(['features.support_promo_chance' => 1]);

    Bleep::factory()->create(['is_anonymous' => false]);

    $this->actingAs(User::factory()->create())
        ->withSession(['support_promo.fetches' => 10])
        ->get('/')
        ->assertOk()
        ->assertSee('data-support-card', false);
});

it('hides the support card when the chance is off', function () {
    config(['features.support_promo_chance' => 0]);

    Bleep::factory()->create(['is_anonymous' => false]);

    $this->withSession(['support_promo.fetches' => 10])
        ->get('/')
        ->assertOk()
        ->assertDontSee('data-support-card', false);
});

it('guarantees the card on the second render of a session', function () {
    config(['features.support_promo_chance' => 0.0001]);

    Bleep::factory()->create(['is_anonymous' => false]);

    $this->withSession(['support_promo.fetches' => 1])
        ->get('/')
        ->assertOk()
        ->assertSee('data-support-card', false);
});

it('keeps the render after a shown card quiet', function () {
    config(['features.support_promo_chance' => 1]);

    Bleep::factory()->create(['is_anonymous' => false]);

    $this->withSession([
        'support_promo.fetches' => 5,
        'support_promo.last_shown_at' => 5,
    ])
        ->get('/')
        ->assertOk()
        ->assertDontSee('data-support-card', false);
});

it('counts every feed render for the schedule', function () {
    config(['features.support_promo_chance' => 0]);

    Bleep::factory()->create(['is_anonymous' => false]);

    $this->get('/')->assertOk()->assertSessionHas('support_promo.fetches', 1);
    $this->get('/')->assertOk()->assertSessionHas('support_promo.fetches', 2);
});

it('shows the support card on paginated feed loads', function () {
    config(['features.support_promo_chance' => 1]);

    Bleep::factory()->count(3)->create(['is_anonymous' => false]);

    $this->withSession(['support_promo.fetches' => 10])
        ->getJson('/bleeps/lazy-load?page=1&tab=for-you')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertSee('data-support-card', false);
});
