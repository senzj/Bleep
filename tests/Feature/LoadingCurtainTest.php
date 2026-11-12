<?php

it('shows the loading curtain on the login page', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('data-loading-curtain', false)
        ->assertSee('data-loading-curtain-trigger="Signing in"', false)
        ->assertSee('data-curtain-status', false)
        ->assertSee('data-state="hidden"', false);
});

it('shows the loading curtain on the register page', function () {
    $this->get('/register')
        ->assertOk()
        ->assertSee('data-loading-curtain', false)
        ->assertSee('data-loading-curtain-trigger="Creating account"', false)
        ->assertSee('data-curtain-status', false);
});

it('shows the loading curtain on the home feed', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('data-loading-curtain', false)
        ->assertSee('data-curtain-status', false)
        ->assertSee('data-statuses=', false);
});
