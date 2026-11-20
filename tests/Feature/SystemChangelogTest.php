<?php

use App\Models\User;
use App\Services\System\Changelog;

it('reads separate version logs for Bleep and the bulletin board', function () {
    $changelog = app(Changelog::class);

    expect($changelog->currentVersion())->toBe('2.6.2')
        ->and($changelog->bulletinVersion())->toBe('1.1')
        ->and($changelog->currentVersion())->not->toBe($changelog->bulletinVersion())
        ->and($changelog->latest())->not->toBeNull()
        ->and($changelog->latest()['changes'])->toHaveKeys([
            'added', 'changed', 'updated', 'optimized', 'removed',
        ])
        ->and($changelog->bulletinLatest())->not->toBeNull()
        ->and($changelog->bulletinLatest()['changes'])->toHaveKeys([
            'added', 'changed', 'updated', 'optimized', 'removed',
        ]);
});

it('shows both version logs in settings for signed-in users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('settings.system'))
        ->assertSuccessful()
        ->assertSee('Version log')
        ->assertSee('v2.6.2')
        ->assertSee('v1.1')
        ->assertSee('Bleep')
        ->assertSee('Bulletin board')
        ->assertSee('Small things that feel better')
        ->assertSee('Notes that can change')
        ->assertSee('Added')
        ->assertSee('Optimized')
        ->assertSee('Removed');
});

it('keeps the system changelog behind authentication', function () {
    $this->get(route('settings.system'))
        ->assertRedirect(route('login'));
});

it('keeps release announcements out of the guest feed', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee('System Update v2.6.2')
        ->assertDontSee('Bulletin board patch: v1.1')
        ->assertDontSee('data-announcement="warning"', false)
        ->assertDontSee('data-announcement="update"', false);
});

it('keeps release announcements out of the signed-in feed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertDontSee('System Update v2.6.2')
        ->assertDontSee('data-announcement="update"', false);
});

it('explains the polling fallback when realtime notifications are unavailable', function () {
    config()->set('broadcasting.default', 'null');

    $this->blade('<x-announcement.system />')
        ->assertSee('Realtime notifications are unavailable on this host');
});

it('advertises live notifications when reverb is configured', function () {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');

    $this->blade('<x-announcement.system />')
        ->assertSee('Notifications arrive live when the realtime connection is available');
});
