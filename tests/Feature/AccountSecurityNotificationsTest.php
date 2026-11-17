<?php

use App\Enums\NotificationType;
use App\Mail\SecurityAlert;
use App\Models\Notification;
use App\Models\RememberedDevice;
use App\Models\User;
use App\Models\UserPreferences;
use App\Services\Notifications\NotificationPresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

const DESKTOP_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';

function securityNotification(NotificationType $type): ?Notification
{
    return Notification::query()->where('type', $type->value)->first();
}

it('notifies the owner about a new sign-in with the device and address', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->withHeader('User-Agent', DESKTOP_AGENT)
        ->post('/login', [
            'username' => $user->username,
            'password' => 'password',
        ]);

    $notification = securityNotification(NotificationType::SignedIn);

    expect($notification)->not->toBeNull()
        ->and($notification->user_id)->toBe($user->id);

    $payload = NotificationPresenter::toArray($notification);

    expect($payload['title'])->toBe('New sign-in to your account')
        ->and($payload['body'])->toContain('Windows · Chrome')
        ->and($payload['body'])->toContain('IP 127.0.0.1');
});

it('notifies the owner when their account signs out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout');

    expect(securityNotification(NotificationType::SignedOut))->not->toBeNull();
});

it('only alerts about failed sign-ins once the same address keeps trying', function () {
    $user = User::factory()->create();

    foreach (range(1, 2) as $ignored) {
        $this->post('/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);
    }

    expect(securityNotification(NotificationType::FailedSignIn))->toBeNull();

    $this->post('/login', [
        'username' => $user->username,
        'password' => 'wrong-password',
    ]);

    $notification = securityNotification(NotificationType::FailedSignIn);

    expect($notification)->not->toBeNull()
        ->and(NotificationPresenter::toArray($notification)['body'])
        ->toContain('3 failed attempts in the last hour');
});

it('notifies the owner when the password changes', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->actingAs($user)
        ->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    expect(securityNotification(NotificationType::PasswordChanged))->not->toBeNull()
        ->and(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

it('notifies the owner only when the sign-in email actually changes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put('/settings/profile', [
            'dname' => $user->dname,
            'email' => $user->email,
        ]);

    expect(securityNotification(NotificationType::EmailChanged))->toBeNull();

    $this->actingAs($user)
        ->put('/settings/profile', [
            'dname' => $user->dname,
            'email' => 'moved@example.com',
        ]);

    $notification = securityNotification(NotificationType::EmailChanged);

    expect($notification)->not->toBeNull()
        ->and(NotificationPresenter::toArray($notification)['body'])
        ->toContain('moved@example.com');
});

it('notifies the owner when they remove a device', function () {
    $user = User::factory()->create();

    $device = RememberedDevice::create([
        'user_id' => $user->id,
        'token' => hash('sha256', 'device-token'),
        'ip' => '127.0.0.1',
        'user_agent' => DESKTOP_AGENT,
        'last_used_at' => now(),
    ]);

    $this->actingAs($user)
        ->delete("/settings/devices/{$device->id}/revoke/device");

    expect(securityNotification(NotificationType::DeviceRemoved))->not->toBeNull()
        ->and(RememberedDevice::find($device->id))->toBeNull();
});

it('notifies the owner when they revoke a session', function () {
    $user = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => 'session-one',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => DESKTOP_AGENT,
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($user)
        ->delete('/settings/devices/session-one/revoke/session');

    expect(securityNotification(NotificationType::SessionRevoked))->not->toBeNull();
});

it('respects the account notification preference', function () {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $user->preferences()->create(array_merge(UserPreferences::defaults(), [
        'notify_account' => false,
    ]));

    $this->actingAs($user)
        ->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    expect(securityNotification(NotificationType::PasswordChanged))->toBeNull();
});

it('queues a security email only when the mailer switch is on', function () {
    Mail::fake();

    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->actingAs($user)
        ->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'first-change',
            'password_confirmation' => 'first-change',
        ]);

    Mail::assertNothingQueued();

    config(['mail.security_alerts.enabled' => true]);

    $this->actingAs($user)
        ->put('/settings/password', [
            'current_password' => 'first-change',
            'password' => 'second-change',
            'password_confirmation' => 'second-change',
        ]);

    Mail::assertQueued(SecurityAlert::class, function (SecurityAlert $mail) use ($user) {
        return $mail->hasTo($user->email)
            && $mail->headline === 'Your password was changed';
    });
});
