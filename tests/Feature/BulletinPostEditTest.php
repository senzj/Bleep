<?php

use App\Models\BulletinPost;
use App\Models\BulletinReaction;
use App\Models\BulletinReport;
use App\Services\Bulletin\BulletinVisitor;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

use function Pest\Laravel\mock;

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * Create a note through the public endpoint so it belongs to the current test
 * session, exactly like a real visitor's note.
 */
function createOwnedNote(TestCase $test, array $overrides = []): BulletinPost
{
    $test->postJson('/bulletin/posts', array_merge([
        'content' => 'Original note copy.',
        'size' => '1x1',
    ], $overrides))->assertCreated();

    return BulletinPost::query()->latest('id')->firstOrFail();
}

/**
 * The test client starts a new session for each request, so bind the visitor
 * hash to the note instead of relying on a session cookie.
 */
function actAsNoteOwner(BulletinPost $post): void
{
    $visitor = mock(BulletinVisitor::class);
    $visitor->shouldReceive('hash')->andReturn($post->visitor_hash);
    $visitor->shouldReceive('ipHash')->andReturn(str_repeat('b', 64));
}

it('lets the owner edit a note and records the edit time', function () {
    $post = createOwnedNote($this, ['content' => 'Original note copy.']);
    actAsNoteOwner($post);

    $response = $this->patchJson("/bulletin/posts/{$post->public_id}", [
        'content' => 'Updated note copy.',
        'link_url' => 'https://example.com/updated',
    ])
        ->assertOk()
        ->assertJsonPath('data.content', 'Updated note copy.')
        ->assertJsonPath('data.link_url', 'https://example.com/updated')
        ->assertJsonPath('data.can_edit', true)
        ->assertJsonPath('data.can_delete', true);

    expect($response->json('data.edited_at'))->not->toBeNull()
        ->and($post->fresh()->content)->toBe('Updated note copy.')
        ->and($post->fresh()->edited_at)->not->toBeNull();
});

it('keeps the original created time when a note is edited', function () {
    Carbon::setTestNow('2026-10-28 09:15:00');

    $post = createOwnedNote($this);
    $createdAt = $post->created_at->toDateTimeString();

    Carbon::setTestNow('2026-10-28 11:45:00');
    actAsNoteOwner($post);

    $this->patchJson("/bulletin/posts/{$post->public_id}", [
        'content' => 'Corrected note copy.',
    ])->assertOk();

    expect($post->fresh()->created_at->toDateTimeString())->toBe($createdAt);
});

it('rejects an edit after the one day window', function () {
    $post = createOwnedNote($this);

    $post->forceFill(['created_at' => now()->subDay()->subMinute()])->save();
    actAsNoteOwner($post);

    $this->patchJson("/bulletin/posts/{$post->public_id}", [
        'content' => 'Too late to change this.',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Notes can only be edited within 24 hours of pinning.');

    expect($post->fresh()->content)->toBe('Original note copy.');
});

it('rejects an edit from a visitor who did not pin the note', function () {
    $post = createOwnedNote($this);

    $visitor = mock(BulletinVisitor::class);
    $visitor->shouldReceive('hash')->andReturn(str_repeat('f', 64));
    $visitor->shouldReceive('ipHash')->andReturn(str_repeat('b', 64));

    $this->patchJson("/bulletin/posts/{$post->public_id}", [
        'content' => 'Not my note to change.',
    ])
        ->assertForbidden()
        ->assertJsonPath('message', 'Only the visitor who pinned this note can edit it.');

    expect($post->fresh()->content)->toBe('Original note copy.');
});

it('validates edited content and links', function () {
    $post = createOwnedNote($this);

    $this->patchJson("/bulletin/posts/{$post->public_id}", [
        'content' => '',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['content']);

    $this->patchJson("/bulletin/posts/{$post->public_id}", [
        'content' => 'Still here.',
        'link_url' => 'javascript:alert(1)',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['link_url']);
});

it('does not allow edits on expired or archived notes', function () {
    $expired = BulletinPost::factory()->expired()->create();
    $archived = BulletinPost::factory()->archived()->create();

    $this->patchJson("/bulletin/posts/{$expired->public_id}", [
        'content' => 'Expired edit.',
    ])->assertNotFound();

    $this->patchJson("/bulletin/posts/{$archived->public_id}", [
        'content' => 'Archived edit.',
    ])->assertNotFound();
});

it('lets the owner fully delete a note, its reactions, reports and media', function () {
    Storage::fake('public');

    $this->post('/bulletin/posts', [
        'content' => 'Delete me completely.',
        'size' => '1x1',
        'media' => UploadedFile::fake()->image('delete-me.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated();

    $post = BulletinPost::sole();
    $path = $post->media_path;
    actAsNoteOwner($post);

    BulletinReaction::query()->create([
        'bulletin_post_id' => $post->id,
        'reaction_type' => 'heart',
        'visitor_hash' => str_repeat('c', 64),
    ]);

    BulletinReport::query()->create([
        'bulletin_post_id' => $post->id,
        'reason' => 'spam',
        'visitor_hash' => str_repeat('d', 64),
    ]);

    $this->deleteJson("/bulletin/posts/{$post->public_id}")
        ->assertOk()
        ->assertJsonPath('data.id', $post->public_id);

    expect(BulletinPost::count())->toBe(0)
        ->and(BulletinReaction::count())->toBe(0)
        ->and(BulletinReport::count())->toBe(0);

    Storage::disk('public')->assertMissing($path);
});

it('rejects a delete from a visitor who did not pin the note', function () {
    $post = createOwnedNote($this);

    $visitor = mock(BulletinVisitor::class);
    $visitor->shouldReceive('hash')->andReturn(str_repeat('f', 64));
    $visitor->shouldReceive('ipHash')->andReturn(str_repeat('b', 64));

    $this->deleteJson("/bulletin/posts/{$post->public_id}")
        ->assertForbidden()
        ->assertJsonPath('message', 'Only the visitor who pinned this note can delete it.');

    expect(BulletinPost::count())->toBe(1);
});

it('marks only the owner notes as editable and deletable', function () {
    $own = createOwnedNote($this, ['content' => 'My own note.']);
    $other = BulletinPost::factory()->create([
        'content' => 'Someone else note.',
        'created_at' => now()->subMinute(),
    ]);
    actAsNoteOwner($own);

    $payload = collect($this->getJson('/bulletin/posts')->assertOk()->json('data'))
        ->keyBy('id');

    expect($payload[$own->public_id]['can_edit'])->toBeTrue()
        ->and($payload[$own->public_id]['can_delete'])->toBeTrue()
        ->and($payload[$other->public_id]['can_edit'])->toBeFalse()
        ->and($payload[$other->public_id]['can_delete'])->toBeFalse();
});

it('keeps delete available after the edit window closes', function () {
    $post = createOwnedNote($this);
    $post->forceFill(['created_at' => now()->subDays(2)])->save();
    actAsNoteOwner($post);

    $payload = collect($this->getJson('/bulletin/posts')->assertOk()->json('data'))
        ->firstWhere('id', $post->public_id);

    expect($payload['can_edit'])->toBeFalse()
        ->and($payload['can_delete'])->toBeTrue();
});

it('defines an edit rate limit per visitor and per IP', function () {
    $callback = RateLimiter::limiter('bulletin-edits');

    expect($callback)->not->toBeNull();

    $request = Request::create('/bulletin/posts/01ABC', 'PATCH');
    $request->setLaravelSession(
        new Store(
            'bulletin_test',
            new ArraySessionHandler(120),
            'bulletin-edit-session'
        )
    );

    $limits = $callback($request);

    expect($limits)->toHaveCount(2)
        ->and($limits[0]->maxAttempts)->toBe(20)
        ->and($limits[0]->decaySeconds)->toBe(60)
        ->and($limits[0]->key)->toStartWith('bulletin-edit:visitor:')
        ->and($limits[1]->maxAttempts)->toBe(60)
        ->and($limits[1]->decaySeconds)->toBe(60)
        ->and($limits[1]->key)->toStartWith('bulletin-edit:ip:');
});
