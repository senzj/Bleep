<?php

it('renders the media viewer with an instant-open loader', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('id="media-modal-loader"', false)
        ->assertSee('id="media-modal-image"', false)
        ->assertSee('id="media-modal-video"', false)
        ->assertDontSee('id="media-modal-video-source"', false);
});

it('renders the image viewer without an empty source attribute', function () {
    $response = $this->get('/')->assertOk();

    expect($response->getContent())
        ->toContain('id="media-modal-image"')
        ->not->toMatch('/id="media-modal-image"[\s\S]{0,120}?src=""/');
});

it('declares the navigation token that cancels stale media loads', function () {
    $source = file_get_contents(resource_path('js/bleep/modals/mediamodal.js'));

    expect($source)->toMatch('/let navigationToken = 0;/');
});
