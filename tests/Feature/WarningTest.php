<?php

use App\Enums\NotificationType;
use App\Models\Bleep;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserWarning;
use App\Services\Moderation\WarningService;
use Illuminate\Support\Facades\Event;

function warningReportFixture(): array
{
    $reporter = User::factory()->create();
    $author = User::factory()->create();
    $bleep = Bleep::factory()->for($author)->create(['message' => 'Reported bleep body']);

    $report = $bleep->reports()->create([
        'reporter_id' => $reporter->id,
        'category' => 'spam',
        'reason' => 'Spam.',
        'status' => 'pending',
    ]);

    return [$report, $reporter, $author];
}

function activeWarning(User $user, array $attributes = []): UserWarning
{
    return $user->warnings()->create(array_merge([
        'reason' => 'spam',
        'expires_at' => now()->addDays(7),
    ], $attributes));
}

beforeEach(function () {
    Event::fake();
});

it('forbids regular users from warning and revoking', function () {
    [$report, , $author] = warningReportFixture();
    $warning = activeWarning($author);
    $member = User::factory()->create();

    $this->actingAs($member)
        ->postJson("/admin/reports/{$report->id}/warn", ['reason' => 'spam'])
        ->assertForbidden();

    $this->actingAs($member)
        ->postJson("/admin/warnings/{$warning->id}/revoke")
        ->assertForbidden();

    expect($author->fresh()->report_strikes)->toBe(0)
        ->and($warning->fresh()->revoked_at)->toBeNull();
});

it('issues a warning from a report and resolves it', function () {
    [$report, $reporter, $author] = warningReportFixture();
    $admin = User::factory()->create(['role' => 'admin']);
    $otherAdmin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->postJson("/admin/reports/{$report->id}/warn", [
            'reason' => 'harassment',
            'notes' => 'Second warning this week.',
        ])
        ->assertOk()
        ->assertJsonPath('banned', false)
        ->assertJsonPath('strikes', 1);

    $warning = UserWarning::sole();

    expect($warning->user_id)->toBe($author->id)
        ->and($warning->moderator_id)->toBe($admin->id)
        ->and($warning->report_id)->toBe($report->id)
        ->and($warning->reason)->toBe('harassment')
        ->and($warning->expires_at)->not->toBeNull();

    expect($report->fresh()->status)->toBe('resolved')
        ->and($report->fresh()->action_taken)->toBe('warned')
        ->and($author->fresh()->report_strikes)->toBe(1)
        ->and($author->fresh()->last_strike_at)->not->toBeNull();

    expect(Notification::where('user_id', $author->id)->where('type', NotificationType::WarningIssued)->exists())->toBeTrue()
        ->and(Notification::where('user_id', $otherAdmin->id)->where('type', NotificationType::AdminWarningIssued)->exists())->toBeTrue()
        ->and(Notification::where('user_id', $admin->id)->where('type', NotificationType::AdminWarningIssued)->exists())->toBeFalse();

    $reporterRow = Notification::where('user_id', $reporter->id)->where('type', NotificationType::ReportResolved)->sole();

    expect($reporterRow->data['outcome'])->toBe('action_taken')
        ->and($reporterRow->data)->not->toHaveKey('notes');
});

it('auto-bans when active strikes reach the threshold', function () {
    [$report, , $author] = warningReportFixture();
    $admin = User::factory()->create(['role' => 'admin']);

    activeWarning($author);
    activeWarning($author);

    $this->actingAs($admin)
        ->postJson("/admin/reports/{$report->id}/warn", ['reason' => 'hate'])
        ->assertOk()
        ->assertJsonPath('banned', true)
        ->assertJsonPath('strikes', 3);

    $author->refresh();

    expect($author->is_banned)->toBeTrue()
        ->and($author->banned_until)->not->toBeNull()
        ->and($author->suspension_count)->toBe(1)
        ->and($author->report_strikes)->toBe(3)
        ->and(Notification::where('user_id', $author->id)->where('type', NotificationType::AccountBanned)->exists())->toBeTrue();
});

it('does not auto-ban when the ladder is disabled', function () {
    config(['moderation.auto_ban_at_strikes' => 0]);

    [$report, , $author] = warningReportFixture();
    $admin = User::factory()->create(['role' => 'admin']);

    activeWarning($author);
    activeWarning($author);

    $this->actingAs($admin)
        ->postJson("/admin/reports/{$report->id}/warn", ['reason' => 'spam'])
        ->assertOk()
        ->assertJsonPath('banned', false);

    expect($author->fresh()->is_banned)->toBeFalse();
});

it('ignores expired warnings when counting strikes', function () {
    $user = User::factory()->create();

    activeWarning($user);
    activeWarning($user, ['expires_at' => now()->subDay()]);

    $strikes = app(WarningService::class)->syncStrikes($user);

    expect($strikes)->toBe(1)
        ->and($user->fresh()->report_strikes)->toBe(1)
        ->and($user->warnings()->active()->count())->toBe(1);
});

it('recomputes strikes when a warning is revoked', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create();
    $warning = activeWarning($user);

    app(WarningService::class)->syncStrikes($user);
    expect($user->fresh()->report_strikes)->toBe(1);

    $this->actingAs($admin)
        ->postJson("/admin/warnings/{$warning->id}/revoke")
        ->assertOk()
        ->assertJsonPath('active_count', 0);

    expect($warning->fresh()->revoked_at)->not->toBeNull()
        ->and($warning->fresh()->revoked_by)->toBe($admin->id)
        ->and($user->fresh()->report_strikes)->toBe(0);
});

it('returns warning history with active counts', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create();
    $warning = activeWarning($user, [
        'moderator_id' => $admin->id,
        'notes' => 'Keep it civil.',
    ]);

    $this->actingAs($admin)
        ->getJson("/admin/users/{$user->id}/warnings")
        ->assertOk()
        ->assertJsonPath('active_count', 1)
        ->assertJsonPath('warnings.0.id', $warning->id)
        ->assertJsonPath('warnings.0.reason', 'spam')
        ->assertJsonPath('warnings.0.active', true);
});

it('rejects unknown warning reasons', function () {
    [$report] = warningReportFixture();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->postJson("/admin/reports/{$report->id}/warn", ['reason' => 'not-a-reason'])
        ->assertUnprocessable();
});

it('returns not found when the reported content no longer exists', function () {
    [$report] = warningReportFixture();
    $admin = User::factory()->create(['role' => 'admin']);

    $report->reportable->delete();

    $this->actingAs($admin)
        ->postJson("/admin/reports/{$report->id}/warn", ['reason' => 'spam'])
        ->assertNotFound();
});
