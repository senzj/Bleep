@push('scripts')
    @vite('resources/js/bleep/users/follow-requests.js')
@endpush

@props([
    'user',
    'showFollowed'  => false, // shows "Followed" state for mutual follows (used in People suggestions)
    'showMessage' => false,  // opt-in only
])

@php
    $authUser       = Auth::user();
    $isOwnProfile   = Auth::check() && Auth::id() === $user->id;
    $isFollowing    = Auth::check() && $authUser->isFollowing($user);
    $isFriend       = Auth::check() && $authUser->isFriend($user);
    $isPrivate      = $user->preferences?->private_profile ?? false;
    $blockNewFollows = $user->preferences?->block_new_followers ?? false;
    $canFollow      = Auth::check() && !$isFollowing && (!$blockNewFollows || $isFriend);
    $hasPendingRequest = Auth::check() && $authUser->hasSentRequestTo($user);
@endphp

<div class="gap-1.5 flex items-center">
    @if(!$isOwnProfile && Auth::check())
        @if($isFollowing)
            <button type="button"
                class="btn btn-sm btn-primary gap-2 follow-btn rounded-lg"
                data-user-id="{{ $user->id }}"
                data-following="1">
                <i data-lucide="user-check" class="w-4 h-4 follow-icon"></i>
                <span class="follow-text">Following</span>
                <span class="unfollow-text hidden">Unfollow</span>
            </button>

        @elseif($hasPendingRequest)
            <button type="button"
                class="btn btn-sm btn-outline gap-2 cancel-request-btn rounded-lg"
                data-user-id="{{ $user->id }}">
                <i data-lucide="clock" class="w-4 h-4"></i>
                Requested
            </button>

        @elseif($canFollow)
            @if($isPrivate)
                <button type="button"
                    class="btn btn-sm btn-outline gap-2 request-follow-btn rounded-lg btn-primary hover:btn-primary-focus"
                    data-user-id="{{ $user->id }}">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    Follow
                </button>
            @else
                <button type="button"
                    class="btn btn-sm btn-outline gap-2 follow-btn rounded-lg"
                    data-user-id="{{ $user->id }}"
                    data-following="0">
                    <i data-lucide="user-plus" class="w-4 h-4 follow-icon"></i>
                    <span class="follow-text">Follow</span>
                    <span class="unfollow-text hidden">Unfollow</span>
                </button>
            @endif
        @endif

        @if($showMessage && $isFriend)
            <a href="{{ route('messages') }}?user_id={{ $user->id }}"
                class="btn btn-sm btn-outline gap-2 rounded-lg">
                <i data-lucide="message-square" class="w-4 h-4"></i>
                Chat
            </a>
        @endif
    @endif
</div>
