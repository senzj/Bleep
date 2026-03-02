<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;

use App\Models\User;
use App\Models\Repost;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    // user profile page
    public function index($username)
    {
        $user = User::where('username', $username)->with('preferences')->firstOrFail();
        $authUser = Auth::user();

        // Check if this is the current user's profile
        $isOwnProfile = Auth::check() && Auth::id() === $user->id;

        // Check if current user follows this profile
        $isFollowing = Auth::check() ? $authUser->isFollowing($user) : false;

        // Check friendship (mutual follow)
        $isFriend = !$isOwnProfile && Auth::check() && $authUser->isFriend($user);

        // Privacy flags from preferences
        $isPrivate        = !$isOwnProfile && ($user->preferences?->private_profile ?? false);
        $blockNewFollows  = $user->preferences?->block_new_followers ?? false;
        $hideOnlineStatus = $user->preferences?->hide_online_status ?? false;

        $isBlockedByCurrentUser = Auth::check() && $authUser->hasBlocked($user);
        $isBlockedByUser = Auth::check() && $authUser->isBlockedBy($user);
        $hasBlockingRelationship = $isBlockedByCurrentUser || $isBlockedByUser;

        // Can view content: own profile, or not private, or already following; but never if blocked either direction
        $canViewContent = !$hasBlockingRelationship && ($isOwnProfile || !$isPrivate || $isFollowing);

        // Online status: hidden from non-friends if hide_online_status is on
        $canSeeOnlineStatus = $isOwnProfile || !$hideOnlineStatus || $isFriend;

        // Can follow: not own profile, logged in, not already following, and block_new_followers is off
        // Friends (mutual) are exempt from block_new_followers
        $canFollow = !$isOwnProfile
            && Auth::check()
            && !$isFollowing
            && !$hasBlockingRelationship
            && (!$blockNewFollows || $isFriend);

        if ($canViewContent) {
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
        } else {
            $bleeps  = null;
            $reposts = null;
        }

        $followersCount = $user->followers()->count();
        $followingCount = $user->following()->count();

        return view('pages.users.profile', [
            'user'               => $user,
            'bleeps'             => $bleeps,
            'reposts'            => $reposts,
            'followersCount'     => $followersCount,
            'followingCount'     => $followingCount,
            'isFollowing'        => $isFollowing,
            'isOwnProfile'       => $isOwnProfile,
            'isFriend'           => $isFriend,
            'isPrivate'          => $isPrivate,
            'isBlocked'          => $isBlockedByUser,
            'isBlockedByUser'    => $isBlockedByUser,
            'isBlockedByCurrentUser' => $isBlockedByCurrentUser,
            'canViewContent'     => $canViewContent,
            'canFollow'          => $canFollow,
            'canSeeOnlineStatus' => $canSeeOnlineStatus,
        ]);
    }

    public function bleeps(Request $request, $username)
    {
        $user = User::where('username', $username)->with('preferences')->firstOrFail();
        $authUser = Auth::user();
        $isOwnProfile = Auth::check() && Auth::id() === $user->id;
        $isFollowing = Auth::check() ? $authUser->isFollowing($user) : false;
        $isPrivate = !$isOwnProfile && ($user->preferences?->private_profile ?? false);
        $hasBlockingRelationship = Auth::check() && $authUser->isBlockedOrHasBlocked($user);

        abort_if($hasBlockingRelationship || ($isPrivate && !$isFollowing && !$isOwnProfile), 403);

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
        $user = User::where('username', $username)->with('preferences')->firstOrFail();
        $authUser = Auth::user();
        $isOwnProfile = Auth::check() && Auth::id() === $user->id;
        $isFollowing = Auth::check() ? $authUser->isFollowing($user) : false;
        $isPrivate = !$isOwnProfile && ($user->preferences?->private_profile ?? false);
        $hasBlockingRelationship = Auth::check() && $authUser->isBlockedOrHasBlocked($user);

        abort_if($hasBlockingRelationship || ($isPrivate && !$isFollowing && !$isOwnProfile), 403);

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
