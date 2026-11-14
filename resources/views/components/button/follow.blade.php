@once
    @push('scripts')
        @vite('resources/js/bleep/users/follow-requests.js')
    @endpush
@endonce

@once
    <dialog id="unfollow-confirm-modal" class="modal modal-bottom sm:modal-middle">
        <div class="modal-box max-w-sm">
            <h3 class="text-lg font-bold">Unfollow this user?</h3>
            <p class="py-2 text-sm text-base-content/70">
                Are you sure you want to unfollow this user? Their bleeps will stop
                appearing in your feed until you follow them again.
            </p>
            <div class="modal-action">
                <button type="button" class="btn btn-ghost btn-sm" data-unfollow-cancel>Cancel</button>
                <button type="button" class="btn btn-error btn-sm" data-unfollow-confirm>Unfollow</button>
            </div>
        </div>
        <form method="dialog" class="modal-backdrop"><button>close</button></form>
    </dialog>
@endonce

@props([
    'user',
    'showFollowed'  => false, // shows "Followed" state for mutual follows (used in People suggestions)
    'showMessage' => false,  // opt-in only
])

{{-- Follow state comes from App\View\Components\Button\Follow, which reads the
     request-scoped FollowContext instead of running EXISTS queries per card. --}}

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
