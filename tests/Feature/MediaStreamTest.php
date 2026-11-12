<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('streams media with range support', function () {
    Storage::disk('public')->put('bleep/1/clip.mp4', '0123456789');

    $this->get('/media/stream/bleep/1/clip.mp4')
        ->assertOk()
        ->assertHeader('Accept-Ranges', 'bytes')
        ->assertHeader('Content-Length', '10');
});

it('returns a partial response for a byte range', function () {
    Storage::disk('public')->put('bleep/1/clip.mp4', '0123456789');

    $response = $this->get('/media/stream/bleep/1/clip.mp4', [
        'Range' => 'bytes=2-5',
    ]);

    $response
        ->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 2-5/10')
        ->assertHeader('Content-Length', '4');

    $this->assertSame('2345', $response->streamedContent());
});

it('supports suffix byte ranges', function () {
    Storage::disk('public')->put('bleep/1/clip.mp4', '0123456789');

    $response = $this->get('/media/stream/bleep/1/clip.mp4', [
        'Range' => 'bytes=-3',
    ]);

    $response
        ->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 7-9/10');

    $this->assertSame('789', $response->streamedContent());
});

it('rejects an unsatisfiable byte range', function () {
    Storage::disk('public')->put('bleep/1/clip.mp4', '0123456789');

    $this->get('/media/stream/bleep/1/clip.mp4', [
        'Range' => 'bytes=20-30',
    ])
        ->assertStatus(416)
        ->assertHeader('Content-Range', 'bytes */10');
});

it('returns not found for missing media', function () {
    $this->get('/media/stream/bleep/1/missing.mp4')->assertNotFound();
});

it('does not allow escaping the public disk', function () {
    $this->get('/media/stream/../.env')->assertNotFound();
});
