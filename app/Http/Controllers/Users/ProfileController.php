<?php

namespace App\Http\Controllers\Users;

use App\Models\User;
use App\Models\Repost;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    // user profile page
    public function index($username)
    {
        // Fetch user with relationships
        $user = User::where('username', $username)->firstOrFail();

        // Get user's bleeps (not anonymous ones for privacy)
        $bleeps = $user->bleeps()
            ->with(['user', 'media', 'likes', 'comments'])
            ->where('is_anonymous', false)
            ->latest()
            ->paginate(20);

        // Attach repost data for authenticated users
        if (Auth::check()) {
            $bleeps->getCollection()->transform(function ($bleep) {
                $bleep->followedReposts = Repost::visibleToUser(Auth::id(), $bleep->id);
                return $bleep;
            });
        }

        // Get user's reposts
        $reposts = Repost::where('user_id', $user->id)
            ->with(['bleep.user', 'bleep.media', 'bleep.likes', 'bleep.comments'])
            ->latest()
            ->paginate(20);

        // Attach repost data for reposts too
        if (Auth::check()) {
            $reposts->getCollection()->transform(function ($repost) {
                if ($repost->bleep) {
                    $repost->bleep->followedReposts = Repost::visibleToUser(Auth::id(), $repost->bleep->id);
                }
                return $repost;
            });
        }

        // Get follow counts
        $followersCount = $user->followers()->count();
        $followingCount = $user->following()->count();

        // Check if current user follows this profile
        $isFollowing = Auth::check() ? Auth::user()->isFollowing($user) : false;

        // Check if this is the current user's profile
        $isOwnProfile = Auth::check() && Auth::id() === $user->id;

        return view('pages.users.profile', [
            'user' => $user,
            'bleeps' => $bleeps,
            'reposts' => $reposts,
            'followersCount' => $followersCount,
            'followingCount' => $followingCount,
            'isFollowing' => $isFollowing,
            'isOwnProfile' => $isOwnProfile,
        ]);
    }

    public function bleeps(Request $request, $username)
    {
        $user = User::where('username', $username)->firstOrFail();

        $bleeps = $user->bleeps()
            ->with(['user', 'media', 'likes', 'comments'])
            ->where('is_anonymous', false)
            ->latest()
            ->paginate(20);

        if (Auth::check()) {
            $bleeps->getCollection()->transform(function ($bleep) {
                $bleep->followedReposts = Repost::visibleToUser(Auth::id(), $bleep->id);
                return $bleep;
            });
        }

        return response()->json([
            'html' => view('components.subcomponents.profile.bleeps', ['bleeps' => $bleeps])->render(),
            'next_page_url' => $bleeps->hasMorePages()
                ? route('user.bleeps', ['username' => $user->username, 'page' => $bleeps->currentPage() + 1])
                : null,
        ]);
    }

    public function reposts(Request $request, $username)
    {
        $user = User::where('username', $username)->firstOrFail();

        $reposts = Repost::where('user_id', $user->id)
            ->with(['bleep.user', 'bleep.media', 'bleep.likes', 'bleep.comments'])
            ->latest()
            ->paginate(20);

        if (Auth::check()) {
            $reposts->getCollection()->transform(function ($repost) {
                if ($repost->bleep) {
                    $repost->bleep->followedReposts = Repost::visibleToUser(Auth::id(), $repost->bleep->id);
                }
                return $repost;
            });
        }

        return response()->json([
            'html' => view('components.subcomponents.profile.reposts', ['reposts' => $reposts, 'user' => $user])->render(),
            'next_page_url' => $reposts->hasMorePages()
                ? route('user.reposts', ['username' => $user->username, 'page' => $reposts->currentPage() + 1])
                : null,
        ]);
    }
}
