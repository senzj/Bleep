<?php

use App\Models\Bleep;
use App\Models\Share;
use App\Models\User;

it('creates one share row per user per day and returns a short link', function () {
    $author = User::factory()->create();
    $sharer = User::factory()->create();
    $bleep = Bleep::factory()->for($author)->create(['is_anonymous' => false]);

    $first = $this->actingAs($sharer)
        ->postJson("/bleeps/{$bleep->id}/share")
        ->assertOk();

    $second = $this->actingAs($sharer)
        ->postJson("/bleeps/{$bleep->id}/share")
        ->assertOk();

    expect(Share::count())->toBe(1)
        ->and($first->json('shares_count'))->toBe(1)
        ->and($second->json('shares_count'))->toBe(1)
        ->and($second->json('share_url'))->toBe($first->json('share_url'))
        ->and($second->json('share_url'))->toMatch('#/s/[A-Za-z0-9]{8}$#');
});

it('reflects the share count in the bleep footer', function () {
    $author = User::factory()->create();
    $sharer = User::factory()->create();
    $bleep = Bleep::factory()->for($author)->create(['is_anonymous' => false]);

    $this->actingAs($sharer)
        ->postJson("/bleeps/{$bleep->id}/share")
        ->assertOk();

    $response = $this->actingAs($sharer)
        ->get("/bleeps/{$bleep->id}")
        ->assertOk();

    $response->assertSee('share-count', false)
        ->assertSee('share-text', false)
        ->assertSee('1 Share');

    expect(substr_count($response->getContent(), 'id="share-modal"'))->toBe(1);
});

it('renders the share modal only once on a feed with multiple bleeps', function () {
    Bleep::factory()->count(3)->create(['is_anonymous' => false]);

    $content = $this->get('/')
        ->assertOk()
        ->getContent();

    expect(substr_count($content, 'id="share-modal"'))->toBe(1);
});

it('renders the share modal only once on a profile page', function () {
    $author = User::factory()->create();
    Bleep::factory()->count(2)->for($author)->create(['is_anonymous' => false]);

    $content = $this->get("/bleeper/{$author->username}")
        ->assertOk()
        ->getContent();

    expect(substr_count($content, 'id="share-modal"'))->toBe(1);
});

it('renders open graph and canonical metadata for a public bleep', function () {
    $author = User::factory()->create(['dname' => 'Nova Star']);
    $bleep = Bleep::factory()->for($author)->create([
        'message' => 'Unfurl this bleep',
        'is_anonymous' => false,
        'is_nsfw' => false,
    ]);

    $url = route('post', $bleep->id);

    $this->get("/bleeps/{$bleep->id}")
        ->assertOk()
        ->assertSee('rel="canonical" href="'.$url.'"', false)
        ->assertSee('property="og:type" content="article"', false)
        ->assertSee('property="og:title" content="Nova Star on Bleep"', false)
        ->assertSee('property="og:description" content="Unfurl this bleep"', false)
        ->assertSee('property="og:url" content="'.$url.'"', false)
        ->assertSee('name="twitter:card" content="summary"', false)
        ->assertSee('name="twitter:title" content="Nova Star on Bleep"', false);
});

it('never leaks an anonymous author through unfurl metadata', function () {
    $author = User::factory()->create(['dname' => 'Secret Person']);
    $bleep = Bleep::factory()->for($author)->anonymous()->create([
        'message' => 'Anonymous thought',
        'is_nsfw' => false,
    ]);

    $this->get("/bleeps/{$bleep->id}")
        ->assertOk()
        ->assertSee('property="og:title" content="Anonymous on Bleep"', false)
        ->assertDontSee('Secret Person')
        ->assertDontSee('@'.$author->username);
});

it('uses the first image as the open graph image for safe bleeps', function () {
    $author = User::factory()->create();
    $bleep = Bleep::factory()->for($author)->create([
        'message' => 'Picture bleep',
        'is_anonymous' => false,
        'is_nsfw' => false,
    ]);

    $bleep->media()->create([
        'path' => 'bleeps/first.jpg',
        'type' => 'image',
    ]);

    $this->get("/bleeps/{$bleep->id}")
        ->assertOk()
        ->assertSee('property="og:image" content="'.asset('storage/bleeps/first.jpg').'"', false)
        ->assertSee('name="twitter:card" content="summary_large_image"', false)
        ->assertSee('name="twitter:image" content="'.asset('storage/bleeps/first.jpg').'"', false);
});

it('omits open graph images for NSFW bleeps', function () {
    $author = User::factory()->create();
    $bleep = Bleep::factory()->for($author)->nsfw()->create([
        'message' => 'Sensitive bleep',
        'is_anonymous' => false,
    ]);

    $bleep->media()->create([
        'path' => 'bleeps/nsfw.jpg',
        'type' => 'image',
    ]);

    $this->get("/bleeps/{$bleep->id}")
        ->assertOk()
        ->assertDontSee('property="og:image"', false)
        ->assertDontSee('name="twitter:image"', false)
        ->assertSee('name="twitter:card" content="summary"', false);
});
