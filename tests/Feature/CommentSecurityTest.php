<?php

use App\Models\Bleep;
use App\Models\Comments;
use App\Models\User;
use App\Models\UserPreferences;

it('does not expose commenter email or timezone in comment payloads', function () {
    $bleep = Bleep::factory()->create();
    $commenter = User::factory()->create([
        'email' => 'commenter@example.com',
        'timezone' => 'Africa/Lagos',
    ]);

    Comments::factory()->create([
        'bleep_id' => $bleep->id,
        'user_id' => $commenter->id,
        'message' => 'Public comment',
    ]);

    $response = $this->getJson("/bleeps/comments/{$bleep->id}/comments")->assertOk();
    $user = $response->json('comments.0.user');

    expect($user)->not->toHaveKey('email')
        ->and($user)->not->toHaveKey('timezone');
});

it('persists a report when a comment is reported', function () {
    $bleep = Bleep::factory()->create();
    $comment = Comments::factory()->create([
        'bleep_id' => $bleep->id,
    ]);
    $reporter = User::factory()->create();

    $this->actingAs($reporter)
        ->postJson("/bleeps/comments/{$comment->id}/report", [
            'reason' => 'spam',
            'description' => 'Repeated promotional links.',
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->assertDatabaseHas('reports', [
        'reportable_id' => $comment->id,
        'reportable_type' => Comments::class,
        'reporter_id' => $reporter->id,
        'reason' => 'Repeated promotional links.',
        'category' => 'spam',
        'status' => 'pending',
    ]);
});

it('hides comments for private profile bleeps from non-followers', function () {
    $owner = User::factory()->create();
    $owner->preferences()->create([
        ...UserPreferences::defaults(),
        'private_profile' => true,
    ]);
    $bleep = Bleep::factory()->for($owner)->create();

    Comments::factory()->create(['bleep_id' => $bleep->id]);

    $this->actingAs(User::factory()->create())
        ->getJson("/bleeps/comments/{$bleep->id}/comments")
        ->assertNotFound();
});
