<?php

use App\Models\Bleep;
use App\Models\User;

function pendingReport(): array
{
    $reporter = User::factory()->create();
    $owner = User::factory()->create();
    $bleep = Bleep::factory()->for($owner)->create();

    $report = $bleep->reports()->create([
        'reporter_id' => $reporter->id,
        'category' => 'spam',
        'reason' => 'This looks like spam.',
        'status' => 'pending',
    ]);

    return [$report, $reporter, $owner];
}

it('forbids regular users from resolving reports', function () {
    [$report] = pendingReport();

    $this->actingAs(User::factory()->create())
        ->postJson("/admin/reports/{$report->id}/mark-reviewed")
        ->assertForbidden();

    expect($report->fresh()->status)->toBe('pending');
});

it('allows admins to resolve a pending report without deleting content', function () {
    [$report] = pendingReport();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->postJson("/admin/reports/{$report->id}/mark-reviewed", [
            'notes' => 'Reviewed, no action needed.',
        ])
        ->assertOk()
        ->assertJson(['message' => 'Report marked as resolved.']);

    $this->assertDatabaseHas('reports', [
        'id' => $report->id,
        'status' => 'resolved',
        'action_taken' => 'none',
        'reviewed_by' => $admin->id,
        'notes' => 'Reviewed, no action needed.',
    ]);

    expect($report->fresh()->reportable)->not->toBeNull();
});
