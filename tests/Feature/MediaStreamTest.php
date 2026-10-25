<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

it('serves files that live inside the public storage disk', function () {
    Storage::disk('public')->put('bleep/1/allowed.txt', 'hello media');

    $this->get('/media/stream/bleep/1/allowed.txt')
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Accept-Ranges', 'bytes');
});

it('blocks path traversal outside the public storage disk', function () {
    $outsideFile = storage_path('app/traversal-secret.txt');
    File::ensureDirectoryExists(dirname($outsideFile));
    File::put($outsideFile, 'top secret');

    try {
        $this->get('/media/stream/..%2Ftraversal-secret.txt')
            ->assertNotFound();
    } finally {
        File::delete($outsideFile);
    }
});

it('returns not found for missing media', function () {
    $this->get('/media/stream/bleep/1/missing.txt')->assertNotFound();
});
