<?php

it('renders the custom not found page outside local debugging', function () {
    config(['app.debug' => false]);

    $this->get('/this-route-does-not-exist')
        ->assertNotFound()
        ->assertSee('Page not found')
        ->assertSee('404');
});

it('keeps json error responses intact', function () {
    config(['app.debug' => false]);

    $this->getJson('/this-route-does-not-exist')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

it('ships custom views for common error statuses', function () {
    foreach ([401, 403, 404, 405, 419, 429, 500, 503] as $status) {
        expect(view()->exists("errors.{$status}"))->toBeTrue();
    }

    expect(view()->exists('errors.generic'))->toBeTrue();
});

it('lets the local debug page handle exceptions', function () {
    config(['app.debug' => true]);
    $this->app->detectEnvironment(fn () => 'local');

    $response = $this->get('/this-route-does-not-exist');

    $response->assertNotFound();

    expect($response->getContent())->toContain('laravel-exceptions-renderer');
});
