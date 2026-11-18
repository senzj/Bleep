<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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

it('serves an uploaded profile picture through the media route', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('settings.profile.update'), [
            'dname' => $user->dname,
            'email' => $user->email,
            'profile_picture' => UploadedFile::fake()->image('avatar.png', 400, 400),
        ])
        ->assertRedirect(route('settings.profile'));

    $updated = $user->fresh();

    expect($updated->profile_picture)->not->toBeNull();

    $url = $updated->profile_picture_url;

    expect($url)->toContain('/media/image/thumb/');

    $this->get($url)->assertOk();

    $this->actingAs($updated)
        ->get(route('settings.profile'))
        ->assertOk()
        ->assertSee($url, false);
});

it('falls back to the default avatar when the stored picture is missing', function () {
    Storage::fake('public');

    $user = User::factory()->create(['profile_picture' => 'user/1/missing.png']);

    expect($user->profile_picture_url)->toBe(asset('images/avatar/default.jpg'));
});

it('serves the picture as a static asset when the host can reach it', function () {
    Storage::fake('public');
    Storage::disk('public')->put('user/9/avatar.png', 'fake');

    $user = User::factory()->create(['profile_picture' => 'user/9/avatar.png']);

    // Stand in for a host whose public/storage symlink works: the same path
    // resolves under public/, so no PHP request is needed to show the file.
    $publicFile = public_path('user/9/avatar.png');

    if (! is_dir(dirname($publicFile))) {
        mkdir(dirname($publicFile), 0777, true);
    }

    file_put_contents($publicFile, 'fake');

    try {
        expect($user->profile_picture_url)->toBe(asset('storage/user/9/avatar.png'));
    } finally {
        unlink($publicFile);
        rmdir(public_path('user/9'));
        rmdir(public_path('user'));
    }
});
