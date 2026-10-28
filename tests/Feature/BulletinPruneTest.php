<?php

use App\Models\BulletinPost;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

it('archives expired notes and deletes old archived ones', function () {
    $active = BulletinPost::factory()->create();
    $expired = BulletinPost::factory()->expired()->create();
    $old = BulletinPost::factory()->archived()->create(['archived_at' => now()->subDays(40)]);
    $recent = BulletinPost::factory()->archived()->create(['archived_at' => now()->subDays(2)]);

    $this->artisan('bulletin:prune')
        ->expectsOutputToContain('Archived 1 expired notes and deleted 1 old notes.')
        ->assertSuccessful();

    expect($expired->fresh()->archived_at)->not->toBeNull()
        ->and($active->fresh()->archived_at)->toBeNull()
        ->and(BulletinPost::find($old->id))->toBeNull()
        ->and(BulletinPost::find($recent->id))->not->toBeNull();
});

it('removes reactions when a pruned note is deleted', function () {
    $post = BulletinPost::factory()->archived()->create(['archived_at' => now()->subDays(40)]);

    $post->reactions()->create([
        'reaction_type' => 'heart',
        'visitor_hash' => str_repeat('a', 64),
    ]);

    $this->artisan('bulletin:prune')->assertSuccessful();

    $this->assertDatabaseMissing('bulletin_reactions', [
        'bulletin_post_id' => $post->id,
    ]);
});

it('deletes stored attachments when pruned notes are removed', function () {
    Storage::fake('public');
    Storage::disk('public')->put('bulletin/01TEST/note.png', 'image');

    BulletinPost::factory()->archived()->create([
        'archived_at' => now()->subDays(40),
        'media_path' => 'bulletin/01TEST/note.png',
        'media_mime' => 'image/png',
        'media_kind' => 'image',
    ]);

    $this->artisan('bulletin:prune')->assertSuccessful();

    Storage::disk('public')->assertMissing('bulletin/01TEST/note.png');
});

it('sweeps expired notes at most once per hour on board visits', function () {
    Cache::flush();

    $first = BulletinPost::factory()->expired()->create();

    $this->get('/bulletin')->assertOk();

    $second = BulletinPost::factory()->expired()->create();

    $this->get('/bulletin')->assertOk();

    expect($first->fresh()->archived_at)->not->toBeNull()
        ->and($second->fresh()->archived_at)->toBeNull();
});
