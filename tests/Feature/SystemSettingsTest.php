<?php

use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserPreferences;
use App\Services\System\SystemSettings;

it('shows the system page to admins', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/system')
        ->assertOk()
        ->assertSee('System configuration')
        ->assertSee('PHP version')
        ->assertSee(PHP_VERSION)
        ->assertSee('Reverb')
        ->assertSee('Registration open');
});

it('forbids regular users from the system page', function () {
    $user = User::factory()->create(['role' => 'user']);

    $this->actingAs($user)->get('/admin/system')->assertForbidden();
    $this->actingAs($user)->post('/admin/system', [])->assertForbidden();
});

it('persists settings and applies them to configuration', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post('/admin/system', [
        'registration_enabled' => '0',
        'anonymous_posting_enabled' => '1',
        'default_theme' => 'dark',
        'default_bleeps_per_page' => 25,
        'max_bleep_file_kb' => 4096,
        'default_media_downloads' => '1',
    ])->assertRedirect(route('admin.system'));

    expect(SystemSetting::count())->toBe(6)
        ->and(SystemSettings::get('registration_enabled'))->toBeFalse()
        ->and(SystemSettings::get('default_theme'))->toBe('dark')
        ->and(SystemSettings::get('default_bleeps_per_page'))->toBe(25)
        ->and(SystemSettings::get('max_bleep_file_kb'))->toBe(4096)
        ->and(SystemSettings::get('default_media_downloads'))->toBeTrue()
        ->and(config('app.registration_enabled'))->toBeFalse()
        ->and(config('media.max_bleep_file_kb'))->toBe(4096);
});

it('validates setting types', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post('/admin/system', [
        'default_theme' => 'neon',
        'default_bleeps_per_page' => 1,
        'max_bleep_file_kb' => 'lots',
    ])->assertSessionHasErrors([
        'default_theme',
        'default_bleeps_per_page',
        'max_bleep_file_kb',
    ]);

    expect(SystemSetting::count())->toBe(0);
});

it('uses system defaults for new user preferences', function () {
    SystemSettings::update([
        'default_theme' => 'dark',
        'default_nav_layout' => 'vertical',
        'default_bleeps_per_page' => 30,
        'default_media_downloads' => true,
    ]);

    $defaults = UserPreferences::defaults();

    expect($defaults['theme'])->toBe('dark')
        ->and($defaults['nav_layout'])->toBe('vertical')
        ->and($defaults['bleeps_per_page'])->toBe(30)
        ->and($defaults['media_downloads'])->toBeTrue();
});

it('applies personal defaults to existing accounts', function () {
    $user = User::factory()->create();
    $user->preferences()->create(UserPreferences::defaults());

    SystemSettings::update([
        'default_theme' => 'dark',
        'default_nav_layout' => 'vertical',
    ]);

    $updated = SystemSettings::applyDefaultsToExistingUsers();

    expect($updated)->toBe(1)
        ->and($user->preferences()->first()->theme)->toBe('dark')
        ->and($user->preferences()->first()->nav_layout)->toBe('vertical');
});

it('blocks registration when sign-ups are closed', function () {
    SystemSettings::update(['registration_enabled' => false]);

    $this->get('/register')->assertForbidden();
    $this->post('/register', [])->assertForbidden();
});
