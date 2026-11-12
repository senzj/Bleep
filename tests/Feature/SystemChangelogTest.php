<?php

use App\Models\User;
use App\Services\System\Changelog;

it('reads separate version logs for Bleep and the bulletin board', function () {
    $changelog = app(Changelog::class);

    expect($changelog->currentVersion())->toBe('2.4')
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
        ->assertSee('v2.4')
        ->assertSee('v1.1')
        ->assertSee('Bleep')
        ->assertSee('Bulletin board')
        ->assertSee('A faster media viewer')
        ->assertSee('Notes that can change')
        ->assertSee('Added')
        ->assertSee('Optimized')
        ->assertSee('Removed');
});

it('keeps the system changelog behind authentication', function () {
    $this->get(route('settings.system'))
        ->assertRedirect(route('login'));
});

it('advertises the latest Bleep and bulletin releases in the announcement', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee('System Update v2.4')
        ->assertSee('Bulletin board patch: v1.1')
        ->assertSee('Notes that can change')
        ->assertSee('data-announcement="warning"', false)
        ->assertSee('data-announcement="update"', false);
});

it('explains the polling fallback when realtime notifications are unavailable', function () {
    config()->set('broadcasting.default', 'null');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee('Realtime notifications are unavailable on this host');
});

it('advertises live notifications when reverb is configured', function () {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee('Notifications arrive live when the realtime connection is available');
});
