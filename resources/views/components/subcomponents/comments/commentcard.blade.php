@push('scripts')
    @once('comments-audio-init')
        @vite([
            'resources/js/bleep/posts/comment.js',
            'resources/js/bleep/posts/media/audio.js',
            'resources/js/bleep/posts/media/video.js',
            'resources/js/bleep/posts/media/visibility.js',
        ])

        <script>
            window.requestAnimationFrame(() => {
                document.dispatchEvent(new CustomEvent('bleeps:media:hydrated'));
            });
        </script>
    @endonce
@endpush

@props([
    'comment',
    'bleep',
    'depth' => 0,
])

@php
    $user = $comment->user ?? null;
    $isAnonymous = (bool) $comment->is_anonymous;
    $viewerSeed = Auth::check() ? Auth::id() : request()->session()->getId();
    $displayName = $isAnonymous
        ? $comment->anonymousDisplayNameFor($viewerSeed)
        : ($user->dname ?? 'Unknown');
    $username = $user->username ?? '';
    $usernameLine = !$isAnonymous && $username
        ? "@{$username}"
        : '@anonymous';

    // User role
    $userRole = $user->role ?? null;

    // User verified
    $isVerified = $user->is_verified ?? false;

    // Check if this comment is from the original poster
    $isOP = $bleep && $user && $bleep->user_id === $user->id;

    // Fix avatar URL generation
    if ($user && $user->profile_picture && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->profile_picture)) {
        $userAvatarUrl = asset('storage/' . $user->profile_picture);
    } else {
        $userAvatarUrl = asset('images/avatar/default.jpg');
    }

    $userProfileLink = (!$isAnonymous && $user)
        ? route('user.profile', ['username' => $user->username])
        : '#';

    $isLiked = Auth::check() && $comment->isLikedBy(Auth::user());
    $likesCount = $comment->likesCount();
    $repliesCount = $comment->replies()->count();
    $replyToName = $isAnonymous ? 'anonymous' : ($user->username ?? 'user');

    $isReply = $depth > 0;
@endphp

<div @class([
        'comment-card flex flex-col gap-2 border border-base-200/50 rounded-lg',
    ])
     data-comment-id="{{ $comment->id }}"
     data-comment-depth="{{ $depth }}"
     data-reply-to-name="{{ $replyToName }}"
     data-comment-created-at="{{ $comment->created_at->toIso8601String() }}"
     data-comment-updated-at="{{ $comment->updated_at->toIso8601String() }}"
     data-is-op="{{ $isOP ? '1' : '0' }}">

    <div @class([
            'comment-body flex flex-col gap-2 transition-all duration-200 relative',
            $isReply
                ? 'ml-0 pl-4 rounded-lg bg-base-200/40 hover:bg-base-200/60'
                : 'p-4 rounded-xl bg-base-100 shadow-md hover:shadow-lg border border-base-200/50',
        ])>

        {{-- comment content --}}
        <div class="flex gap-3">
            {{-- Avatar --}}
            <div class="avatar shrink-0">
                @if ($isAnonymous)
                    <div class="size-10 rounded-full bg-base-300 flex items-center justify-center">
                        <i data-lucide="glasses" class="w-5 h-5 text-base-content"></i>
                    </div>
                @else
                    <a href="{{ $userProfileLink }}" class="group" title="View profile: {{ $username }}">
                        <div class="size-10 rounded-full overflow-hidden">
                            <img src="{{ $userAvatarUrl }}" alt="{{ $displayName }}'s avatar" class="w-full h-full object-cover">
                        </div>
                    </a>
                @endif
            </div>

            {{-- Comment Content --}}
            <div class="flex-1 min-w-0">

                {{-- Header --}}
                <div class="flex items-start justify-between gap-2">
                    @if(!$isAnonymous && $user)
                        <a href="{{ $userProfileLink }}" class="group flex flex-col min-w-0" title="View profile">
                            <div class="flex items-center gap-2 flex-wrap">

                                <span class="font-semibold text-sm truncate group-hover:underline comment-display-name">
                                    {{ $displayName }}
                                </span>

                                {{-- badges and tags --}}
                                <div class="flex gap-1 items-center">
                                    @if($isOP)
                                        <span class="badge badge-success badge-xs text-white font-bold" title="Original Poster">OP</span>
                                    @else
                                        {{-- Role Tag beside name --}}
                                        @if ($userRole === 'admin')
                                            <i data-lucide="sparkles" class="w-4 h-4 text-teal-500" aria-label="Admin"></i>
                                        @elseif ($userRole === 'moderator')
                                            <i data-lucide="sparkles" class="w-4 h-4 text-amber-500" aria-label="Moderator"></i>
                                        @endif
                                    @endif

                                    @if ($isVerified)
                                        <i data-lucide="badge-check" class="w-4 h-4 text-blue-500" aria-label="Verified User"></i>
                                    @endif
                                </div>
                            </div>
                            <span class="text-xs text-base-content/50 comment-username">{{ $usernameLine }}</span>
                        </a>
                    @else
                        <div class="flex flex-col min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold text-sm truncate comment-display-name">{{ $displayName }}</span>
                                @if($isOP)
                                    <span class="badge badge-success badge-xs text-white font-bold" title="Original Poster">OP</span>
                                @endif
                            </div>
                            <span class="text-xs text-base-content/50 comment-username">{{ $usernameLine }}</span>
                        </div>
                    @endif

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
                                    {{ $createdHuman }}
                                @else
                                    {{ $createdTimeLabel }} @if($within7Days) · {{ $createdHuman }} @endif
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
                                    Edited · @if($updatedWithin7) {{ $updatedHuman }} @else {{ $updatedTimeLabel }} @endif
                                </span>
                            @endif
                        </div>

                        {{-- Actions Dropdown --}}
                        <div class="dropdown dropdown-end">
                            <button tabindex="0" class="btn btn-ghost btn-xs btn-circle hover:bg-base-300" title="More options">
                                <i data-lucide="more-vertical" class="w-4 h-4"></i>
                            </button>
                            <ul tabindex="0" class="dropdown-content z-10 shadow-lg bg-base-100 rounded-xl w-48 border border-base-200 p-2 space-y-1 max-h-60 overflow-auto">
                                @auth
                                    @if (Auth::id() === $comment->user_id)
                                        <li>
                                            <button type="button"
                                                class="cursor-pointer flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-700 rounded-md hover:bg-base-200 transition edit-comment-btn"
                                                data-comment-id="{{ $comment->id }}"
                                                data-comment-message="{{ e($comment->message) }}"
                                                data-is-anonymous="{{ $comment->is_anonymous ? '1' : '0' }}"
                                                data-user-name="{{ e($user?->dname ?? 'Unknown') }}"
                                                data-user-username="{{ e($user?->username ?? 'unknown') }}"
                                                data-user-email="{{ e($user?->email ?? '') }}"
                                                data-user-avatar="{{ $userAvatarUrl }}"
                                                data-comment-media="{{ $comment->media_path ?? '' }}"
                                                data-comment-media-type="{{ $comment->media_path ? 'image' : '' }}"
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
                <div id="comment-message">
                    @php
                        $cleanMessage = trim($comment->message);
                    @endphp

                    @if($cleanMessage)
                        <div class="text-sm mb-1 mt-2.5 leading-snug text-base-content/90">
                            <p class="whitespace-pre-line wrap-break-word">{{ $cleanMessage }}</p>
                        </div>
                    @endif

                    {{-- Media --}}
                    @if ($comment->media_path)
                        <div class="">
                            @php
                                $mediaPath = $comment->media_path;
                                $isVideo = \Illuminate\Support\Str::of($mediaPath)->lower()->contains(['.mp4', '.mov', '.webm']);
                                $isAudio = \Illuminate\Support\Str::of($mediaPath)->lower()->contains(['.mp3', '.wav', '.m4a', '.aac', '.ogg', '.flac']);

                                $mediaItems = collect([
                                    (object) [
                                        'path' => $mediaPath,
                                        'type' => $isAudio ? 'audio' : ($isVideo ? 'video' : 'image'),
                                        'mime_type' => \Illuminate\Support\Facades\Storage::mimeType($mediaPath) ?? 'application/octet-stream',
                                        'original_name' => basename($mediaPath),
                                    ],
                                ]);

                                $bleepProxy = (object) [
                                    'id' => "comment-{$comment->id}",
                                    'is_nsfw' => false,
                                ];
                            @endphp

                            <div class="mt-2" data-comment-media-wrapper="{{ $comment->id }}">
                                @include('components.bleepsmedia', [
                                    'mediaItems' => $mediaItems,
                                    'isNsfw' => false,
                                    'bleep' => $bleepProxy,
                                ])
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-4 mt-3 text-xs text-base-content/60">

                    <button type="button" class="comment-like-btn cursor-pointer inline-flex items-center gap-1.5 hover:text-error transition-colors  {{ $isLiked ? 'text-error font-semibold' : '' }}"
                        data-comment-id="{{ $comment->id }}"
                        data-liked="{{ $isLiked ? '1' : '0' }}">
                        <i data-lucide="heart" class="w-4 h-4 {{ $isLiked ? 'fill-error text-error' : '' }}"></i>
                        <span class="comment-like-count {{ $isLiked ? 'text-error' : '' }}">{{ $likesCount }}</span>
                    </button>

                    <button type="button" class="comment-reply-btn cursor-pointer inline-flex items-center gap-1.5 hover:text-primary transition-colors"
                        data-comment-id="{{ $comment->id }}">
                        <i data-lucide="reply" class="w-4 h-4"></i>
                        <span>Reply</span>
                    </button>

                    @if($repliesCount > 0)
                        <button type="button" class="comment-toggle-replies cursor-pointer inline-flex items-center gap-1.5 hover:text-primary transition-colors ml-auto"
                            data-comment-id="{{ $comment->id }}"
                            data-expanded="false">
                            <span class="replies-toggle-text">View {{ $repliesCount }} {{ Str::plural('reply', $repliesCount) }}</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 replies-toggle-icon transition-transform"></i>
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <div class="comment-replies-container hidden ml-1 mt-3 space-y-3 border-l border-base-300 pl-1"
             data-comment-id="{{ $comment->id }}"
             data-page="0"
             data-has-more="true"
             data-depth="{{ $depth + 1 }}">

            <div class="replies-list space-y-2"></div>

            <button
                type="button"
                data-comment-id="{{ $comment->id }}"
                class="cursor-pointer load-more-replies-btn hidden text-xs mt-3 text-primary hover:underline font-semibold flex items-center justify-center gap-1 mx-auto">
                View more replies
            </button>
        </div>

    </div>
</div>
