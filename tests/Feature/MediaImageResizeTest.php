<?php

use App\Helpers\MediaPath;
use Illuminate\Support\Facades\Storage;

function createTestMediaImage(string $path, int $width, int $height, string $format = 'jpeg'): void
{
    $disk = Storage::disk('public');
    $disk->makeDirectory(dirname($path));

    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 120, 60, 200));

    $fullPath = $disk->path($path);

    match ($format) {
        'png' => imagepng($image, $fullPath),
        'gif' => imagegif($image, $fullPath),
        default => imagejpeg($image, $fullPath, 90),
    };

    imagedestroy($image);
}

it('downscales large images for the viewer', function () {
    Storage::fake('public');
    createTestMediaImage('bleep/1/photo.jpg', 2400, 3000);

    $this->get(route('media.image', ['size' => 'view', 'path' => 'bleep/1/photo.jpg']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp');

    $cached = collect(Storage::disk('public')->allFiles('media-cache/view'))->first();

    expect($cached)->not->toBeNull();

    [$width, $height] = getimagesize(Storage::disk('public')->path($cached));

    expect($height)->toBe(1800)
        ->and($width)->toBe(1440);
});

it('creates a grid thumbnail capped at 640 pixels', function () {
    Storage::fake('public');
    createTestMediaImage('bleep/1/photo.jpg', 2400, 3000);

    $this->get(route('media.image', ['size' => 'thumb', 'path' => 'bleep/1/photo.jpg']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp');

    $cached = collect(Storage::disk('public')->allFiles('media-cache/thumb'))->first();

    expect($cached)->not->toBeNull();

    [$width, $height] = getimagesize(Storage::disk('public')->path($cached));

    expect($height)->toBe(640)
        ->and($width)->toBe(512);
});

it('reuses the cached resized copy on later requests', function () {
    Storage::fake('public');
    createTestMediaImage('bleep/1/photo.jpg', 2400, 1600);

    $route = route('media.image', ['size' => 'view', 'path' => 'bleep/1/photo.jpg']);

    $this->get($route)->assertOk();
    $first = collect(Storage::disk('public')->allFiles('media-cache/view'))->first();

    $this->get($route)->assertOk();
    $files = collect(Storage::disk('public')->allFiles('media-cache/view'));

    expect($files)->toHaveCount(1)
        ->and($files->first())->toBe($first);
});

it('serves images that already fit without resizing them', function () {
    Storage::fake('public');
    createTestMediaImage('bleep/1/small.jpg', 320, 200);

    $this->get(route('media.image', ['size' => 'thumb', 'path' => 'bleep/1/small.jpg']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');

    expect(Storage::disk('public')->allFiles('media-cache'))->toBeEmpty();
});

it('passes animated gifs through untouched', function () {
    Storage::fake('public');
    createTestMediaImage('bleep/1/animated.gif', 1000, 1000, 'gif');

    $this->get(route('media.image', ['size' => 'thumb', 'path' => 'bleep/1/animated.gif']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/gif');

    expect(Storage::disk('public')->allFiles('media-cache'))->toBeEmpty();
});

it('returns not found for missing images and unknown sizes', function () {
    Storage::fake('public');

    $this->get(route('media.image', ['size' => 'view', 'path' => 'bleep/1/missing.jpg']))
        ->assertNotFound();

    createTestMediaImage('bleep/1/photo.jpg', 100, 100);

    $this->get('/media/image/huge/bleep/1/photo.jpg')->assertNotFound();
});

it('rejects paths that could escape the public disk', function (string $path) {
    expect(MediaPath::isSafe($path))->toBeFalse();
})->with([
    'parent segment' => '../secret.jpg',
    'nested parent' => 'bleep/../../secret.jpg',
    'windows separators' => 'bleep\\..\\secret.jpg',
    'null byte' => "bleep/\0secret.jpg",
    'empty' => '',
]);

it('accepts normal media paths', function (string $path) {
    expect(MediaPath::isSafe($path))->toBeTrue();
})->with([
    'image' => 'bleep/1/photo.jpg',
    'nested' => 'bleep/1/nested/photo.webp',
    'dots in filename' => 'bleep/1/photo..final.jpg',
]);
