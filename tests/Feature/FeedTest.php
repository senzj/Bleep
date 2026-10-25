<?php

use App\Models\Bleep;
use App\Models\User;
use App\Models\UserPreferences;

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
