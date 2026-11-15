<?php

use Illuminate\Support\Facades\Http;

it('requests the database for the selected month', function () {
    Http::fake(['*' => Http::response('unavailable', 500)]);

    $this->artisan('geoip:update', ['--month' => '2026-11', '--force' => true])
        ->assertExitCode(1);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'dbip-city-lite-2026-11.mmdb.gz'));
});

it('keeps the installed database when the download is not a valid archive', function () {
    $target = sys_get_temp_dir().DIRECTORY_SEPARATOR.'bleep-geoip-'.uniqid().'.mmdb';
    file_put_contents($target, 'existing database');

    config(['geoip.database' => $target]);
    Http::fake(['*' => Http::response(str_repeat('not a gzip archive', 200), 200)]);

    $this->artisan('geoip:update', ['--month' => '2026-11', '--force' => true])
        ->assertExitCode(1);

    expect(file_get_contents($target))->toBe('existing database');

    @unlink($target);
});

it('rejects an invalid month', function () {
    $this->artisan('geoip:update', ['--month' => '2026-13'])
        ->assertExitCode(1);
});
