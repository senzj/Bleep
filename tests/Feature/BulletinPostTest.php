<?php

use App\Models\BulletinPost;
use App\Models\BulletinReaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

afterEach(function () {
    Carbon::setTestNow();
});

it('generates a public id and an eight month expiry', function () {
    Carbon::setTestNow('2026-10-25 12:00:00');

    $post = BulletinPost::create([
        'anonymous_name' => 'Quietfox_492817',
        'content' => 'A note for the board.',
        'visitor_hash' => str_repeat('a', 64),
    ]);

    expect(strlen($post->public_id))->toBe(26)
        ->and($post->expires_at->toDateTimeString())->toBe('2027-06-25 12:00:00');
});

it('uses the public id as its route key', function () {
    $post = BulletinPost::factory()->create();

    expect($post->getRouteKeyName())->toBe('public_id')
        ->and($post->getRouteKey())->toBe($post->public_id);
});

it('only returns active posts from the active scope', function () {
    $active = BulletinPost::factory()->create();
    $archived = BulletinPost::factory()->archived()->create();
    $expired = BulletinPost::factory()->expired()->create();

    $ids = BulletinPost::query()->active()->pluck('id');

    expect($ids)->toContain($active->id)
        ->not->toContain($archived->id)
        ->not->toContain($expired->id);

    expect($archived->isExpired())->toBeFalse()
        ->and($expired->isExpired())->toBeTrue();
});

it('stores one reaction per visitor per post', function () {
    $post = BulletinPost::factory()->create();

    $post->reactions()->create([
        'reaction_type' => 'heart',
        'visitor_hash' => str_repeat('b', 64),
    ]);

    expect(fn () => $post->reactions()->create([
        'reaction_type' => 'fire',
        'visitor_hash' => str_repeat('b', 64),
    ]))->toThrow(QueryException::class);

    $post->reactions()->create([
        'reaction_type' => 'fire',
        'visitor_hash' => str_repeat('c', 64),
    ]);

    expect($post->reactions()->count())->toBe(2);
});

it('deletes reactions when its post is deleted', function () {
    $post = BulletinPost::factory()->create();
    $post->reactions()->create([
        'reaction_type' => 'heart',
        'visitor_hash' => str_repeat('c', 64),
    ]);

    $post->delete();

    expect(BulletinReaction::count())->toBe(0);
});

it('never serializes internal ids or visitor hashes', function () {
    $post = BulletinPost::factory()->create();
    $reaction = $post->reactions()->create([
        'reaction_type' => 'heart',
        'visitor_hash' => str_repeat('d', 64),
    ]);

    expect($post->toArray())
        ->not->toHaveKey('id')
        ->not->toHaveKey('visitor_hash')
        ->and($reaction->toArray())
        ->not->toHaveKey('visitor_hash');
});
