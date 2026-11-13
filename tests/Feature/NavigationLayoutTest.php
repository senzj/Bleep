<?php

use App\Models\User;

it('renders the compact navbar hook and collapsible mobile side panels for signed-in users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee('id="app-navbar"', false)
        ->assertSee('id="left-panel"', false)
        ->assertSee('aria-controls="left-panel-content"', false)
        ->assertSee('id="right-panel"', false)
        ->assertSee('aria-controls="right-panel-content"', false);
});

it('does not render the notifications side panel for guests', function () {
    $this->get('/')
        ->assertSuccessful()
        ->assertDontSee('id="right-panel"', false);
});

it('does not link back to the home feed from the header on the home page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertSuccessful()
        ->assertSee('aria-current="page"', false)
        ->assertDontSee('href="'.route('home').'"', false);
});

it('links back to the home feed from the header on other pages', function () {
    $this->get('/login')
        ->assertSuccessful()
        ->assertSee('href="'.route('home').'"', false);
});

it('offers guests a way into the public bulletin board', function () {
    $this->get('/login')
        ->assertSuccessful()
        ->assertSee(route('bulletin.index'), false)
        ->assertSee('Read the bulletin board');
});
