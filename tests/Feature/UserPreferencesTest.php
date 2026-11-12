<?php

use App\Models\Bleep;
use App\Models\BleepMedia;
use App\Models\Comments;
use App\Models\User;
use App\Models\UserPreferences;

it('defaults the off-screen media behaviour to pausing', function () {
    $user = User::factory()->create();

    expect($user->getPreferences()->media_offscreen)->toBe('pause');
});

it('remembers media positions by default', function () {
    $user = User::factory()->create();

    expect($user->getPreferences()->remember_media_position)->toBeTrue();

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('data-remember-media-position="1"', false);
});

it('saves the off-screen media behaviour from the preference api', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/preferences/update', [
            'key' => 'media_offscreen',
            'value' => 'pip',
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'key' => 'media_offscreen',
            'value' => 'pip',
        ]);

    expect($user->preferences()->first()->media_offscreen)->toBe('pip');
});

it('rejects an unknown off-screen media behaviour', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/preferences/update', [
            'key' => 'media_offscreen',
            'value' => 'explode',
        ])
        ->assertUnprocessable()
        ->assertJson([
            'success' => false,
            'error' => 'Invalid media off-screen behaviour',
        ]);

    expect($user->preferences()->first())->toBeNull();
});

it('batch saves the off-screen media behaviour', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/preferences/batch', [
            'preferences' => ['media_offscreen' => 'background'],
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect($user->preferences()->first()->media_offscreen)->toBe('background');
});

it('shows the chosen behaviour in settings and on the page body', function () {
    $user = User::factory()->create();
    $user->preferences()->create([
        ...UserPreferences::defaults(),
        'media_offscreen' => 'pip',
    ]);

    $this->actingAs($user)
        ->get('/settings/preferences')
        ->assertOk()
        ->assertSee('When audio or video is not on screen')
        ->assertSee('Pause playback')
        ->assertSee('Keep playing in the background')
        ->assertSee('Continue in a mini player')
        ->assertSee('value="pip"', false);

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('data-media-offscreen="pip"', false);
});

it('defaults media downloads to off', function () {
    $user = User::factory()->create();

    expect($user->getPreferences()->media_downloads)->toBeFalse();
});

it('remembers manual video pauses by default', function () {
    $user = User::factory()->create();

    expect($user->getPreferences()->resume_media_on_view)->toBeFalse();

    $this->actingAs($user)
        ->postJson('/api/preferences/update', [
            'key' => 'resume_media_on_view',
            'value' => true,
        ])
        ->assertOk()
        ->assertJsonPath('value', true);

    $this->actingAs($user->fresh())
        ->get('/')
        ->assertOk()
        ->assertSee('data-resume-media-on-view="1"', false);
});

it('saves the media download preference and exposes it on the page body', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/preferences/update', [
            'key' => 'media_downloads',
            'value' => true,
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'key' => 'media_downloads',
            'value' => true,
        ]);

    expect($user->preferences()->first()->media_downloads)->toBeTrue();

    $this->actingAs($user->fresh())
        ->get('/')
        ->assertOk()
        ->assertSee('data-media-downloads="1"', false);
});

it('hides the audio download button until media downloads are enabled', function () {
    $user = User::factory()->create();
    $bleep = Bleep::factory()->create(['user_id' => $user->id, 'message' => 'Track']);
    BleepMedia::create([
        'bleep_id' => $bleep->id,
        'path' => 'bleep/'.$bleep->id.'/track.mp3',
        'type' => 'audio',
        'original_name' => 'track.mp3',
        'mime_type' => 'audio/mpeg',
        'size' => 1024,
    ]);

    $this->actingAs($user)
        ->get(route('post', ['id' => $bleep->id]))
        ->assertOk()
        ->assertDontSee('title="Download"', false);

    $user->preferences()->updateOrCreate(
        ['user_id' => $user->id],
        [
            ...UserPreferences::defaults(),
            'media_downloads' => true,
        ]
    );

    $this->actingAs($user->fresh())
        ->get(route('post', ['id' => $bleep->id]))
        ->assertOk()
        ->assertSee('title="Download"', false);
});

it('shows anonymous comments by default and can hide them', function () {
    $user = User::factory()->create();
    $bleep = Bleep::factory()->create(['user_id' => $user->id]);

    commentsFor($bleep, $user, true, 'hidden anon comment');
    commentsFor($bleep, $user, false, 'visible comment');

    $this->actingAs($user)
        ->getJson("/bleeps/comments/{$bleep->id}/comments")
        ->assertOk()
        ->assertJsonFragment(['message' => 'hidden anon comment'])
        ->assertJsonFragment(['message' => 'visible comment']);

    $this->actingAs($user)
        ->postJson('/api/preferences/update', [
            'key' => 'show_anonymous_comments',
            'value' => false,
        ])
        ->assertOk()
        ->assertJsonPath('value', false);

    $this->actingAs($user->fresh())
        ->getJson("/bleeps/comments/{$bleep->id}/comments")
        ->assertOk()
        ->assertJsonMissing(['message' => 'hidden anon comment'])
        ->assertJsonFragment(['message' => 'visible comment']);
});

it('defaults the anonymous comment preference to on', function () {
    $user = User::factory()->create();

    expect($user->getPreferences()->show_anonymous_comments)->toBeTrue();
});

function commentsFor(Bleep $bleep, User $user, bool $anonymous, string $message): void
{
    Comments::factory()->create([
        'bleep_id' => $bleep->id,
        'user_id' => $user->id,
        'is_anonymous' => $anonymous,
        'message' => $message,
    ]);
}
