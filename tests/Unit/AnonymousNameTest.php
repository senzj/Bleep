<?php

use App\Helpers\AnonymousName;
use App\Traits\HasAnonymousName;

it('generates short anonymous display names with 1 to 3 digits', function () {
    for ($i = 0; $i < 50; $i++) {
        $name = AnonymousName::randomDisplayName();

        expect($name)->toMatch('/^[A-Z][a-z]{2,}[A-Z][a-z]{2,}\d{1,3}$/')
            ->and(strlen($name))->toBeLessThanOrEqual(32);
    }
});

it('uses the shared display name for bulletin identities', function () {
    for ($i = 0; $i < 20; $i++) {
        expect(AnonymousName::randomBulletinIdentity())
            ->toMatch('/^[A-Z][a-z]{2,}[A-Z][a-z]{2,}\d{1,3}$/');
    }
});

it('generates lowercase usernames from the shared word pools', function () {
    $model = new class
    {
        use HasAnonymousName;
    };

    for ($i = 0; $i < 20; $i++) {
        expect($model::generateRandomUsername())
            ->toMatch('/^[a-z]{2,}\d{1,3}$/');
    }
});
