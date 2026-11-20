<?php

use App\Models\Bleep;
use App\Models\User;

/**
 * Only visitors clear an age check. Signed-in members see the post with its
 * normal NSFW cover, whatever their blur preferences are.
 */
it('shows the age gate to guests on an NSFW post page', function () {
    $bleep = Bleep::factory()->nsfw()->create([
        'is_anonymous' => false,
    ]);

    $response = $this->get("/bleeps/{$bleep->id}")
        ->assertOk()
        ->assertSee('data-nsfw-age-gate', false)
        ->assertSee('Sensitive content warning')
        ->assertSee('at your own risk')
        ->assertSee('fixed inset-0', false)
        ->assertSee('data-nsfw-age-content', false)
        ->assertSee('I understand — view post');

    expect($response->getContent())->toContain('hidden space-y-4');
});

it('hides NSFW cards from the guest feed', function () {
    $bleep = Bleep::factory()->nsfw()->create([
        'message' => 'Guest feed NSFW card',
        'is_anonymous' => false,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('Guest feed NSFW card')
        ->assertDontSee('data-bleep-card="'.$bleep->id.'"', false);
});

it('shows NSFW cards to members who opted in', function () {
    $viewer = User::factory()->create();
    $viewer->getPreferences()->forceFill(['show_nsfw' => true])->save();

    $bleep = Bleep::factory()->nsfw()->create([
        'message' => 'Member feed NSFW card',
        'is_anonymous' => false,
    ]);

    $this->actingAs($viewer)
        ->get('/')
        ->assertOk()
        ->assertSee('data-bleep-card="'.$bleep->id.'"', false);
});

it('skips the age gate for signed-in viewers who opted into unblurred NSFW media', function () {
    $viewer = User::factory()->create(['age_confirmed_at' => now()]);

    $viewer->getPreferences()->forceFill([
        'show_nsfw' => true,
        'blur_nsfw_media' => false,
    ])->save();

    $bleep = Bleep::factory()->for($viewer)->nsfw()->create([
        'is_anonymous' => false,
    ]);

    $this->actingAs($viewer)
        ->get("/bleeps/{$bleep->id}")
        ->assertOk()
        ->assertDontSee('data-nsfw-age-gate', false);
});

it('skips the age gate for signed-in viewers even when NSFW media is blurred', function () {
    $viewer = User::factory()->create(['age_confirmed_at' => now()]);

    $viewer->getPreferences()->forceFill([
        'show_nsfw' => true,
        'blur_nsfw_media' => true,
    ])->save();

    $bleep = Bleep::factory()->for($viewer)->nsfw()->create([
        'is_anonymous' => false,
    ]);

    $this->actingAs($viewer)
        ->get("/bleeps/{$bleep->id}")
        ->assertOk()
        ->assertDontSee('data-nsfw-age-gate', false);
});

it('skips the age gate for signed-in viewers who have not confirmed their age', function () {
    $viewer = User::factory()->create(['age_confirmed_at' => null]);

    $viewer->getPreferences()->forceFill([
        'show_nsfw' => true,
        'blur_nsfw_media' => false,
    ])->save();

    $bleep = Bleep::factory()->for($viewer)->nsfw()->create([
        'is_anonymous' => false,
    ]);

    $this->actingAs($viewer)
        ->get("/bleeps/{$bleep->id}")
        ->assertOk()
        ->assertDontSee('data-nsfw-age-gate', false);
});
