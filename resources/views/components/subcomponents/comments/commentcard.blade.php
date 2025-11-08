@vite([
    'resources/js/bleep/posts/comment.js',
])

@props([
    'comment',
    'bleep',
])

@php
    $user = $comment->user ?? null;
    $isAnonymous = (bool) $comment->is_anonymous;
    $viewerSeed = Auth::check() ? Auth::id() : request()->session()->getId();
    $displayName = $isAnonymous
        ? $comment->anonymousDisplayNameFor($viewerSeed)
        : ($user?->dname ?? 'Unknown');
    $username = $user?->username ?? '';
    $usernameLine = !$isAnonymous && $username
        ? "@{$username}"
        : '@anonymous';
    $email = !$isAnonymous && $user?->email ? $user->email : null;
@endphp

<div class="flex gap-3 p-4 rounded-lg bg-base-100 shadow-md hover:shadow-lg transition-shadow duration-200" data-comment-id="{{ $comment->id }}">
    {{-- Avatar --}}
    <div class="avatar shrink-0">
        @if ($isAnonymous)
            <div class="size-10 rounded-full bg-base-300 flex items-center justify-center">
                <i data-lucide="hat-glasses" class="w-5 h-5 text-base-content"></i>
            </div>
        @else
            <div class="size-10 rounded-full overflow-hidden">
                <img src="https://avatars.laravel.cloud/{{ urlencode($email ?? '') }}" alt="{{ $displayName }}'s avatar" class="w-full h-full object-cover" />
            </div>
        @endif
    </div>

    {{-- Content --}}
    <div class="flex-1 min-w-0">
        <div class="flex items-start justify-between gap-2">
            <div class="flex flex-col min-w-0">
                <span class="font-semibold text-sm truncate comment-display-name">{{ $displayName }}</span>
                <span class="text-xs text-base-content/50 truncate comment-username">{{ $usernameLine }}</span>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <div class="flex flex-col text-right shrink-0 text-xs text-base-content/50 leading-tight whitespace-nowrap">
                    <span title="{{ $isAnonymous ? 'Posting time hidden for anonymous users' : '' }}">
                        {{ $comment->created_at->format('M d, Y | g:i A') }}
                    </span>
                    <span>{{ $comment->created_at->diffForHumans() }}</span>
                </div>

                {{-- Actions Dropdown --}}
                <div class="dropdown dropdown-end">
                    <button tabindex="0" class="btn btn-ghost btn-xs btn-circle hover:bg-base-300" title="More options">
                        <i data-lucide="more-vertical" class="w-4 h-4"></i>
                    </button>
                    <ul tabindex="0" class="dropdown-content z-10 shadow-lg bg-base-100 rounded-xl w-48 border border-base-200 p-2 space-y-1">
                        @auth
                            @if (auth()->id() === $comment->user_id)
                                <li>
                                    <button type="button"
                                        class="cursor-pointer flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-700 rounded-md hover:bg-base-200 transition edit-comment-btn"
                                        data-comment-id="{{ $comment->id }}"
                                        data-comment-message="{{ htmlspecialchars($comment->message, ENT_QUOTES) }}"
                                        data-is-anonymous="{{ $comment->is_anonymous ? '1' : '0' }}"
                                        data-user-name="{{ $user?->dname ?? 'Unknown' }}"
                                        data-user-username="{{ $user?->username ?? 'unknown' }}"
                                        data-user-email="{{ $user?->email ?? '' }}"
                                        title="Edit this comment">
                                        <i data-lucide="pencil" class="w-4 h-4"></i>
                                        <span>Edit</span>
                                    </button>
                                </li>

                                <li>
                                    <button type="button"
                                        class="cursor-pointer flex items-center gap-2 w-full px-3 py-2 text-sm text-red-600 rounded-md hover:bg-red-50 transition delete-comment-btn"
                                        data-comment-id="{{ $comment->id }}"
                                        title="Delete this comment">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        <span>Delete</span>
                                    </button>
                                </li>
                            @endif
                        @endauth

                        <li>
                            <button type="button"
                                class="cursor-pointer flex items-center gap-2 w-full px-3 py-2 text-sm text-orange-500 rounded-md hover:bg-orange-50 transition report-comment-btn"
                                data-comment-id="{{ $comment->id }}"
                                title="Report this comment">
                                <i data-lucide="flag" class="w-4 h-4"></i>
                                <span>Report</span>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Message --}}
        <p class="text-sm mb-1 mt-2.5 break-words leading-snug text-base-content/90 comment-message">
            {{ $comment->message }}
        </p>
    </div>
</div>
