<?php

use App\Models\Bleep;
use App\Models\BleepViews;
use App\Models\Following;
use App\Models\ProfileVisit;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

it('shows bleep analytics to the author', function () {
    $author = User::factory()->create();
    $bleep = Bleep::factory()->create(['user_id' => $author->id, 'views' => 42]);

    BleepViews::create([
        'bleep_id' => $bleep->id,
        'user_id' => $author->id,
        'viewed_at' => now()->subDay(),
    ]);
    BleepViews::create([
        'bleep_id' => $bleep->id,
        'session_id' => 'guest-session',
        'viewed_at' => now(),
    ]);

    $this->actingAs($author)
        ->get(route('bleeps.analytics', ['bleep' => $bleep->id]))
        ->assertSuccessful()
        ->assertSee('Bleep analytics')
        ->assertSee('Total views')
        ->assertSee('Likes')
        ->assertSee('Guest views');
});

it('hides bleep analytics from everyone else', function () {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $bleep = Bleep::factory()->create(['user_id' => $author->id]);

    $this->actingAs($other)
        ->get(route('bleeps.analytics', ['bleep' => $bleep->id]))
        ->assertForbidden();

    Auth::logout();

    $this->get(route('bleeps.analytics', ['bleep' => $bleep->id]))
        ->assertRedirect(route('login'));
});

it('only lists the analytics action for the author in the post menu', function () {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $bleep = Bleep::factory()->create(['user_id' => $author->id]);

    $this->actingAs($author)
        ->get(route('post', ['id' => $bleep->id]))
        ->assertSuccessful()
        ->assertSee(route('bleeps.analytics', ['bleep' => $bleep->id]));

    $this->actingAs($other)
        ->get(route('post', ['id' => $bleep->id]))
        ->assertSuccessful()
        ->assertDontSee(route('bleeps.analytics', ['bleep' => $bleep->id]));
});

it('shows profile analytics to the owner only', function () {
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    $stranger = User::factory()->create();

    Following::create(['follower_id' => $visitor->id, 'followed_id' => $owner->id]);

    ProfileVisit::create([
        'profile_user_id' => $owner->id,
        'visitor_id' => $visitor->id,
        'visited_on' => now()->toDateString(),
    ]);

    Bleep::factory()->create(['user_id' => $owner->id, 'views' => 12]);

    $this->actingAs($owner)
        ->get(route('profile.analytics', ['username' => $owner->username]))
        ->assertSuccessful()
        ->assertSee('Profile analytics')
        ->assertSee('Profile visits')
        ->assertSee('Followers')
        ->assertSee('Recent visitors');

    $this->actingAs($stranger)
        ->get(route('profile.analytics', ['username' => $owner->username]))
        ->assertForbidden();

    Auth::logout();

    $this->get(route('profile.analytics', ['username' => $owner->username]))
        ->assertRedirect(route('login'));
});
