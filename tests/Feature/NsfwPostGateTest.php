<?php

use App\Models\Bleep;
use App\Models\User;

/**
 * An NSFW post page must ask for confirmation on every visit unless the
 * signed-in viewer has confirmed their age and opted into unblurred NSFW.
 */
it('shows the age gate to guests on an NSFW post page', function () {
    $bleep = Bleep::factory()->nsfw()->create([
        'is_anonymous' => false,
    ]);

    $this->get("/bleeps/{$bleep->id}")
        ->assertOk()
        ->assertSee('data-nsfw-age-gate', false)
        ->assertSee('Age check required');
});

it('skips the age gate for a viewer who opted into unblurred NSFW media', function () {
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

it('shows the age gate when NSFW media is still blurred', function () {
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
        ->assertSee('data-nsfw-age-gate', false);
});

it('shows the age gate when the viewer has not confirmed their age', function () {
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
        ->assertSee('data-nsfw-age-gate', false);
});
