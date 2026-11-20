<?php

use App\Models\Bleep;
use App\Models\User;
use App\Models\UserPreferences;

it('renders the compact navbar hook and the notifications panel for signed-in users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee('id="app-navbar"', false)
        ->assertDontSee('id="left-panel"', false)
        ->assertDontSee('aria-controls="left-panel-content"', false)
        ->assertSee('id="right-panel"', false)
        ->assertSee('aria-controls="right-panel-content"', false);
});

it('keeps announcements and system updates out of the guest feed', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee('id="left-panel"', false)
        ->assertDontSee('aria-controls="left-panel-content"', false)
        ->assertDontSee('data-announcement', false);
});

it('does not render the notifications side panel for guests', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee('id="right-panel"', false);
});

it('keeps the home links enabled and marked current on the home page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee('href="'.route('home').'"', false)
        ->assertSee('aria-current="page"', false)
        ->assertSee('<a href="'.route('home').'" class="nav-link flex items-center gap-3 px-3 py-2 rounded-sm bg-primary text-primary-content" aria-current="page">', false);
});

it('links back to the home feed from the header on other pages', function () {
    $this->get('/login')
        ->assertSuccessful()
        ->assertSee('href="'.route('home').'"', false);
});

it('keeps the chat tab highlighted on chat sub pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/chat/1')
        ->assertSuccessful()
        ->assertSee('class="nav-link relative flex items-center gap-3 px-3 py-2 rounded-sm bg-primary text-primary-content" aria-current="page"', false);
});

it('keeps the home tab highlighted on a bleep page', function () {
    $user = User::factory()->create();
    $bleep = Bleep::factory()->create([
        'message' => 'Navigation highlight bleep',
        'is_anonymous' => false,
    ]);

    $this->actingAs($user)
        ->get(route('post', $bleep->id))
        ->assertSuccessful()
        ->assertSee('class="nav-link flex items-center gap-3 px-3 py-2 rounded-sm bg-primary text-primary-content" aria-current="page"', false);
});

it('offers guests a way into the public bulletin board', function () {
    $this->get('/login')
        ->assertSuccessful()
        ->assertSee(route('bulletin.index'), false)
        ->assertSee('Read the bulletin board');
});

it('renders exactly one bottom-right mobile menu button in the horizontal layout', function () {
    $user = User::factory()->create();

    $body = $this->actingAs($user)->get('/')->assertSuccessful()->getContent();

    expect(substr_count($body, 'id="mobile-menu-btn"'))->toBe(1)
        ->and($body)->not->toContain('id="hor-mobile-menu-btn"')
        ->and($body)->not->toContain('id="compact-mobile-menu-btn"')
        ->and($body)->not->toContain('compact-nav__fab')
        ->and($body)->not->toContain('id="activity-drawer-open"')
        ->and($body)->toContain('data-nav-fab-position="bottom-right"')
        ->and($body)->toContain('nav-fab--bottom-right');
});

it('uses the floating menu button by default on phones', function () {
    $user = User::factory()->create();

    $body = $this->actingAs($user)->get('/')->assertSuccessful()->getContent();

    expect($body)->toContain('data-nav-mobile-mode="fab"')
        ->and($body)->toContain('id="mobile-menu-btn"')
        ->and($body)->not->toContain('id="mobile-nav-strip"');
});

it('renders the bottom navigation bar when the member chooses it', function () {
    $user = User::factory()->create();
    $user->preferences()->create([
        ...UserPreferences::defaults(),
        'nav_mobile_mode' => 'bottom',
    ]);

    $body = $this->actingAs($user)->get('/')->assertSuccessful()->getContent();

    expect($body)->toContain('data-nav-mobile-mode="bottom"')
        ->and($body)->toContain('id="mobile-nav-strip"')
        ->and($body)->toContain('id="mobile-strip-menu-btn"')
        ->and($body)->not->toContain('id="mobile-menu-btn"');
});

it('leaves the quick navigation strip off for guests', function () {
    $this->get('/login')
        ->assertSuccessful()
        ->assertDontSee('id="mobile-nav-strip"', false);
});

it('moves the mobile menu button and drawer to the chosen side', function () {
    $user = User::factory()->create();
    $user->preferences()->create([
        ...UserPreferences::defaults(),
        'nav_fab_position' => 'top-left',
    ]);

    $body = $this->actingAs($user)->get('/')->assertSuccessful()->getContent();

    expect($body)->toContain('data-nav-fab-position="top-left"')
        ->and($body)->toContain('nav-fab--top-left')
        ->and($body)->toContain('data-side="left"')
        ->and($body)->toContain('-translate-x-full');
});

it('opens the mobile drawer from the right when the button is on the right', function () {
    $user = User::factory()->create();
    $user->preferences()->create([
        ...UserPreferences::defaults(),
        'nav_fab_position' => 'bottom-right',
    ]);

    $body = $this->actingAs($user)->get('/')->assertSuccessful()->getContent();

    expect($body)->toContain('data-side="right"')
        ->and($body)->toContain('right-0')
        ->and($body)->toContain('translate-x-full');
});

it('keeps the only mobile menu button in place for the vertical layout', function () {
    $user = User::factory()->create();
    $user->preferences()->create([
        ...UserPreferences::defaults(),
        'nav_layout' => 'vertical',
    ]);

    $body = $this->actingAs($user)->get('/')->assertSuccessful()->getContent();

    expect(substr_count($body, 'id="mobile-menu-btn"'))->toBe(1)
        ->and($body)->not->toContain('id="hor-mobile-menu-btn"')
        ->and($body)->not->toContain('compact-nav__fab')
        ->and($body)->toContain('w-58')
        ->and($body)->toContain('xl:pr-58')
        ->and($body)->toContain('xl:col-start-3')
        ->and($body)->not->toContain('lg:col-start-4');
});

it('keeps the three-column grid for the horizontal layout', function () {
    $user = User::factory()->create();

    $body = $this->actingAs($user)->get('/')->assertSuccessful()->getContent();

    expect($body)->toContain('lg:grid-cols-12')
        ->and($body)->not->toContain('xl:pr-58')
        ->and($body)->toContain('lg:col-span-6 lg:col-start-4 lg:row-start-1')
        ->and($body)->toContain('lg:col-span-3 lg:col-start-10 lg:row-start-1');
});

it('centers the guest feed without an announcement column', function () {
    $body = $this->get('/')->assertSuccessful()->getContent();

    expect($body)->toContain('lg:col-span-6 lg:col-start-4 lg:row-start-1')
        ->and($body)->not->toContain('lg:col-span-3 lg:col-start-1 lg:row-start-1');
});

it('gives chat the whole viewport without app-level mobile navigation', function () {
    $user = User::factory()->create();

    foreach ([route('messages'), route('chat.conversation', 1)] as $url) {
        $body = $this->actingAs($user)
            ->get($url)
            ->assertSuccessful()
            ->getContent();

        expect($body)->toContain('data-chat-page="1"')
            ->and($body)->toContain('data-home-url="'.route('home').'"')
            ->and($body)->not->toContain('id="mobile-menu-btn"')
            ->and($body)->not->toContain('id="mobile-nav-strip"')
            ->and($body)->not->toContain('id="hor-mobile-drawer"');
    }
});

it('moves notifications into the mobile slide-over menu', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee('id="hor-mobile-drawer"', false)
        ->assertSee('id="mobile-menu-btn"', false)
        ->assertSee('href="'.route('notifications.index').'"', false)
        ->assertSee('nav-notification-unread-badge', false);
});

it('hides the chat navigation when the feature is disabled', function () {
    config(['features.chat' => false]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertDontSee('data-nav-chat-badge', false)
        ->assertDontSee('href="'.route('messages').'"', false)
        ->assertSee('href="'.route('social.people').'"', false);
});

it('shows the chat navigation when the feature is enabled', function () {
    config(['features.chat' => true]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee('data-nav-chat-badge', false);
});
