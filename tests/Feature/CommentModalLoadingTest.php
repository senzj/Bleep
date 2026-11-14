<?php

use Illuminate\Support\Facades\Blade;

it('renders one loading indicator for the comment modal', function () {
    $modal = Blade::render('<x-subcomponents.comments.layout layoutmode="modal" />');

    expect($modal)
        ->toContain('comments-container-layout-modal')
        ->not->toContain('loading-spinner');
});

it('keeps the loading placeholder on the full comment page', function () {
    $post = Blade::render('<x-subcomponents.comments.layout layoutmode="post" />');

    expect($post)->toContain('loading-spinner');
});
