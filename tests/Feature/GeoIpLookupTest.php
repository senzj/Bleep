<?php

use App\Models\Logs;
use App\Models\User;
use App\Models\Visits;
use App\Services\GeoIpService;

function fakeGeoIpResolver(): Closure
{
    return fn (string $ip): ?array => $ip === '8.8.8.8'
        ? [
            'country' => 'Philippines',
            'country_code' => 'PH',
            'region' => 'Metro Manila',
            'city' => 'Manila',
            'latitude' => 14.6,
            'longitude' => 120.98,
            'timezone' => 'Asia/Manila',
            'label' => 'Manila, Metro Manila, Philippines',
            'flag' => 'PH',
        ]
        : null;
}

it('never looks up private, reserved or malformed addresses', function () {
    $service = new GeoIpService(resolver: fn (string $ip): array => ['label' => 'Should not resolve']);

    expect($service->locate('127.0.0.1'))->toBeNull()
        ->and($service->locate('10.1.2.3'))->toBeNull()
        ->and($service->locate('192.168.1.10'))->toBeNull()
        ->and($service->locate('::1'))->toBeNull()
        ->and($service->locate('not-an-ip'))->toBeNull()
        ->and($service->locate(null))->toBeNull();
});

it('returns null when no database is installed', function () {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'bleep-geoip-empty-'.uniqid();
    config(['geoip.database' => $directory.DIRECTORY_SEPARATOR.'missing.mmdb']);

    expect((new GeoIpService)->locate('8.8.8.8'))->toBeNull();
});

it('can be switched off entirely', function () {
    config(['geoip.enabled' => false]);

    $service = new GeoIpService(resolver: fn (string $ip): array => ['label' => 'Should not resolve']);

    expect($service->locate('8.8.8.8'))->toBeNull();
});

it('resolves a public address through the reader', function () {
    $service = new GeoIpService(resolver: fakeGeoIpResolver());

    expect($service->locate('8.8.8.8'))
        ->toMatchArray([
            'country' => 'Philippines',
            'city' => 'Manila',
            'label' => 'Manila, Metro Manila, Philippines',
        ])
        ->and($service->locate('1.1.1.1'))->toBeNull();
});

it('shows the location beside activity log IPs', function () {
    $this->app->instance(GeoIpService::class, new GeoIpService(resolver: fakeGeoIpResolver()));

    $user = User::factory()->create();

    Logs::create([
        'user_id' => $user->id,
        'action' => 'login',
        'ip' => '8.8.8.8',
        'user_agent' => 'Pest',
    ]);

    $this->actingAs($user)
        ->get(route('settings.logs'))
        ->assertOk()
        ->assertSee('Manila, Metro Manila, Philippines');

    $this->actingAs($user)
        ->get(route('settings.devices'))
        ->assertOk();
});

it('shows locations and the country breakdown on the admin visits page', function () {
    $this->app->instance(GeoIpService::class, new GeoIpService(resolver: fakeGeoIpResolver()));

    $admin = User::factory()->create(['role' => 'admin']);

    Visits::create([
        'user_id' => null,
        'ip_address' => '8.8.8.8',
        'user_agent' => 'Pest',
        'browser' => 'Chrome',
        'device' => 'Desktop',
        'platform' => 'Windows',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.visits'))
        ->assertOk()
        ->assertSee('Top Visitor Locations')
        ->assertSee('Manila, Metro Manila, Philippines')
        ->assertSee('Philippines');
});
