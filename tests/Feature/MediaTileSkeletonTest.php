<?php

use App\Models\Bleep;
use App\Models\BleepMedia;
use App\Models\User;

it('renders a skeleton placeholder for media that has not loaded yet', function () {
    $user = User::factory()->create(['profile_picture' => null]);

    $bleep = Bleep::factory()->create([
        'user_id' => $user->id,
        'message' => 'Skeleton media',
        'is_anonymous' => false,
    ]);

    BleepMedia::create([
        'bleep_id' => $bleep->id,
        'path' => 'bleep/'.$bleep->id.'/photo.jpg',
        'type' => 'image',
        'original_name' => 'photo.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1024,
    ]);

    $this->get(route('post', ['id' => $bleep->id]))
        ->assertOk()
        ->assertSee('media-pending', false)
        ->assertSee('media-skeleton skeleton', false)
        ->assertSee('media-single', false)
        ->assertSee('data-media-view=', false)
        ->assertSee('/media/image/thumb/', false)
        ->assertSee('/media/image/view/', false)
        ->assertSee('data-src=', false);
});

it('renders a skeleton placeholder for a pending video', function () {
    $user = User::factory()->create(['profile_picture' => null]);

    $bleep = Bleep::factory()->create([
        'user_id' => $user->id,
        'message' => 'Skeleton video',
        'is_anonymous' => false,
    ]);

    BleepMedia::create([
        'bleep_id' => $bleep->id,
        'path' => 'bleep/'.$bleep->id.'/clip.mp4',
        'type' => 'video',
        'original_name' => 'clip.mp4',
        'mime_type' => 'video/mp4',
        'size' => 1024,
    ]);

    $this->get(route('post', ['id' => $bleep->id]))
        ->assertOk()
        ->assertSee('media-pending', false)
        ->assertSee('media-skeleton skeleton', false)
        ->assertSee('source data-src=', false);
});
