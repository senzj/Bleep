<?php

use App\Models\BulletinPost;
use App\Models\BulletinReaction;
use App\Models\BulletinReport;
use App\Services\Bulletin\BulletinVisitor;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\mock;

function bulletinPayload(array $overrides = []): array
{
    return array_merge([
        'content' => 'A note from a visitor.',
        'size' => '1x1',
    ], $overrides);
}

it('shows only active notes on the public board', function () {
    BulletinPost::factory()->create(['content' => 'Active bulletin note']);
    BulletinPost::factory()->expired()->create(['content' => 'Expired bulletin note']);
    BulletinPost::factory()->archived()->create(['content' => 'Archived bulletin note']);

    $this->get('/bulletin')
        ->assertOk()
        ->assertSee('Bleep Bulletin')
        ->assertSee('Active bulletin note')
        ->assertDontSee('Expired bulletin note')
        ->assertDontSee('Archived bulletin note');

    expect(session('bulletin_identity'))->toMatch('/^[A-Z][a-z]{2,}[A-Z][a-z]{2,}\d{1,3}$/');
});

it('hydrates the board with the public json payload and endpoints', function () {
    $post = BulletinPost::factory()->create([
        'content' => 'Hydrated note',
        'width' => 2,
        'height' => 1,
        'paper_color' => '#bbf7d0',
        'paper_texture' => 'crumpled',
    ]);

    $this->get('/bulletin')
        ->assertOk()
        ->assertSee('data-page="bulletin"', false)
        ->assertSee('id="bulletin-board"', false)
        ->assertSee('data-endpoint-posts="'.route('bulletin.posts.index').'"', false)
        ->assertSee('data-endpoint-store="'.route('bulletin.posts.store').'"', false)
        ->assertSee('&quot;id&quot;:&quot;'.$post->public_id.'&quot;', false)
        ->assertSee('&quot;width&quot;:2', false)
        ->assertSee('&quot;color&quot;:&quot;#bbf7d0&quot;', false)
        ->assertSee('&quot;texture&quot;:&quot;crumpled&quot;', false);
});

it('escapes note content on the board', function () {
    BulletinPost::factory()->create([
        'content' => '<script>alert("bulletin")</script>',
    ]);

    $this->get('/bulletin')
        ->assertOk()
        ->assertSee('&lt;script&gt;alert(&quot;bulletin&quot;)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert("bulletin")</script>', false);
});

it('creates a note with a server generated identity', function () {
    $response = $this->postJson('/bulletin/posts', bulletinPayload([
        'content' => 'Server identity note',
    ]))->assertCreated();

    $post = BulletinPost::sole();

    expect($post->anonymous_name)->toMatch('/^[A-Z][a-z]{2,}[A-Z][a-z]{2,}\d{1,3}$/')
        ->and(strlen($post->anonymous_name))->toBeLessThanOrEqual(64)
        ->and(strlen($post->visitor_hash))->toBe(64)
        ->and($post->content)->toBe('Server identity note')
        ->and($post->width)->toBe(1)
        ->and($post->height)->toBe(1)
        ->and($post->expires_at->isFuture())->toBeTrue()
        ->and($response->json('data.id'))->toBe($post->public_id)
        ->and($response->json('data'))->not->toHaveKey('visitor_hash');
});

it('ignores a client supplied name and reuses the session identity', function () {
    $payload = bulletinPayload([
        'anonymous_name' => 'Fakeidentity_000000',
    ]);

    $first = $this->withSession(['bulletin_identity' => 'LittleGold128'])
        ->postJson('/bulletin/posts', $payload)
        ->assertCreated()
        ->assertJsonPath('data.anonymous_name', 'LittleGold128');

    $second = $this->withSession(['bulletin_identity' => 'LittleGold128'])
        ->postJson('/bulletin/posts', $payload)
        ->assertCreated()
        ->assertJsonPath('data.anonymous_name', 'LittleGold128');

    expect(BulletinPost::count())->toBe(2)
        ->and($first->json('data.anonymous_name'))->toBe($second->json('data.anonymous_name'))
        ->and(BulletinPost::where('anonymous_name', 'Fakeidentity_000000')->exists())->toBeFalse();
});

it('replaces a legacy session identity with a short generated name', function () {
    $this->withSession(['bulletin_identity' => 'Quietfox_492817'])
        ->postJson('/bulletin/posts', bulletinPayload(['content' => 'Legacy identity note']))
        ->assertCreated()
        ->assertJsonPath('data.anonymous_name', fn (string $name) => preg_match('/^[A-Z][a-z]{2,}[A-Z][a-z]{2,}\d{1,3}$/', $name) === 1);
});

it('accepts the allowed note sizes', function (string $size, int $width, int $height) {
    $this->postJson('/bulletin/posts', bulletinPayload([
        'size' => $size,
    ]))->assertCreated();

    $post = BulletinPost::sole();

    expect($post->width)->toBe($width)
        ->and($post->height)->toBe($height);
})->with([
    '1x1' => ['1x1', 1, 1],
    '1x2' => ['1x2', 1, 2],
    '1x3' => ['1x3', 1, 3],
    '1x4' => ['1x4', 1, 4],
    '2x1' => ['2x1', 2, 1],
    '2x2' => ['2x2', 2, 2],
    '2x3' => ['2x3', 2, 3],
    '2x4' => ['2x4', 2, 4],
]);

it('validates note content, size, and URLs', function (array $payload, array $errors) {
    $this->postJson('/bulletin/posts', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'content required' => [
        ['content' => '', 'size' => '1x1'],
        ['content'],
    ],
    'content too long' => [
        ['content' => str_repeat('a', 2001), 'size' => '1x1'],
        ['content'],
    ],
    'invalid size' => [
        ['content' => 'A note', 'size' => '3x3'],
        ['size'],
    ],
    'invalid width' => [
        ['content' => 'A note', 'width' => 3, 'height' => 1],
        ['width'],
    ],
    'invalid height' => [
        ['content' => 'A note', 'width' => 1, 'height' => 0],
        ['height'],
    ],
    'unsafe link scheme' => [
        ['content' => 'A note', 'size' => '1x1', 'link_url' => 'javascript:alert(1)'],
        ['link_url'],
    ],
    'unsafe image scheme' => [
        ['content' => 'A note', 'size' => '1x1', 'image_url' => 'ftp://example.com/image.jpg'],
        ['image_url'],
    ],
    'invalid paper colour' => [
        ['content' => 'A note', 'size' => '1x1', 'paper_color' => 'yellow'],
        ['paper_color'],
    ],
    'unsafe paper colour' => [
        ['content' => 'A note', 'size' => '1x1', 'paper_color' => '#ff0000;drop'],
        ['paper_color'],
    ],
    'invalid paper texture' => [
        ['content' => 'A note', 'size' => '1x1', 'paper_texture' => 'glitter'],
        ['paper_texture'],
    ],
]);

it('accepts a custom paper colour and returns it publicly', function () {
    $this->postJson('/bulletin/posts', bulletinPayload([
        'paper_color' => '#12ab34',
    ]))
        ->assertCreated()
        ->assertJsonPath('data.color', '#12ab34');

    expect(BulletinPost::sole()->paper_color)->toBe('#12ab34');
});

it('accepts each paper texture and normalises its case', function (string $texture) {
    $this->postJson('/bulletin/posts', bulletinPayload([
        'paper_texture' => ucfirst($texture),
    ]))
        ->assertCreated()
        ->assertJsonPath('data.texture', $texture);

    expect(BulletinPost::sole()->paper_texture)->toBe($texture);
})->with([
    'plain',
    'torn',
    'folded',
    'crumpled',
    'scratched',
    'perforated',
    'scraps',
    'notebook',
    'torn-edges',
    'zigzag',
]);

it('leaves the paper texture empty when the visitor does not pick one', function () {
    $this->postJson('/bulletin/posts', bulletinPayload())
        ->assertCreated()
        ->assertJsonPath('data.texture', null);

    expect(BulletinPost::sole()->paper_texture)->toBeNull();
});

it('accepts exactly 2000 characters and valid http urls', function () {
    $this->postJson('/bulletin/posts', bulletinPayload([
        'content' => str_repeat('a', 2000),
        'link_url' => 'https://example.com/a-link',
        'image_url' => 'https://example.com/image.jpg',
    ]))->assertCreated();

    $post = BulletinPost::sole();

    expect(strlen($post->content))->toBe(2000)
        ->and($post->link_url)->toBe('https://example.com/a-link')
        ->and($post->image_url)->toBe('https://example.com/image.jpg');
});

it('stores an uploaded image and exposes a public media url', function () {
    Storage::fake('public');

    $response = $this->post('/bulletin/posts', bulletinPayload([
        'media' => UploadedFile::fake()->image('note.jpg', 640, 480),
    ]), ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.media_kind', 'image')
        ->assertJsonPath('data.media_type', 'image/jpeg');

    $post = BulletinPost::sole();

    expect($post->media_path)->toStartWith("bulletin/{$post->public_id}/")
        ->and($response->json('data.media_url'))->toContain($post->media_path);

    Storage::disk('public')->assertExists($post->media_path);
});

it('stores an uploaded video attachment', function () {
    Storage::fake('public');

    $this->post('/bulletin/posts', bulletinPayload([
        'media' => UploadedFile::fake()->create('clip.mp4', 120, 'video/mp4'),
    ]), ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.media_kind', 'video')
        ->assertJsonPath('data.media_type', 'video/mp4');

    Storage::disk('public')->assertExists(BulletinPost::sole()->media_path);
});

it('treats an uploaded gif as an image attachment', function () {
    Storage::fake('public');

    $this->post('/bulletin/posts', bulletinPayload([
        'media' => UploadedFile::fake()->create('loop.gif', 40, 'image/gif'),
    ]), ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.media_kind', 'image')
        ->assertJsonPath('data.media_type', 'image/gif');
});

it('rejects an unsupported attachment type', function () {
    Storage::fake('public');

    $this->post('/bulletin/posts', bulletinPayload([
        'media' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
    ]), ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['media']);

    expect(BulletinPost::count())->toBe(0);
});

it('deletes a stored attachment when its note is deleted', function () {
    Storage::fake('public');

    $this->post('/bulletin/posts', bulletinPayload([
        'media' => UploadedFile::fake()->image('note.png'),
    ]), ['Accept' => 'application/json'])->assertCreated();

    $post = BulletinPost::sole();
    $path = $post->media_path;

    Storage::disk('public')->assertExists($path);

    $post->delete();

    Storage::disk('public')->assertMissing($path);
});

it('lists active notes with a public cursor and hides expired notes', function () {
    $oldest = BulletinPost::factory()->create([
        'content' => 'Oldest bulletin note',
        'created_at' => now()->subMinutes(3),
        'expires_at' => now()->addMonth(),
    ]);
    $middle = BulletinPost::factory()->create([
        'content' => 'Middle bulletin note',
        'created_at' => now()->subMinutes(2),
        'expires_at' => now()->addMonth(),
    ]);
    $newest = BulletinPost::factory()->create([
        'content' => 'Newest bulletin note',
        'created_at' => now()->subMinute(),
        'expires_at' => now()->addMonth(),
    ]);
    BulletinPost::factory()->expired()->create(['content' => 'Hidden expired note']);

    $first = $this->getJson('/bulletin/posts?limit=2')
        ->assertOk()
        ->assertJsonPath('meta.limit', 2)
        ->assertJsonPath('meta.has_more', true)
        ->assertJsonPath('meta.next_cursor', $middle->public_id);

    expect(collect($first->json('data'))->pluck('id')->all())
        ->toBe([$newest->public_id, $middle->public_id]);

    $second = $this->getJson('/bulletin/posts?limit=2&before='.$middle->public_id)
        ->assertOk()
        ->assertJsonPath('meta.has_more', false)
        ->assertJsonPath('meta.next_cursor', null);

    expect(collect($second->json('data'))->pluck('id')->all())
        ->toBe([$oldest->public_id]);

    $this->getJson('/bulletin/posts')
        ->assertOk()
        ->assertJsonMissing(['content' => 'Hidden expired note']);
});

it('redirects a normal form submission back to the board', function () {
    $this->post('/bulletin/posts', bulletinPayload([
        'content' => 'Fallback form note',
    ]))
        ->assertRedirect(route('bulletin.index'))
        ->assertSessionHas('success');

    expect(BulletinPost::sole()->content)->toBe('Fallback form note');
});

it('defines a per-visitor post limit alongside the per-IP limit', function () {
    $callback = RateLimiter::limiter('bulletin-posts');

    expect($callback)->not->toBeNull();

    $limitsFor = function (string $sessionId) use ($callback): array {
        $request = Request::create('/bulletin/posts', 'POST');
        $request->setLaravelSession(
            new Store('bulletin_test', new ArraySessionHandler(120), $sessionId)
        );

        return $callback($request);
    };

    $first = $limitsFor('bulletin-session-one');
    $second = $limitsFor('bulletin-session-two');

    expect($first)->toHaveCount(2)
        ->and($first[0]->maxAttempts)->toBe(3)
        ->and($first[0]->decaySeconds)->toBe(600)
        ->and($first[0]->key)->toStartWith('bulletin-post:visitor:')
        ->and($first[1]->maxAttempts)->toBe(30)
        ->and($first[1]->decaySeconds)->toBe(600)
        ->and($first[1]->key)->toStartWith('bulletin-post:ip:')
        ->and($first[0]->key)->not->toBe($second[0]->key)
        ->and($first[1]->key)->toBe($second[1]->key);
});

it('blocks posts once the shared per-IP limit is exhausted', function () {
    // The test client starts a fresh session per request, so the per-visitor
    // limiter cannot accumulate. The per-IP limiter is the shared backstop.
    foreach (range(1, 30) as $attempt) {
        $this->postJson('/bulletin/posts', bulletinPayload([
            'content' => "Flood note {$attempt}",
        ]))->assertCreated();
    }

    $this->postJson('/bulletin/posts', bulletinPayload([
        'content' => 'One note too many',
    ]))->assertStatus(429);

    expect(BulletinPost::count())->toBe(30);
});

it('reacts to a note and returns fresh counts', function () {
    $post = BulletinPost::factory()->create();
    $visitor = mock(BulletinVisitor::class);
    $visitor->shouldReceive('hash')->andReturn(str_repeat('a', 64));
    $visitor->shouldReceive('ipHash')->andReturn(str_repeat('b', 64));

    $this->postJson("/bulletin/posts/{$post->public_id}/reactions", [
        'reaction_type' => 'heart',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $post->public_id)
        ->assertJsonPath('data.reactions.mine', 'heart')
        ->assertJsonPath('data.reactions.counts.heart', 1);

    expect(BulletinReaction::count())->toBe(1);
});

it('toggles the same reaction off for one visitor', function () {
    $post = BulletinPost::factory()->create();
    $visitor = mock(BulletinVisitor::class);
    $visitor->shouldReceive('hash')->andReturn(str_repeat('a', 64));
    $visitor->shouldReceive('ipHash')->andReturn(str_repeat('b', 64));

    $this->postJson("/bulletin/posts/{$post->public_id}/reactions", [
        'reaction_type' => 'fire',
    ])->assertOk()->assertJsonPath('data.reactions.mine', 'fire');

    $this->postJson("/bulletin/posts/{$post->public_id}/reactions", [
        'reaction_type' => 'fire',
    ])
        ->assertOk()
        ->assertJsonPath('data.reactions.mine', null)
        ->assertJsonPath('data.reactions.counts.fire', 0);

    expect(BulletinReaction::count())->toBe(0);
});

it('replaces a reaction when the visitor picks a different one', function () {
    $post = BulletinPost::factory()->create();
    $visitor = mock(BulletinVisitor::class);
    $visitor->shouldReceive('hash')->andReturn(str_repeat('a', 64));
    $visitor->shouldReceive('ipHash')->andReturn(str_repeat('b', 64));

    $this->postJson("/bulletin/posts/{$post->public_id}/reactions", [
        'reaction_type' => 'heart',
    ])->assertOk();

    $this->postJson("/bulletin/posts/{$post->public_id}/reactions", [
        'reaction_type' => 'idea',
    ])
        ->assertOk()
        ->assertJsonPath('data.reactions.mine', 'idea')
        ->assertJsonPath('data.reactions.counts.idea', 1)
        ->assertJsonPath('data.reactions.counts.heart', 0);

    expect(BulletinReaction::count())->toBe(1);
});

it('shows reaction counts and the visitor reaction in list payloads', function () {
    $post = BulletinPost::factory()->create();
    $visitor = mock(BulletinVisitor::class);
    $visitor->shouldReceive('hash')->andReturn(str_repeat('a', 64));
    $visitor->shouldReceive('ipHash')->andReturn(str_repeat('b', 64));

    BulletinReaction::query()->create([
        'bulletin_post_id' => $post->id,
        'reaction_type' => 'laugh',
        'visitor_hash' => str_repeat('c', 64),
    ]);

    $this->getJson('/bulletin/posts')
        ->assertOk()
        ->assertJsonPath('data.0.reactions.counts.laugh', 1)
        ->assertJsonPath('data.0.reactions.mine', null);

    $this->postJson("/bulletin/posts/{$post->public_id}/reactions", [
        'reaction_type' => 'wow',
    ])->assertOk();

    $this->getJson('/bulletin/posts')
        ->assertOk()
        ->assertJsonPath('data.0.reactions.counts.laugh', 1)
        ->assertJsonPath('data.0.reactions.counts.wow', 1)
        ->assertJsonPath('data.0.reactions.mine', 'wow');
});

it('validates reaction types', function () {
    $post = BulletinPost::factory()->create();

    $this->postJson("/bulletin/posts/{$post->public_id}/reactions", [
        'reaction_type' => 'angry',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reaction_type']);
});

it('does not accept reactions on expired or archived notes', function () {
    $expired = BulletinPost::factory()->expired()->create();
    $archived = BulletinPost::factory()->archived()->create();

    $this->postJson("/bulletin/posts/{$expired->public_id}/reactions", [
        'reaction_type' => 'heart',
    ])->assertNotFound();

    $this->postJson("/bulletin/posts/{$archived->public_id}/reactions", [
        'reaction_type' => 'heart',
    ])->assertNotFound();
});

it('defines a reaction rate limit per visitor and per IP', function () {
    $callback = RateLimiter::limiter('bulletin-reactions');

    expect($callback)->not->toBeNull();

    $request = Request::create('/bulletin/posts/01ABC/reactions', 'POST');
    $request->setLaravelSession(
        new Store('bulletin_test', new ArraySessionHandler(120), 'bulletin-reaction-session')
    );

    $limits = $callback($request);

    expect($limits)->toHaveCount(2)
        ->and($limits[0]->maxAttempts)->toBe(60)
        ->and($limits[0]->decaySeconds)->toBe(60)
        ->and($limits[0]->key)->toStartWith('bulletin-reaction:visitor:')
        ->and($limits[1]->maxAttempts)->toBe(240)
        ->and($limits[1]->decaySeconds)->toBe(60)
        ->and($limits[1]->key)->toStartWith('bulletin-reaction:ip:');
});

it('records an anonymous report with a reason', function () {
    $post = BulletinPost::factory()->create();
    $visitor = mock(BulletinVisitor::class);
    $visitor->shouldReceive('hash')->andReturn(str_repeat('a', 64));
    $visitor->shouldReceive('ipHash')->andReturn(str_repeat('b', 64));

    $this->postJson("/bulletin/posts/{$post->public_id}/reports", [
        'reason' => 'spam',
    ])
        ->assertCreated()
        ->assertJsonPath('data.id', $post->public_id)
        ->assertJsonPath('data.reported', true)
        ->assertJsonPath('data.already_reported', false);

    $report = BulletinReport::sole();

    expect($report->reason->value)->toBe('spam')
        ->and($report->visitor_hash)->toBe(str_repeat('a', 64));
});

it('keeps one report per visitor per note', function () {
    $post = BulletinPost::factory()->create();
    $visitor = mock(BulletinVisitor::class);
    $visitor->shouldReceive('hash')->andReturn(str_repeat('a', 64));
    $visitor->shouldReceive('ipHash')->andReturn(str_repeat('b', 64));

    $this->postJson("/bulletin/posts/{$post->public_id}/reports", [
        'reason' => 'harassment',
    ])->assertCreated();

    $this->postJson("/bulletin/posts/{$post->public_id}/reports", [
        'reason' => 'spam',
    ])
        ->assertOk()
        ->assertJsonPath('data.already_reported', true);

    expect(BulletinReport::count())->toBe(1)
        ->and(BulletinReport::sole()->reason->value)->toBe('harassment');
});

it('validates report reasons', function () {
    $post = BulletinPost::factory()->create();

    $this->postJson("/bulletin/posts/{$post->public_id}/reports", [
        'reason' => 'boring',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('does not accept reports on expired or archived notes', function () {
    $expired = BulletinPost::factory()->expired()->create();
    $archived = BulletinPost::factory()->archived()->create();

    $this->postJson("/bulletin/posts/{$expired->public_id}/reports", [
        'reason' => 'spam',
    ])->assertNotFound();

    $this->postJson("/bulletin/posts/{$archived->public_id}/reports", [
        'reason' => 'spam',
    ])->assertNotFound();
});

it('defines a report rate limit per visitor and per IP', function () {
    $callback = RateLimiter::limiter('bulletin-reports');

    expect($callback)->not->toBeNull();

    $request = Request::create('/bulletin/posts/01ABC/reports', 'POST');
    $request->setLaravelSession(
        new Store('bulletin_test', new ArraySessionHandler(120), 'bulletin-report-session')
    );

    $limits = $callback($request);

    expect($limits)->toHaveCount(2)
        ->and($limits[0]->maxAttempts)->toBe(5)
        ->and($limits[0]->decaySeconds)->toBe(60)
        ->and($limits[0]->key)->toStartWith('bulletin-report:visitor:')
        ->and($limits[1]->maxAttempts)->toBe(20)
        ->and($limits[1]->decaySeconds)->toBe(60)
        ->and($limits[1]->key)->toStartWith('bulletin-report:ip:');
});
