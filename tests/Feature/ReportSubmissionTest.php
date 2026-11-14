<?php

use App\Models\Bleep;
use App\Models\Comments;
use App\Models\Reports;
use App\Models\User;

it('submits a report for a bleep', function () {
    $reporter = User::factory()->create();
    $bleep = Bleep::factory()->create();

    $this->actingAs($reporter)
        ->postJson(route('reports.store'), [
            'type' => 'bleep',
            'bleep_id' => $bleep->id,
            'category' => 'spam',
            'reason' => 'Spam link.',
        ])
        ->assertCreated()
        ->assertJson(['message' => 'Report submitted successfully.']);

    expect(Reports::where([
        'reportable_id' => $bleep->id,
        'reportable_type' => Bleep::class,
        'reporter_id' => $reporter->id,
    ])->exists())->toBeTrue();
});

it('submits a report for a comment', function () {
    $reporter = User::factory()->create();
    $comment = Comments::factory()->create();

    $this->actingAs($reporter)
        ->postJson(route('reports.store'), [
            'type' => 'comment',
            'comment_id' => $comment->id,
            'category' => 'harassment',
            'reason' => 'Rude comment.',
        ])
        ->assertCreated()
        ->assertJson(['message' => 'Report submitted successfully.']);

    expect(Reports::where([
        'reportable_id' => $comment->id,
        'reportable_type' => Comments::class,
        'reporter_id' => $reporter->id,
    ])->exists())->toBeTrue();
});

it('rejects a duplicate report from the same user', function () {
    $reporter = User::factory()->create();
    $bleep = Bleep::factory()->create();

    $this->actingAs($reporter)->postJson(route('reports.store'), [
        'type' => 'bleep',
        'bleep_id' => $bleep->id,
        'category' => 'spam',
    ])->assertCreated();

    $this->actingAs($reporter)
        ->postJson(route('reports.store'), [
            'type' => 'bleep',
            'bleep_id' => $bleep->id,
            'category' => 'hate',
        ])
        ->assertStatus(409);
});

it('rejects reporting your own content', function () {
    $author = User::factory()->create();
    $bleep = Bleep::factory()->for($author)->create();

    $this->actingAs($author)
        ->postJson(route('reports.store'), [
            'type' => 'bleep',
            'bleep_id' => $bleep->id,
            'category' => 'spam',
        ])
        ->assertStatus(422);
});

it('validates the report target', function (array $payload) {
    $this->actingAs(User::factory()->create())
        ->postJson(route('reports.store'), $payload)
        ->assertUnprocessable();
})->with([
    'missing target' => [['type' => 'bleep', 'category' => 'spam']],
    'unknown bleep' => [['type' => 'bleep', 'bleep_id' => 999999, 'category' => 'spam']],
    'unknown comment' => [['type' => 'comment', 'comment_id' => 999999, 'category' => 'spam']],
    'bad category' => [['type' => 'bleep', 'bleep_id' => 1, 'category' => 'nonsense']],
]);
