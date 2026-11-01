<?php

use App\Services\Bulletin\BulletinVisitor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\mock;

beforeEach(function () {
    Cache::flush();
});

afterEach(function () {
    Cache::flush();
});

/**
 * Bind the visitor to a fixed fingerprint so the rename limits can be tested
 * without relying on a test client reusing the same session cookie.
 */
function mockRenamingVisitor(string ...$names): void
{
    $visitor = mock(BulletinVisitor::class);
    $visitor->shouldReceive('hash')->andReturn(str_repeat('a', 64));
    $visitor->shouldReceive('ipHash')->andReturn(str_repeat('b', 64));
    $visitor->shouldReceive('identity')->andReturn('Bravemoth512');

    $expectation = $visitor->shouldReceive('rotateIdentity');

    if ($names !== []) {
        $expectation->andReturn(...$names);
    } else {
        $expectation->andReturn('Bravemoth512');
    }
}

it('shows the name change allowance on the board', function () {
    $this->get('/bulletin')
        ->assertOk()
        ->assertSee('data-endpoint-identity', false)
        ->assertSee('data-name-changes-left="3"', false)
        ->assertSee('data-name-cooldown="0"', false);
});

it('gives the visitor a new name and starts the cooldown', function () {
    mockRenamingVisitor('Bravemoth512');

    $this->postJson('/bulletin/identity')
        ->assertOk()
        ->assertJsonPath('data.identity', 'Bravemoth512')
        ->assertJsonPath('data.changes_left', 2)
        ->assertJsonPath('data.retry_after', 300)
        ->assertJsonPath('message', 'Your name is now Bravemoth512. New notes will use it.');
});

it('rotates the session identity for new notes only', function () {
    $this->withSession(['bulletin_identity' => 'Oldname123']);

    $response = $this->postJson('/bulletin/identity')->assertOk();
    $identity = $response->json('data.identity');

    expect($identity)
        ->toMatch('/^[A-Z][a-z]{2,}[A-Z][a-z]{2,}\d{1,3}$/')
        ->not->toBe('Oldname123');
});

it('enforces a cooldown between name changes', function () {
    mockRenamingVisitor('Bravemoth512');

    $this->postJson('/bulletin/identity')->assertOk();

    $response = $this->postJson('/bulletin/identity')->assertStatus(429);

    expect($response->json('retry_after'))->toBeGreaterThan(0)->toBeLessThanOrEqual(300)
        ->and($response->json('changes_left'))->toBe(2)
        ->and($response->json('message'))
        ->toStartWith('You can change your name again in');
});

it('limits name changes to three a day', function () {
    mockRenamingVisitor('Nameone111', 'Nametwo222', 'Namethree333');

    $cooldownKey = 'bulletin-name-cooldown:'.str_repeat('a', 64);

    foreach (['Nameone111', 'Nametwo222', 'Namethree333'] as $name) {
        RateLimiter::clear($cooldownKey);

        $this->postJson('/bulletin/identity')
            ->assertOk()
            ->assertJsonPath('data.identity', $name);
    }

    RateLimiter::clear($cooldownKey);

    $response = $this->postJson('/bulletin/identity')->assertStatus(429);

    expect($response->json('changes_left'))->toBe(0)
        ->and($response->json('message'))->toContain('3 times a day');
});

it('keeps the board visit working while a name change is on cooldown', function () {
    mockRenamingVisitor('Bravemoth512');

    $this->postJson('/bulletin/identity')->assertOk();

    $this->get('/bulletin')
        ->assertOk()
        ->assertSee('data-name-changes-left="2"', false);
});
