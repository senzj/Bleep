<?php

use App\Enums\NotificationAudience;
use App\Enums\NotificationType;
use App\Models\Bleep;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Event;

function moderationReportFixture(): array
{
    $reporter = User::factory()->create();
    $author = User::factory()->create();
    $bleep = Bleep::factory()->for($author)->create(['message' => 'Questionable bleep body']);

    $report = $bleep->reports()->create([
        'reporter_id' => $reporter->id,
        'category' => 'spam',
        'reason' => 'Looks like spam.',
        'status' => 'pending',
    ]);

    return [$report, $reporter, $author];
}

beforeEach(function () {
    Event::fake();
});

it('notifies moderators and admins when a report is submitted', function () {
    $reporter = User::factory()->create();
    $author = User::factory()->create();
    $bleep = Bleep::factory()->for($author)->create(['message' => 'Reported body']);
    $moderator = User::factory()->create(['role' => 'moderator']);
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($reporter)
        ->postJson(route('reports.store'), [
            'type' => 'bleep',
            'bleep_id' => $bleep->id,
            'category' => 'spam',
            'reason' => 'Spam link.',
        ])
        ->assertCreated();

    $moderatorRow = Notification::where('user_id', $moderator->id)->sole();
    $adminRow = Notification::where('user_id', $admin->id)->sole();

    expect($moderatorRow->type)->toBe(NotificationType::ReportSubmitted)
        ->and($moderatorRow->audience)->toBe(NotificationAudience::Moderator)
        ->and($moderatorRow->group_key)->toBe('reportqueue:App\Models\Bleep:'.$bleep->id)
        ->and($adminRow->audience)->toBe(NotificationAudience::Admin)
        ->and(Notification::where('user_id', $reporter->id)->exists())->toBeFalse();
});

it('notifies the reporter and other staff when a report is resolved without action', function () {
    [$report, $reporter] = moderationReportFixture();
    $resolver = User::factory()->create(['role' => 'admin']);
    $moderator = User::factory()->create(['role' => 'moderator']);

    $this->actingAs($resolver)
        ->postJson("/admin/reports/{$report->id}/mark-reviewed", [
            'notes' => 'Private staff note.',
        ])
        ->assertOk();

    $reporterRow = Notification::where('user_id', $reporter->id)->sole();

    expect($reporterRow->type)->toBe(NotificationType::ReportResolved)
        ->and($reporterRow->data['outcome'])->toBe('no_action')
        ->and($reporterRow->data)->not->toHaveKey('notes')
        ->and(Notification::where('user_id', $moderator->id)->where('type', NotificationType::ReportReviewed)->exists())->toBeTrue()
        ->and(Notification::where('user_id', $resolver->id)->where('type', NotificationType::ReportReviewed)->exists())->toBeFalse();
});

it('notifies the author and reporters when content is removed', function () {
    [$report, $reporter, $author] = moderationReportFixture();
    $resolver = User::factory()->create(['role' => 'admin']);

    $this->actingAs($resolver)
        ->postJson("/admin/reports/{$report->id}/delete-bleep", [
            'ban_op' => false,
            'notes' => 'Removed for spam.',
        ])
        ->assertOk();

    $authorRow = Notification::where('user_id', $author->id)->sole();
    $reporterRow = Notification::where('user_id', $reporter->id)->sole();

    expect($authorRow->type)->toBe(NotificationType::ContentRemoved)
        ->and($authorRow->data['subject_label'])->toBe('bleep')
        ->and($authorRow->data['reason'])->toBe('Violated community guidelines')
        ->and($reporterRow->type)->toBe(NotificationType::ReportResolved)
        ->and($reporterRow->data['outcome'])->toBe('action_taken');

    expect($author->fresh()->is_banned)->toBeFalse();
});

it('bans the author through the report flow and audits it', function () {
    [$report, , $author] = moderationReportFixture();
    $resolver = User::factory()->create(['role' => 'admin']);
    $otherAdmin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($resolver)
        ->postJson("/admin/reports/{$report->id}/delete-bleep", [
            'ban_op' => true,
            'banned_until' => now()->addDay()->toIso8601String(),
            'notes' => 'Repeated spam.',
        ])
        ->assertOk();

    $author->refresh();

    expect($author->is_banned)->toBeTrue()
        ->and($author->suspension_count)->toBe(1)
        ->and(Notification::where('user_id', $author->id)->where('type', NotificationType::AccountBanned)->exists())->toBeTrue()
        ->and(Notification::where('user_id', $otherAdmin->id)->where('type', NotificationType::AdminBanIssued)->exists())->toBeTrue()
        ->and(Notification::where('user_id', $resolver->id)->where('type', NotificationType::AdminBanIssued)->exists())->toBeFalse();
});

it('bans a reporter who abuses the report system', function () {
    [$report, $reporter] = moderationReportFixture();
    $resolver = User::factory()->create(['role' => 'admin']);

    $this->actingAs($resolver)
        ->postJson("/admin/reports/{$report->id}/ban-reporter", [
            'banned_until' => now()->addWeek()->toIso8601String(),
        ])
        ->assertOk();

    expect($report->fresh()->action_taken)->toBe('reporter_banned')
        ->and($reporter->fresh()->is_banned)->toBeTrue()
        ->and(Notification::where('user_id', $reporter->id)->where('type', NotificationType::AccountBanned)->exists())->toBeTrue();
});

it('notifies role changes and audits them for other admins', function () {
    $issuer = User::factory()->create(['role' => 'admin']);
    $otherAdmin = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create(['role' => 'user']);

    $this->actingAs($issuer)
        ->postJson("/admin/users/{$target->id}/update", [
            'role' => 'moderator',
            'is_verified' => false,
            'is_banned' => false,
        ])
        ->assertOk();

    $targetRow = Notification::where('user_id', $target->id)->sole();

    expect($targetRow->type)->toBe(NotificationType::RoleChanged)
        ->and($targetRow->data['role'])->toBe('moderator')
        ->and(Notification::where('user_id', $otherAdmin->id)->where('type', NotificationType::AdminRoleChanged)->exists())->toBeTrue()
        ->and(Notification::where('user_id', $issuer->id)->where('type', NotificationType::AdminRoleChanged)->exists())->toBeFalse();
});

it('notifies bans and unbans from the user editor', function () {
    $issuer = User::factory()->create(['role' => 'admin']);
    $otherAdmin = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create(['role' => 'user']);

    $this->actingAs($issuer)
        ->postJson("/admin/users/{$target->id}/update", [
            'role' => 'user',
            'is_verified' => false,
            'is_banned' => true,
            'duration_type' => 'temporary',
            'banned_until' => now()->addDay()->toIso8601String(),
            'ban_reason' => 'Harassment.',
        ])
        ->assertOk();

    expect($target->fresh()->is_banned)->toBeTrue()
        ->and(Notification::where('user_id', $target->id)->where('type', NotificationType::AccountBanned)->exists())->toBeTrue()
        ->and(Notification::where('user_id', $otherAdmin->id)->where('type', NotificationType::AdminBanIssued)->exists())->toBeTrue();

    $this->actingAs($issuer)
        ->postJson("/admin/users/{$target->id}/update", [
            'role' => 'user',
            'is_verified' => false,
            'is_banned' => false,
        ])
        ->assertOk();

    expect($target->fresh()->is_banned)->toBeFalse()
        ->and(Notification::where('user_id', $target->id)->where('type', NotificationType::AccountUnbanned)->exists())->toBeTrue()
        ->and(Notification::where('user_id', $otherAdmin->id)->where('type', NotificationType::AdminUnbanned)->exists())->toBeTrue();
});

it('does not send an admin audit to a newly promoted admin', function () {
    $issuer = User::factory()->create(['role' => 'admin']);
    $otherAdmin = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create(['role' => 'user']);

    $this->actingAs($issuer)
        ->postJson("/admin/users/{$target->id}/update", [
            'role' => 'admin',
            'is_verified' => false,
            'is_banned' => false,
        ])
        ->assertOk();

    expect(Notification::where('user_id', $target->id)->where('type', NotificationType::RoleChanged)->exists())->toBeTrue()
        ->and(Notification::where('user_id', $target->id)->where('type', NotificationType::AdminRoleChanged)->exists())->toBeFalse()
        ->and(Notification::where('user_id', $otherAdmin->id)->where('type', NotificationType::AdminRoleChanged)->exists())->toBeTrue()
        ->and(Notification::where('user_id', $issuer->id)->where('type', NotificationType::AdminRoleChanged)->exists())->toBeFalse();
});

it('does not notify when only the verification flag changes', function () {
    $issuer = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create(['role' => 'user', 'is_verified' => false]);

    $this->actingAs($issuer)
        ->postJson("/admin/users/{$target->id}/update", [
            'role' => 'user',
            'is_verified' => true,
            'is_banned' => false,
        ])
        ->assertOk();

    expect(Notification::count())->toBe(0);
});
