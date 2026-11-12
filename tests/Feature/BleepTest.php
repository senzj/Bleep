<?php

use App\Models\Bleep;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('redirects guests to login when creating a bleep', function () {
    $this->post('/bleeps', ['message' => 'Guest bleep'])
        ->assertRedirect(route('login'));

    $this->assertDatabaseCount('bleeps', 0);
});

it('allows authenticated users to create a bleep', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/bleeps', ['message' => 'My first bleep'])
        ->assertRedirect('/');

    $this->assertDatabaseHas('bleeps', [
        'user_id' => $user->id,
        'message' => 'My first bleep',
    ]);
});

it('returns the rendered card and undo url for ajax posts', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/bleeps', ['message' => 'Async bleep'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['bleep_id', 'html', 'undo_url']);

    expect($response->json('html'))->toContain('Async bleep')
        ->and($response->json('undo_url'))->toContain('/bleeps/'.$response->json('bleep_id'));
});

it('prevents users from deleting another users bleep', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $bleep = Bleep::factory()->for($owner)->create();

    $this->actingAs($otherUser)
        ->delete("/bleeps/{$bleep->id}/delete")
        ->assertForbidden();

    $this->assertNotSoftDeleted('bleeps', ['id' => $bleep->id]);
});

it('allows owners to soft delete their own bleep', function () {
    $owner = User::factory()->create();
    $bleep = Bleep::factory()->for($owner)->create(['created_at' => now()]);

    $this->actingAs($owner)
        ->delete("/bleeps/{$bleep->id}/delete")
        ->assertRedirect();

    $this->assertSoftDeleted('bleeps', ['id' => $bleep->id]);
    $this->assertDatabaseHas('bleeps', [
        'id' => $bleep->id,
        'deleted_by_author' => true,
    ]);
});

it('lets owners edit a bleep inside the ten minute window', function () {
    $owner = User::factory()->create();
    $bleep = Bleep::factory()->for($owner)->create([
        'message' => 'Original bleep',
        'created_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($owner)
        ->put("/bleeps/{$bleep->id}/update", ['message' => 'Edited bleep'])
        ->assertRedirect();

    expect($bleep->fresh()->message)->toBe('Edited bleep');
});

it('stops owners from editing a bleep after ten minutes', function () {
    $owner = User::factory()->create();
    $bleep = Bleep::factory()->for($owner)->create([
        'message' => 'Original bleep',
        'created_at' => now()->subMinutes(11),
    ]);

    $this->actingAs($owner)
        ->put("/bleeps/{$bleep->id}/update", ['message' => 'Edited bleep'])
        ->assertForbidden();

    expect($bleep->fresh()->message)->toBe('Original bleep');
});

it('stops owners from deleting a bleep after one week', function () {
    $owner = User::factory()->create();
    $bleep = Bleep::factory()->for($owner)->create([
        'created_at' => now()->subDays(8),
    ]);

    $this->actingAs($owner)
        ->delete("/bleeps/{$bleep->id}/delete")
        ->assertForbidden();

    $this->assertNotSoftDeleted('bleeps', ['id' => $bleep->id]);
});

it('accepts up to nine media files on a bleep', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $media = collect(range(1, 9))
        ->map(fn (int $index) => UploadedFile::fake()->image("photo-{$index}.jpg"))
        ->all();

    $this->actingAs($user)
        ->postJson('/bleeps', [
            'message' => 'Nine photos at once.',
            'media' => $media,
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Bleep::sole()->media()->count())->toBe(9);
});

it('rejects more than nine media files on a bleep', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $media = collect(range(1, 10))
        ->map(fn (int $index) => UploadedFile::fake()->image("photo-{$index}.jpg"))
        ->all();

    $this->actingAs($user)
        ->postJson('/bleeps', [
            'message' => 'Ten photos is too many.',
            'media' => $media,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['media']);

    expect(Bleep::count())->toBe(0);
});
