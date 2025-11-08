{{-- scripts --}}
@vite([
    'resources/js/bleep/modals/posts/edit.js',   // ensure edit handler is loaded
    'resources/js/bleep/posts/like.js',
    'resources/js/bleep/posts/comment.js',
    'resources/js/bleep/posts/repost.js',
    'resources/js/bleep/posts/share.js',
])

{{-- Props --}}
@props([
    'bleep',
    'showCommentsButton' => true,
])

@php
    // Normalize showCommentsButton so strings like "false" become boolean false
    $showCommentsButton = filter_var($showCommentsButton ?? true, FILTER_VALIDATE_BOOLEAN);

    $isAnonymous = (bool) $bleep->is_anonymous;

    if ($isAnonymous) {
        // viewer seed: authenticated user id or session id for guests
        $viewerSeed = auth()->check() ? auth()->id() : request()->session()->getId();
        // stable per (bleep, viewer)
        $displayName = $bleep->anonymousDisplayNameFor($viewerSeed);
        $username = '@anonymous';
    } else {
        $displayName = $bleep->user->dname ?? 'Unknown';
        $username = "@" . ($bleep->user->username ?? 'Unknown');
    }

    // counts and user repost state
    $shareCount = \App\Models\Share::where('bleep_id', $bleep->id)->count();
    $repostCount = \App\Models\Repost::where('bleep_id', $bleep->id)->count();
    $totalShareCount = $shareCount + $repostCount;
    $hasReposted = auth()->check() ? \App\Models\Repost::where('bleep_id', $bleep->id)->where('user_id', auth()->id())->exists() : false;
@endphp

<article class="bg-base-100 rounded-lg p-4 shadow-md hover:shadow-lg transition-shadow duration-200">

    {{-- Header: Avatar + Author Info --}}
    <div class="flex gap-3 mb-4">
        {{-- Avatar --}}
        @if(! $isAnonymous && $bleep->user)
            <div class="avatar shrink-0 bleep-avatar" data-bleep-id="{{ $bleep->id }}">
                <x-subcomponents.avatar :user="$bleep->user" :size="12" />
            </div>
        @elseif($isAnonymous)
            {{-- Anonymous avatar --}}
            <div class="avatar shrink-0 bleep-avatar" data-bleep-id="{{ $bleep->id }}">
                <div class="size-12 rounded-full bg-base-300 flex items-center justify-center overflow-hidden">
                    <i data-lucide="hat-glasses" class="w-6 h-6 text-base-content/80"></i>
                </div>
            </div>
        @else
            <div class="avatar placeholder shrink-0 bleep-avatar" data-bleep-id="{{ $bleep->id }}">
                <div class="size-12 rounded-full ring ring-base-300 ring-offset-base-100 ring-offset-2 bg-base-300">
                    <span class="text-xl">?</span>
                </div>
            </div>
        @endif

        {{-- Author Info + Actions --}}
        <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-2">
                {{-- Left: Name and username --}}
                <div class="flex items-start space-x-3">

                    {{-- Name and username --}}
                    <div>
                        {{-- Display name --}}
                        <div class="font-semibold text-gray-900">
                            <span class="font-semibold text-sm truncate bleep-display-name" data-bleep-id="{{ $bleep->id }}">{{ $displayName }}</span>
                        </div>

                        {{-- Username (always show computed username) --}}
                        <div class="text-gray-500 text-sm">
                            <span class="text-base-content/60 text-sm truncate bleep-username" data-bleep-id="{{ $bleep->id }}">{{ $username }}</span>
                        </div>
                    </div>
                </div>

                {{-- Right: Time posted & Actions --}}
                <div class="flex items-start space-x-3">
                    <div class="text-gray-400 text-xs whitespace-nowrap mt-2">
                        <div class="">
                            {{ $bleep->created_at->diffForHumans() }}
                        </div>

                        {{-- Edited Badge --}}
                        @if ($bleep->updated_at->gt($bleep->created_at->addSeconds(5)))
                            <div class="text-xs text-base-content/50">
                                edited {{ $bleep->updated_at->diffForHumans() }}
                            </div>
                        @endif
                    </div>

                    {{-- Action Dropdown --}}
                    <div class="dropdown dropdown-end">
                        <button tabindex="0" class="btn btn-ghost btn-xs btn-circle hover:bg-base-300">
                            <i data-lucide="more-vertical" class="w-5 h-5"></i>
                        </button>

                        <ul tabindex="0"
                            class="dropdown-content z-1 shadow-lg bg-base-100 rounded-xl w-52 border border-base-200 p-2 space-y-1">

                            @can('update', $bleep)
                                <li>
                                    <button type="button"
                                        class="cursor-pointer flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-700 rounded-md hover:bg-base-200 transition edit-bleep-btn"
                                        data-bleep-id="{{ $bleep->id }}"
                                        data-bleep-message="{{ $bleep->message }}"
                                        data-bleep-anonymous="{{ $bleep->is_anonymous ? '1' : '0' }}">
                                        <i data-lucide="pencil" class="w-4 h-4"></i>
                                        <span>Edit</span>
                                    </button>
                                </li>
                            @endcan
                            @can('delete', $bleep)
                                <li>
                                    <form method="POST" action="/bleeps/{{ $bleep->id }}/delete">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                onclick="return confirm('Are you sure you want to delete this bleep?')"
                                                class="cursor-pointer flex items-center gap-2 w-full px-3 py-2 text-sm text-red-600 rounded-md hover:bg-red-50 transition">
                                            <i data-lucide="trash" class="w-4 h-4"></i>
                                            <span>Delete</span>
                                        </button>
                                    </form>
                                </li>
                            @endcan

                            <li>
                                <button class="cursor-pointer flex items-center gap-2 w-full px-3 py-2 text-sm text-orange-500 rounded-md hover:bg-orange-50 transition">
                                    <i data-lucide="flag" class="w-4 h-4"></i>
                                    <span>Report</span>
                                </button>
                            </li>
                        </ul>
                    </div>

                </div>
            </div>
        </div>
    </div>

    {{-- Message Content --}}
    <div class="mb-4">
        <p class="text-base leading-relaxed text-base-content bleep-message" data-bleep-id="{{ $bleep->id }}">
            {{ $bleep->message }}
        </p>

        {{-- date and time created + views --}}
        <div class="text-xs text-gray-400 mt-5 flex items-center justify-between">
            {{-- Left: Date posted --}}
            <span class="text-xs">
                {{ $bleep->created_at->timezone(Auth::user()->timezone ?? 'UTC')->format('M j, Y \| g:i:s A') }}
            </span>

            {{-- Right: Views --}}
            <div class="flex items-center gap-1 text-base-content/60 text-xs">
                <i data-lucide="eye" class="w-3 h-3"></i>
                <span>5.2k</span>
                <span class="hidden sm:inline">Views</span>
            </div>
        </div>
    </div>

    {{-- Engagement Footer --}}
    <div class="flex items-center justify-between pt-3 border-t border-base-300 text-sm">

        {{-- Likes --}}
        <form method="POST" action="/bleeps/{{ $bleep->id }}/like" class="like-form inline">
            @csrf
            <button type="submit"
                class="btn btn-ghost btn-xs gap-1 hover:bg-red-100/50 hover:text-red-600 transition-colors group like-btn
                {{ Auth::check() && $bleep->isLikedBy(Auth::user()) ? 'text-red-600' : '' }}"
                data-bleep-id="{{ $bleep->id }}">

                {{-- Heart Icon --}}
                <i data-lucide="heart" class="w-5 h-5 group-hover:scale-110 transition-transform heart-icon"></i>

                {{-- Count on mobile, text on desktop --}}
                <span class="inline sm:hidden text-sm like-count" data-bleep-id="{{ $bleep->id }}">
                    {{ $bleep->likes()->count() }}
                </span>


                <span class="hidden sm:inline text-xs like-text">
                    @if (Auth::check() && $bleep->isLikedBy(Auth::user()))
                        {{ $bleep->likes()->count() }} {{ $bleep->likes()->count() === 1 ? 'Liked' : 'Likes' }}
                    @else
                        {{ $bleep->likes()->count() }} {{ $bleep->likes()->count() === 1 ? 'Like' : 'Likes' }}
                    @endif
                </span>
            </button>
        </form>

        {{-- Comments --}}
        @if($showCommentsButton)
            <button class="btn btn-ghost btn-xs gap-1 hover:bg-blue-100/50 hover:text-blue-600 transition-colors group comment-btn"
                data-bleep-id="{{ $bleep->id }}">
                <i data-lucide="message-circle" class="w-5 h-5 group-hover:scale-110 transition-transform"></i>
                {{-- Mobile: number only / Desktop: text label --}}
                <span class="inline sm:hidden text-xs">{{ $bleep->comments()->count() }}</span>
                <span class="hidden sm:inline text-xs">
                    {{ $bleep->comments()->count() }} {{ $bleep->comments()->count() === 1 ? 'Comment' : 'Comments' }}
                </span>
            </button>
        @endif

        {{-- Repost --}}
        <button type="button" data-bleep-id="{{ $bleep->id }}" data-reposted="{{ $hasReposted ? '1' : '0' }}" title="{{ $hasReposted ? 'You reposted — click to remove' : 'Repost' }}"
            class="cursor-pointer flex items-center gap-2 text-sm font-medium group repost-btn rounded-full px-3 py-1.5 transition-all duration-200 ease-out
                {{ $hasReposted ? 'bg-green-100 text-green-700 shadow-sm' : 'hover:bg-green-50 hover:text-green-600 text-gray-500' }}">
            <i data-lucide="repeat" class="w-5 h-5 transition-transform duration-200 group-hover:scale-110"></i>

            {{-- Mobile: compact repost·share --}}
            <span class="inline sm:hidden text-xs repost-meta-mobile" data-bleep-id="{{ $bleep->id }}">
                {{ $repostCount }}
            </span>

            {{-- Desktop: full text + share count --}}
            <span class="hidden sm:inline text-xs repost-text" data-bleep-id="{{ $bleep->id }}">
                <span class="repost-label">
                    <span class="repost-share-count mr-0.5" data-bleep-id="{{ $bleep->id }}">{{ $repostCount }} </span>
                    {{ $hasReposted ? 'Reposted' : ($repostCount === 1 ? 'Repost' : 'Reposts') }}
                </span>
            </span>
        </button>

        {{-- Share --}}
        <button type="button" data-bleep-id="{{ $bleep->id }}" title="Share / Copy link"
            class="cursor-pointer flex items-center gap-2 text-sm font-medium group share-btn rounded-full px-3 py-1.5 transition-all duration-200 ease-out hover:bg-primary/5">

            <i data-lucide="forward" class="w-5 h-5 transition-transform duration-200 group-hover:scale-110"></i>

            {{-- show only actual shares (not reposts) --}}
            <span class="inline sm:hidden text-xs share-count" data-bleep-id="{{ $bleep->id }}">{{ $shareCount }}</span>
            <span class="hidden sm:inline text-xs share-text" data-bleep-id="{{ $bleep->id }}">
                {{ $shareCount }} {{ $shareCount === 1 ? 'Share' : 'Shares' }}
            </span>
        </button>
    </div>
</article>

{{-- share/repost modal --}}
<div id="share-modal" class="hidden fixed inset-0 z-50 items-center justify-center">
    <div id="share-modal-overlay" class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>

    <div class="relative max-w-sm w-full mx-4 bg-base-100 rounded-2xl shadow-xl border border-base-300 p-5 z-10 space-y-4">
        <div class="space-y-1">
            <h3 class="text-lg font-semibold">Share Post</h3>
            <p class="text-sm text-base-content/70">Copy the link to share this post.</p>
        </div>

        <input id="share-url-input" type="hidden" />

        <div id="share-link-card" role="button" tabindex="0"
             class="flex items-center justify-between gap-4 rounded-xl border border-base-300 bg-base-200/70 px-4 py-3 cursor-pointer transition hover:border-primary hover:bg-primary/10 focus:outline-none focus:ring-2 focus:ring-primary/60">
            <div class="flex items-center gap-3 min-w-0">
                <span class="flex items-center justify-center rounded-full bg-primary/10 text-primary size-10">
                    <i data-lucide="link-2" class="w-4 h-4"></i>
                </span>

                <div class="min-w-0">
                    <span class="text-xs uppercase tracking-wide text-base-content/60">Share link</span>
                    <span id="share-url-display" class="mt-1 block text-sm font-medium text-base-content truncate">
                        This is a bleep link URL
                    </span>
                </div>
            </div>

            <button id="share-copy-btn" type="button" class="btn btn-ghost btn-sm shrink-0" title="Copy link">
                <i data-lucide="copy" class="w-4 h-4"></i>
            </button>
        </div>

        <div class="flex items-center justify-between gap-3 text-xs text-base-content/70">
            <span>Sharing this post will create a link anyone can use to view it.</span>
            <button id="share-cancel-btn" type="button" class="btn btn-ghost btn-sm bg-gray-300">Cancel</button>
        </div>
    </div>
</div>

@push('script')
    <script>
        function autoGrow(element) {
            element.style.height = "auto";
            element.style.height = (element.scrollHeight) + "px";
        }
    </script>
@endpush
