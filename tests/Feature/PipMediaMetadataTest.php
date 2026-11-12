<?php

use App\Models\Bleep;
use App\Models\BleepMedia;
use App\Models\User;

it('exposes title, subtitle and author for the floating audio player', function () {
    $user = User::factory()->create([
        'dname' => 'Nova',
        'username' => 'nova',
        'profile_picture' => null,
    ]);

    $bleep = Bleep::factory()->create([
        'user_id' => $user->id,
        'message' => "First line\nSecond line",
        'is_anonymous' => false,
    ]);

    BleepMedia::create([
        'bleep_id' => $bleep->id,
        'path' => 'bleep/'.$bleep->id.'/track.mp3',
        'type' => 'audio',
        'original_name' => 'track.mp3',
        'mime_type' => 'audio/mpeg',
        'size' => 1024,
    ]);

    $this->get(route('post', ['id' => $bleep->id]))
        ->assertOk()
        ->assertSee('data-media-title="First line"', false)
        ->assertSee('data-media-subtitle="Second line"', false)
        ->assertSee('data-media-poster="Nova"', false)
        ->assertSee('data-media-poster-handle="@nova"', false);
});

it('falls back to the media filename when a bleep has no message', function () {
    $user = User::factory()->create([
        'dname' => 'Nova',
        'username' => 'nova',
        'profile_picture' => null,
    ]);

    $bleep = Bleep::factory()->create([
        'user_id' => $user->id,
        'message' => null,
        'is_anonymous' => false,
    ]);

    BleepMedia::create([
        'bleep_id' => $bleep->id,
        'path' => 'bleep/'.$bleep->id.'/Midnight Drive.mp3',
        'type' => 'audio',
        'original_name' => 'Midnight Drive.mp3',
        'mime_type' => 'audio/mpeg',
        'size' => 1024,
    ]);

    $this->get(route('post', ['id' => $bleep->id]))
        ->assertOk()
        ->assertSee('data-media-title="Midnight Drive"', false)
        ->assertDontSee('data-media-subtitle=', false);
});

it('keeps anonymous authors anonymous in the floating player', function () {
    $user = User::factory()->create([
        'dname' => 'Real Name',
        'username' => 'realname',
        'profile_picture' => null,
    ]);

    $bleep = Bleep::factory()->anonymous()->create([
        'user_id' => $user->id,
        'message' => 'Anon track',
    ]);

    BleepMedia::create([
        'bleep_id' => $bleep->id,
        'path' => 'bleep/'.$bleep->id.'/anon.mp3',
        'type' => 'audio',
        'original_name' => 'anon.mp3',
        'mime_type' => 'audio/mpeg',
        'size' => 1024,
    ]);

    $this->get(route('post', ['id' => $bleep->id]))
        ->assertOk()
        ->assertSee('data-media-title="Anon track"', false)
        ->assertSee('data-media-poster-handle="@anonymous"', false)
        ->assertDontSee('data-media-poster="Real Name"', false);
});
