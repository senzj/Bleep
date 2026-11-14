<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('shows the password form inside the edit profile tab', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('settings.profile'));

    $response->assertOk()
        ->assertSee('Change Password')
        ->assertSee('name="current_password"', false)
        ->assertSee('name="password_confirmation"', false)
        ->assertSee('id="pwdStrength"', false)
        ->assertDontSee('href="'.route('settings.password').'"', false);
});

it('redirects the old password page to the profile tab anchor', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.password'))
        ->assertRedirect(route('settings.profile').'#password');
});

it('updates the password from the profile tab and returns to it', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);

    $this->actingAs($user)
        ->put(route('settings.password.update'), [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertRedirect(route('settings.profile').'#password')
        ->assertSessionHas('success', 'Password updated.');

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

it('rejects a wrong current password', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);

    $this->actingAs($user)
        ->put(route('settings.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});
