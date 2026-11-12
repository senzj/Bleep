<?php

use App\Models\User;

function registrationPayload(array $overrides = []): array
{
    return [
        'display_name' => 'Consent Tester',
        'username' => 'consent_tester',
        'email' => 'consent@example.com',
        'password' => 'Password1!',
        'password_confirmation' => 'Password1!',
        'timezone' => 'UTC',
        'accept_terms' => '1',
        'accept_privacy' => '1',
        'accept_cookies' => '1',
        'confirm_age' => '1',
        ...$overrides,
    ];
}

it('blocks registration until every policy consent is accepted', function (string $field) {
    $response = $this->post('/register', registrationPayload([$field => null]));

    $response->assertSessionHasErrors($field);
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['username' => 'consent_tester']);
})->with([
    'terms' => 'accept_terms',
    'privacy' => 'accept_privacy',
    'cookies' => 'accept_cookies',
    'age' => 'confirm_age',
]);

it('stores consent timestamps when registration succeeds', function () {
    $this->post('/register', registrationPayload())
        ->assertRedirect('/login');

    $user = User::where('username', 'consent_tester')->firstOrFail();

    expect($user->terms_accepted_at)->not->toBeNull()
        ->and($user->privacy_accepted_at)->not->toBeNull()
        ->and($user->cookies_accepted_at)->not->toBeNull()
        ->and($user->age_confirmed_at)->not->toBeNull();
});

it('records consent timestamps when an existing account accepts them at sign-in', function () {
    $user = User::factory()->create([
        'username' => 'existing_user',
        'password' => 'Password1!',
        'terms_accepted_at' => null,
        'privacy_accepted_at' => null,
        'cookies_accepted_at' => null,
        'age_confirmed_at' => null,
    ]);

    $this->post('/login', [
        'username' => $user->username,
        'password' => 'Password1!',
        'accept_terms' => '1',
        'accept_privacy' => '1',
        'accept_cookies' => '1',
        'confirm_age' => '1',
    ])->assertRedirect('/');

    $user->refresh();

    expect($user->terms_accepted_at)->not->toBeNull()
        ->and($user->age_confirmed_at)->not->toBeNull();
});

it('serves the legal documents', function (string $route) {
    $this->get(route($route))
        ->assertSuccessful()
        ->assertSee('Bleep');
})->with([
    'terms' => 'legal.terms',
    'privacy' => 'legal.privacy',
    'cookies' => 'legal.cookies',
]);
