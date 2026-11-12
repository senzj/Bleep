<?php

use App\Models\Bleep;
use App\Models\MediaUpload;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

/**
 * A 1x1 PNG split into two chunks.
 *
 * @return array{0: string, 1: string}
 */
function pngChunks(): array
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+ip1sAAAAASUVORK5CYII=');
    $half = (int) ceil(strlen($png) / 2);

    return [substr($png, 0, $half), substr($png, $half)];
}

function initUpload(User $user, int $size, int $totalChunks = 2)
{
    return test()->actingAs($user)->postJson('/uploads/init', [
        'original_name' => 'photo.png',
        'size' => $size,
        'mime_type' => 'image/png',
        'total_chunks' => $totalChunks,
        'context' => 'bleep',
    ]);
}

it('initialises a chunked upload', function () {
    $user = User::factory()->create();
    [$first, $second] = pngChunks();

    $response = initUpload($user, strlen($first) + strlen($second));

    $response
        ->assertOk()
        ->assertJsonPath('received_chunks', [])
        ->assertJsonPath('total_chunks', 2)
        ->assertJsonStructure(['upload_id', 'chunk_size_kb']);

    expect(MediaUpload::where('user_id', $user->id)->count())->toBe(1);
});

it('reports the configured chunk size', function () {
    config(['media.chunk_size_kb' => 8192]);

    $user = User::factory()->create();
    [$first, $second] = pngChunks();

    initUpload($user, strlen($first) + strlen($second))
        ->assertOk()
        ->assertJsonPath('chunk_size_kb', 8192);
});

it('accepts chunks in any order', function () {
    $user = User::factory()->create();
    [$first, $second] = pngChunks();
    $size = strlen($first) + strlen($second);

    $uploadId = initUpload($user, $size)->json('upload_id');

    $this->actingAs($user)
        ->post("/uploads/{$uploadId}/chunk", [
            'index' => 1,
            'chunk' => UploadedFile::fake()->createWithContent('chunk', $second),
        ])
        ->assertOk()
        ->assertJsonPath('received_chunks', [1]);

    $this->actingAs($user)
        ->post("/uploads/{$uploadId}/chunk", [
            'index' => 0,
            'chunk' => UploadedFile::fake()->createWithContent('chunk', $first),
        ])
        ->assertOk()
        ->assertJsonPath('received_chunks', [0, 1]);

    $this->actingAs($user)
        ->postJson("/uploads/{$uploadId}/complete")
        ->assertOk()
        ->assertJsonPath('status', MediaUpload::STATUS_COMPLETE);
});

it('stores chunks and reports progress', function () {
    $user = User::factory()->create();
    [$first, $second] = pngChunks();

    $uploadId = initUpload($user, strlen($first) + strlen($second))->json('upload_id');

    $this->actingAs($user)
        ->post("/uploads/{$uploadId}/chunk", [
            'index' => 0,
            'chunk' => UploadedFile::fake()->createWithContent('chunk', $first),
        ])
        ->assertOk()
        ->assertJsonPath('received_chunks', [0]);

    $this->actingAs($user)
        ->getJson("/uploads/{$uploadId}")
        ->assertOk()
        ->assertJsonPath('received_chunks', [0])
        ->assertJsonPath('status', MediaUpload::STATUS_PENDING);

    $this->actingAs($user)
        ->post("/uploads/{$uploadId}/chunk", [
            'index' => 1,
            'chunk' => UploadedFile::fake()->createWithContent('chunk', $second),
        ])
        ->assertOk()
        ->assertJsonPath('received_chunks', [0, 1]);
});

it('assembles completed chunks', function () {
    $user = User::factory()->create();
    [$first, $second] = pngChunks();
    $size = strlen($first) + strlen($second);

    $uploadId = initUpload($user, $size)->json('upload_id');

    foreach ([$first, $second] as $index => $chunk) {
        $this->actingAs($user)->post("/uploads/{$uploadId}/chunk", [
            'index' => $index,
            'chunk' => UploadedFile::fake()->createWithContent('chunk', $chunk),
        ]);
    }

    $this->actingAs($user)
        ->postJson("/uploads/{$uploadId}/complete")
        ->assertOk()
        ->assertJsonPath('status', MediaUpload::STATUS_COMPLETE)
        ->assertJsonPath('mime_type', 'image/png');

    $upload = MediaUpload::firstWhere('upload_id', $uploadId);

    expect($upload->size)->toBe($size)
        ->and(Storage::disk('local')->exists($upload->path))->toBeTrue();
});

it('refuses to complete before every chunk arrives', function () {
    $user = User::factory()->create();
    [$first, $second] = pngChunks();

    $uploadId = initUpload($user, strlen($first) + strlen($second))->json('upload_id');

    $this->actingAs($user)->post("/uploads/{$uploadId}/chunk", [
        'index' => 0,
        'chunk' => UploadedFile::fake()->createWithContent('chunk', $first),
    ]);

    $this->actingAs($user)
        ->postJson("/uploads/{$uploadId}/complete")
        ->assertStatus(409)
        ->assertJsonPath('received_chunks', [0]);
});

it('rejects unsupported file extensions', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/uploads/init', [
        'original_name' => 'payload.exe',
        'size' => 10,
        'total_chunks' => 1,
    ])->assertStatus(422);
});

it('does not expose uploads owned by another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    [$first, $second] = pngChunks();

    $uploadId = initUpload($owner, strlen($first) + strlen($second))->json('upload_id');

    $this->actingAs($other)->getJson("/uploads/{$uploadId}")->assertNotFound();
});

it('attaches a completed upload when posting a bleep', function () {
    $user = User::factory()->create();
    [$first, $second] = pngChunks();
    $size = strlen($first) + strlen($second);

    $uploadId = initUpload($user, $size)->json('upload_id');

    foreach ([$first, $second] as $index => $chunk) {
        $this->actingAs($user)->post("/uploads/{$uploadId}/chunk", [
            'index' => $index,
            'chunk' => UploadedFile::fake()->createWithContent('chunk', $chunk),
        ]);
    }

    $this->actingAs($user)->postJson("/uploads/{$uploadId}/complete")->assertOk();

    $response = $this->actingAs($user)->postJson('/bleeps', [
        'message' => 'Chunked photo',
        'upload_ids' => [$uploadId],
    ]);

    $response->assertOk()->assertJsonPath('success', true);

    $bleep = Bleep::latest('id')->first();
    $media = $bleep->media()->first();

    expect($media)->not->toBeNull()
        ->and($media->type)->toBe('image')
        ->and($media->mime_type)->toBe('image/png')
        ->and($media->original_name)->toBe('photo.png')
        ->and(Storage::disk('public')->exists($media->path))->toBeTrue()
        ->and($media->path)->toStartWith('bleep/'.$bleep->id.'/')
        ->and(MediaUpload::where('upload_id', $uploadId)->exists())->toBeFalse();
});

it('rejects upload ids owned by another user', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    [$first, $second] = pngChunks();

    $uploadId = initUpload($owner, strlen($first) + strlen($second))->json('upload_id');

    foreach ([$first, $second] as $index => $chunk) {
        $this->actingAs($owner)->post("/uploads/{$uploadId}/chunk", [
            'index' => $index,
            'chunk' => UploadedFile::fake()->createWithContent('chunk', $chunk),
        ]);
    }

    $this->actingAs($owner)->postJson("/uploads/{$uploadId}/complete")->assertOk();

    $this->actingAs($other)->postJson('/bleeps', [
        'message' => 'Trying to steal an upload',
        'upload_ids' => [$uploadId],
    ])->assertStatus(422);
});

it('rejects unknown upload ids when posting', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/bleeps', [
        'message' => 'Missing upload',
        'upload_ids' => ['00000000-0000-0000-0000-000000000000'],
    ])->assertStatus(422);
});

it('attaches a completed upload when updating a bleep', function () {
    $user = User::factory()->create();
    $bleep = Bleep::factory()->for($user)->create([
        'message' => 'Original',
        'created_at' => now(),
    ]);
    [$first, $second] = pngChunks();
    $size = strlen($first) + strlen($second);

    $uploadId = initUpload($user, $size)->json('upload_id');

    foreach ([$first, $second] as $index => $chunk) {
        $this->actingAs($user)->post("/uploads/{$uploadId}/chunk", [
            'index' => $index,
            'chunk' => UploadedFile::fake()->createWithContent('chunk', $chunk),
        ]);
    }

    $this->actingAs($user)->postJson("/uploads/{$uploadId}/complete")->assertOk();

    $this->actingAs($user)->putJson("/bleeps/{$bleep->id}/update", [
        'message' => 'Updated with a photo',
        'upload_ids' => [$uploadId],
    ])->assertOk()->assertJsonPath('success', true);

    $media = $bleep->fresh()->media()->first();

    expect($media)->not->toBeNull()
        ->and($media->type)->toBe('image')
        ->and($media->path)->toStartWith('bleep/'.$bleep->id.'/')
        ->and(MediaUpload::where('upload_id', $uploadId)->exists())->toBeFalse();
});
