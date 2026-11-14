<?php

use App\Models\Bleep;
use App\Models\Comments;
use App\Models\RememberedDevice;
use App\Models\User;
use App\Models\Visits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('redirects guests from admin routes', function () {
    $this->get('/admin/dashboard')
        ->assertRedirect(route('login'));
});

it('forbids regular users from admin routes', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/dashboard')
        ->assertForbidden();
});

it('allows admins to view the dashboard', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/dashboard')
        ->assertOk();
});

it('allows moderators to view the dashboard', function () {
    $moderator = User::factory()->create(['role' => 'moderator']);

    $this->actingAs($moderator)
        ->get('/admin/dashboard')
        ->assertOk();
});

it('shows a unique ip table on the site visits page', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Visits::create([
        'ip_address' => '203.0.113.7',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'browser' => 'Chrome',
        'platform' => 'Windows',
    ]);
    Visits::create([
        'ip_address' => '203.0.113.7',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'browser' => 'Chrome',
        'platform' => 'Windows',
    ]);
    Visits::create([
        'ip_address' => '198.51.100.9',
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
        'browser' => 'Safari',
        'platform' => 'macOS',
    ]);

    $this->actingAs($admin)
        ->get('/admin/visits')
        ->assertOk()
        ->assertSee('Unique IP Addresses')
        ->assertSee('203.0.113.7')
        ->assertSee('198.51.100.9');
});

it('renders the devices and sessions page with real session and device rows', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $member = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => Str::random(40),
        'user_id' => $member->id,
        'ip_address' => '203.0.113.44',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
        'payload' => base64_encode('payload'),
        'last_activity' => now()->timestamp,
    ]);

    RememberedDevice::create([
        'user_id' => $member->id,
        'token' => hash('sha256', 'qa-device-token'),
        'ip' => '203.0.113.44',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
        'last_used_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get('/admin/devices')
        ->assertOk()
        ->assertSee('Active Sessions')
        ->assertSee('203.0.113.44')
        ->assertSee($member->username);
});

it('renders the reports page when bleep and comment reports are pending', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $reporter = User::factory()->create();
    $author = User::factory()->create();

    $bleep = Bleep::factory()->for($author)->create(['message' => 'Reported bleep body']);
    $bleep->reports()->create([
        'reporter_id' => $reporter->id,
        'category' => 'spam',
        'reason' => 'Looks like spam.',
        'status' => 'pending',
    ]);

    $comment = Comments::factory()->create([
        'bleep_id' => $bleep->id,
        'user_id' => $author->id,
        'message' => 'Reported comment body',
    ]);
    $comment->reports()->create([
        'reporter_id' => $reporter->id,
        'category' => 'harassment',
        'reason' => 'Rude comment.',
        'status' => 'pending',
    ]);

    $this->actingAs($admin)
        ->get('/admin/reports')
        ->assertOk()
        ->assertSee('Reported bleep body')
        ->assertSee('Reported comment body');
});

it('renders the reports page when there are no reports', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/reports')
        ->assertOk();
});

it('renders the bulletin report filter without bleep reports', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->get('/admin/reports?type=bulletin')
        ->assertOk();
});
