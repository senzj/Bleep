<?php

it('shows the credits page with the maintainer and project link', function () {
    $this->get('/system-credits')
        ->assertSuccessful()
        ->assertSee('System credits')
        ->assertSee('senzj')
        ->assertSee('https://github.com/senzj', false)
        ->assertSee('https://github.com/senzj/Bleep', false);
});

it('explains the purpose of the project', function () {
    $this->get('/system-credits')
        ->assertSuccessful()
        ->assertSee('A place to learn Laravel')
        ->assertSee('A Chirper continuation')
        ->assertSee('Learning AI in practice')
        ->assertSee('A space for friends and family')
        ->assertSee('A smoother experience');
});

it('keeps the credits page out of search results', function () {
    $this->get('/system-credits')
        ->assertSuccessful()
        ->assertSee('noindex, nofollow');
});
