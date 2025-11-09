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

    // compute a usable avatar URL (public storage if exists, else remote fallback, else default)
    if ($user) {
        if ($user->profile_picture && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->profile_picture)) {
            $userAvatarUrl = asset('storage/' . $user->profile_picture);
        }  else {
            $userAvatarUrl = asset('images/avatar/default.jpg');
        }
    } else {
        $userAvatarUrl = asset('images/avatar/default.jpg');
    }
@endphp

<div class="flex gap-3 p-4 rounded-lg bg-base-100 shadow-md hover:shadow-lg transition-shadow duration-200"
     data-comment-id="{{ $comment->id }}"
     data-comment-created-at="{{ $comment->created_at->toIso8601String() }}"
     data-comment-updated-at="{{ $comment->updated_at->toIso8601String() }}">
    {{-- Avatar --}}
    <div class="avatar shrink-0">
        @if ($isAnonymous)
            <div class="size-10 rounded-full bg-base-300 flex items-center justify-center">
                <i data-lucide="hat-glasses" class="w-5 h-5 text-base-content"></i>
            </div>
        @else
            <x-subcomponents.avatar :user="$user" size="10" />
        @endif
    </div>

    {{-- Content --}}
    <div class="flex-1 min-w-0">
        <div class="flex items-start justify-between gap-2">
            <div class="flex flex-col min-w-0">
                <span class="font-semibold text-sm truncate comment-display-name">{{ $displayName }}</span>
                <span class="text-xs text-base-content/50 truncate comment-username">{{ $usernameLine }}</span>
            </div>

            {{-- date and actions --}}
            <div class="flex items-center gap-2 shrink-0">
                {{-- comment date --}}
                <div class="flex flex-col text-right shrink-0 text-xs text-base-content/50 leading-tight whitespace-nowrap comment-date-wrap">
                    @php
                        // timezone: prefer the comment author timezone (or fallback to app timezone)
                        $tz = $user?->timezone ?? config('app.timezone', 'UTC');

                        $createdAt = $comment->created_at->setTimezone($tz);
                        $nowTz = \Carbon\Carbon::now($tz);
                        $ageSeconds = $nowTz->diffInSeconds($createdAt);
                        $isToday = $createdAt->isSameDay($nowTz);
                        $within7Days = $ageSeconds <= (7 * 86400);

                        // display rules:
                        // - if created today => show relative ("X minutes/hours ago")
                        //   but if you prefer absolute after 24h use time (we treat >24h as not today)
                        // - if not today => show local time (g:i A)
                        // - human diff only shown when within 7 days

                        $createdTimeLabel = $createdAt->format('g:i A');
                        $createdHuman = $within7Days ? $createdAt->diffForHumans() : null;
                    @endphp

                    <span title="{{ $isAnonymous ? 'Posting time hidden for anonymous users' : $createdAt->toIso8601String() }}">
                        @if($isToday)
                            {{-- show relative (mins/hours ago) when today --}}
                            {{ $createdHuman }}
                        @else
                            {{-- show absolute local time when not today --}}
                            {{ $createdTimeLabel }}
                            @if($within7Days)
                                · {{ $createdHuman }}
                            @endif
                        @endif
                    </span>

                    @php
                        $edited = $comment->updated_at && $comment->updated_at->gt($comment->created_at);
                    @endphp

                    @if($edited)
                        @php
                            $updatedAt = $comment->updated_at->setTimezone($tz);
                            $updatedAge = $nowTz->diffInSeconds($updatedAt);
                            $updatedWithin7 = $updatedAge <= (7 * 86400);
                            $updatedTimeLabel = $updatedAt->format('g:i A');
                            $updatedHuman = $updatedWithin7 ? $updatedAt->diffForHumans() : null;
                        @endphp

                        <span class="comment-edited-tag text-xs text-base-content/50" title="Edited: {{ $updatedAt->format('M d, Y | g:i A') }}">
                            Edited ·
                            @if($updatedWithin7)
                                {{ ' ' . $updatedHuman }}
                            @else
                                {{ ' ' . $updatedTimeLabel }}
                            @endif
                        </span>
                    @endif
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
                                        data-user-avatar="{{ $userAvatarUrl }}"
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
