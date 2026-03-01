{{-- Props --}}
@props([
    'bleep',
    'showCommentsButton' => true,
])

@php
    // Normalize showCommentsButton so strings like "false" become boolean false
    $showCommentsButton = filter_var($showCommentsButton ?? true, FILTER_VALIDATE_BOOLEAN);

    // If we're on the single post route, disable the floating comments UI by default.
    // This hides the comments button and prevents appending the floating modal on the post page.
    if (request()->routeIs('post')) {
        $showCommentsButton = false;
    }

    $isAnonymous = (bool) $bleep->is_anonymous;

    if ($isAnonymous) {
        $viewerSeed = auth()->check() ? auth()->id() : request()->session()->getId();
        $displayName = $bleep->anonymousDisplayNameFor($viewerSeed);
        $username = '@anonymous';
    } else {
        $displayName = $bleep->user->dname ?? 'Unknown';
        $username = "@" . ($bleep->user->username ?? 'Unknown');
    }

    $shareCount = \App\Models\Share::where('bleep_id', $bleep->id)->count();
    $totalRepostCount = \App\Models\Repost::where('bleep_id', $bleep->id)->count();
    $totalShareCount = $shareCount + $totalRepostCount;
    $hasReposted = auth()->check() ? \App\Models\Repost::where('bleep_id', $bleep->id)->where('user_id', auth()->id())->exists() : false;

    $followedReposts = collect();
    if (Auth::check()) {
        $followedReposts = isset($bleep->followedReposts)
            ? $bleep->followedReposts
            : \App\Models\Repost::visibleToUser(Auth::id(), $bleep->id);
    }

    $followedRepostCount = $followedReposts->count();

    $userProfileLink = $isAnonymous ? "#" : route('user.profile', ['username' => $bleep->user->username]);
@endphp

{{-- Container --}}

<article
    class="rounded-lg"
    data-bleep-card="{{ $bleep->id }}"
>

    {{-- Wrapper --}}
    <div class="bg-base-300 rounded-lg p-4 shadow-lg border border-gray-300/50 hover:shadow-xl transition-shadow duration-200" data-bleep-card="{{ $bleep->id }}">
        {{-- Repost Tag (if reposted by followed users) --}}
        @if($followedRepostCount > 0)
            <div class="flex items-center gap-2 mb-3 text-xs text-base-content/60">
                <i data-lucide="repeat" class="w-4 h-4"></i>

                @if($followedRepostCount === 1)
                    <span>
                        <a href="{{ route('user.profile', ['username' => $followedReposts->first()->user->username]) }}" class="font-semibold hover:underline">{{ $followedReposts->first()->user->username }}</a> reposted
                    </span>
                @elseif($followedRepostCount === 2)
                    <span>
                        <a href="{{ route('user.profile', ['username' => $followedReposts->first()->user->username]) }}" class="font-semibold hover:underline">{{ $followedReposts->first()->user->username }}</a> and
                        <a href="{{ route('user.profile', ['username' => $followedReposts->skip(1)->first()->user->username]) }}" class="font-semibold hover:underline">{{ $followedReposts->skip(1)->first()->user->username }}</a> reposted
                    </span>
                @else
                    <div class="dropdown dropdown-bottom">
                        <button tabindex="0" class="hover:underline cursor-pointer">
                            <a href="#" class="font-semibold">{{ $followedReposts->first()->user->username }}</a>
                            and {{ $followedRepostCount - 1 }} other{{ $followedRepostCount > 2 ? 's' : '' }} reposted
                        </button>

                        <div tabindex="0" class="dropdown-content z-10 card card-compact w-64 p-2 shadow-lg bg-base-100 border border-base-300 rounded-xl mt-1">
                            <div class="card-body p-3">
                                <h3 class="font-semibold text-sm mb-2">Reposted by</h3>
                                <div class="space-y-2 max-h-48 overflow-y-auto">
                                    @foreach($followedReposts as $repost)
                                        <div class="flex items-center gap-2 text-xs">
                                            <div class="avatar">
                                                <div class="w-6 h-6 rounded-full">
                                                    <img src="{{ $repost->user->profile_picture ? asset('storage/' . $repost->user->profile_picture) : asset('images/avatar/default.jpg') }}"
                                                        alt="{{ $repost->user->username }}">
                                                </div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <a href="#" class="font-semibold hover:underline truncate block">
                                                    {{ $repost->user->username }}
                                                </a>
                                                <span class="text-base-content/50 text-xs">
                                                    {{ $repost->created_at->diffForHumans() }}
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Header: Avatar + Author Info --}}
        <div class="flex gap-3 mb-4">
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">

                    {{-- Right --}}
                    <div class="flex items-start gap-3">

                        {{-- User Name Display --}}
                        @if($isAnonymous)
                            <div class="group flex items-start gap-3">
                                <div class="avatar shrink-0 bleep-avatar" data-bleep-id="{{ $bleep->id }}">
                                    <div class="size-12 rounded-full bg-base-300 flex items-center justify-center overflow-hidden">
                                        <i data-lucide="hat-glasses" class="w-6 h-6 text-base-content/80"></i>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-semibold text-base-content flex items-center gap-1.5">
                                        <span class="text-sm truncate bleep-display-name" data-bleep-id="{{ $bleep->id }}">
                                            {{ $displayName }}
                                        </span>

                                        {{-- Verification tag --}}
                                        @if (! $isAnonymous && $bleep->user && $bleep->user->is_verified)
                                            <i data-lucide="badge-check" class="w-4 h-4 text-blue-500"></i>
                                        @endif

                                        {{-- Gray "YOU" tag — only visible to the actual author of the anonymous bleep --}}
                                        @if (Auth::check() && $bleep->user_id === Auth::id() && !Route::is('post'))
                                            <span class="px-1.5 py-0.5 text-[8px] font-extrabold rounded bg-base-content/10 text-base-content/40 border border-base-content/20">
                                                YOU
                                            </span>
                                        @endif

                                        {{-- Role Tag moved beside name using only spacing --}}
                                        @if (! $isAnonymous && $bleep->user && $bleep->user->role === 'moderator')
                                            <span class="px-1 py-0.5 text-xs font-extrabold rounded bg-yellow-500/20 text-yellow-500 border border-yellow-600/20">
                                                MOD
                                            </span>
                                        @endif
                                    </div>

                                    <div class="text-base-content/60 text-xs mt-0.5">
                                        <span class="truncate bleep-username" data-bleep-id="{{ $bleep->id }}">@anonymous</span>
                                    </div>
                                </div>
                            </div>

                        @else
                            <a href="{{ route('user.profile', ['username' => $bleep->user->username]) }}"
                            class="group flex items-start gap-3">

                                <div class="avatar shrink-0 bleep-avatar" data-bleep-id="{{ $bleep->id }}">
                                    <x-subcomponents.avatar :user="$bleep->user" :size="10" />
                                </div>

                                <div class="min-w-0">
                                    <div class="font-semibold text-base-content flex items-center gap-2">
                                        <span class="text-sm truncate bleep-display-name group-hover:underline" data-bleep-id="{{ $bleep->id }}">
                                            {{ $displayName }}
                                        </span>

                                        {{-- badges and tags --}}
                                        <div class="flex gap-1 items-center">
                                            {{-- Role Tag beside name --}}
                                            @if ($bleep->user->role === 'admin')
                                                <i data-lucide="sparkles" class="w-4 h-4 text-teal-500" aria-label="Admin"></i>
                                            @elseif ($bleep->user->role === 'moderator')
                                                <i data-lucide="sparkles" class="w-4 h-4 text-amber-500" aria-label="Moderator"></i>
                                            @endif

                                            {{-- You Tag --}}
                                            @if ($bleep->user->id === Auth::id() && !Route::Is('post'))
                                                <span class="px-1.5 py-0.5 text-[8px] font-extrabold rounded bg-green-500/20 text-green-500 border border-green-600/20">
                                                    YOU
                                                </span>
                                            @endif

                                            {{-- Verified --}}
                                            @if ($bleep->user->is_verified)
                                                <i data-lucide="badge-check" class="w-4 h-4 text-blue-500" aria-label="This user is verified, and legit. Trust me frfr."></i>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="text-base-content/60 text-xs mt-0.5">
                                        <span class="truncate bleep-username group-hover:underline" data-bleep-id="{{ $bleep->id }}">
                                            {{ '@' . $bleep->user->username }}
                                        </span>
                                    </div>

                                </div>
                            </a>
                        @endif

                        {{-- Follow Button --}}
                        @if (! $isAnonymous && $bleep->user && Auth::check() && Auth::id() !== $bleep->user->id)
                            @php $isFollowing = Auth::user()->isFollowing($bleep->user); @endphp

                            <button type="button"
                                data-user-id="{{ $bleep->user->id }}"
                                data-following="{{ $isFollowing ? '1' : '0' }}"
                                class="cursor-pointer flex items-center gap-1.5 text-xs font-medium group follow-btn
                                    rounded-full px-3 py-1.5 mt-1 transition-all duration-200 shadow-sm
                                    {{ $isFollowing
                                            ? 'bg-blue-100 text-blue-700 hover:bg-red-100 hover:text-red-600'
                                            : 'bg-gray-200 text-gray-700 hover:bg-blue-50 hover:text-blue-600' }}">

                                <i data-lucide="{{ $isFollowing ? 'user-round-check' : 'user-round-plus' }}"
                                class="w-4 h-4 transition-transform group-hover:scale-110"></i>

                                <span class="follow-text">{{ $isFollowing ? 'Following' : 'Follow' }}</span>
                                <span class="unfollow-text hidden">Unfollow</span>

                            </button>
                        @endif

                    </div>

                    {{-- Left --}}
                    <div class="flex items-start gap-3">

                        {{-- Timestamp - desktop only --}}
                        <div class="text-xs text-gray-400 mt-1 whitespace-nowrap hidden md:block">
                            @if ($bleep->updated_at->gt($bleep->created_at->addSeconds(5)))
                                <span>
                                    edited {{ $bleep->updated_at->diffForHumans(['short' => true]) }}
                                </span>
                            @else
                                <span>
                                    {{ $bleep->created_at->diffForHumans(['short' => true]) }}
                                </span>
                            @endif
                        </div>

                        <div class="dropdown dropdown-end">
                            <button tabindex="0" class="btn btn-ghost btn-xs btn-circle hover:bg-base-300">
                                <i data-lucide="more-vertical" class="w-5 h-5"></i>
                            </button>

                            <ul tabindex="0" class="dropdown-content z-1 shadow-lg bg-base-100 rounded-xl w-52 border border-base-200 p-2 space-y-1">
                                @can('update', $bleep)
                                    <li>
                                        <button type="button"
                                            class="cursor-pointer flex items-center gap-2 w-full px-3 py-2 text-sm text-base-content rounded-md hover:bg-base-200 transition edit-bleep-btn"
                                            data-bleep-id="{{ $bleep->id }}"
                                            data-bleep-message="{{ $bleep->message }}"
                                            data-bleep-anonymous="{{ $bleep->is_anonymous ? '1' : '0' }}"
                                            data-bleep-nsfw="{{ $bleep->is_nsfw ? '1' : '0' }}">
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
                                @if (Auth::check() && Auth::user()->id !== $bleep->user->id)
                                    <li>
                                        <button type="button" class="cursor-pointer flex items-center gap-2 w-full px-3 py-2 text-sm text-orange-500 rounded-md hover:bg-orange-50 transition report-bleep-btn" data-bleep-id="{{ $bleep->id }}">
                                            <i data-lucide="flag" class="w-4 h-4"></i>
                                            <span>Report</span>
                                        </button>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- Message Content --}}
        <div class="mb-4">
            <div class="bleep-content-section">
                @php
                    $mediaItems = ($bleep->media ?? collect())->take(4);
                    $hasMedia = $mediaItems->count() > 0;
                    $isNsfw = (bool) $bleep->is_nsfw;
                @endphp

                {{-- ALWAYS render the wrapper, toggle visibility based on NSFW state --}}
                <div class="bleep-nsfw-wrapper" data-bleep-id="{{ $bleep->id }}" data-is-nsfw="{{ $isNsfw ? '1' : '0' }}" data-is-anonymous="{{ $bleep->is_anonymous ? '1' : '0' }}">

                    {{-- NSFW Placeholder (shown only when NSFW) --}}
                    <div class="nsfw-placeholder p-6 bg-red-400/20 rounded-lg text-center border-2 border-red-500/20 {{ !$isNsfw ? 'hidden' : '' }}">
                        <div class="flex items-center justify-center mb-3">
                            <i data-lucide="eye-off" class="w-8 h-8 text-red-500"></i>
                        </div>
                        <p class="mb-1 font-semibold text-lg">This Bleep is marked as <span class="text-red-500 font-bold">NSFW</span></p>
                        <p class="text-sm text-base-content/60 mb-4">Content may be sensitive or inappropriate</p>
                        <button type="button" class="text-white! btn btn-sm btn-error nsfw-reveal-btn shadow-sm" data-bleep-id="{{ $bleep->id }}">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                            <p class="font-semibold">View</p>
                        </button>
                    </div>

                    {{-- Deferred NSFW Content --}}
                    <div class="nsfw-content hidden mt-3" data-bleep-id="{{ $bleep->id }}" data-bleep-message="{{ e($bleep->message) }}">
                        <p class="nsfw-message text-base leading-relaxed text-base-content mb-3"></p>
                        @if($hasMedia)
                            @include('components.bleepsmedia', ['mediaItems' => $mediaItems, 'isNsfw' => true])
                        @endif
                        <div class="mt-3 text-center">
                            <button type="button" class="text-white! btn btn-sm btn-outline hide-nsfw-btn shadow-sm bg-gray-500" data-bleep-id="{{ $bleep->id }}">
                                <i data-lucide="eye-off" class="w-4 h-4"></i>
                                <p class="font-semibold">Hide</p>
                            </button>
                        </div>
                    </div>

                    {{-- Normal Content (shown when NOT NSFW) --}}
                    <div class="normal-bleep-content {{ $isNsfw ? 'hidden' : '' }}">
                        @if(!empty($bleep->message))
                            @php
                                $cleanMessage = trim($bleep->message);
                            @endphp

                            @if($cleanMessage)
                                <div class="text-base leading-relaxed text-base-content/90 mb-3">
                                    <p class="whitespace-pre-line wrap-break-word">{{ $cleanMessage }}</p>
                                </div>
                            @endif
                        @endif

                        @if($hasMedia)
                            @include('components.bleepsmedia', ['mediaItems' => $mediaItems, 'isNsfw' => false])
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- views --}}
        <div class="text-xs text-gray-400 flex items-center justify-between min-h-5 min-w-0">
            <time datetime="{{ $bleep->created_at->toIso8601String() }}" class="text-xs min-w-0 truncate" title="{{ $bleep->created_at->timezone(Auth::user()->timezone ?? 'UTC')->format('M j, Y | g:i:s A') }}" aria-label="Posted {{ $bleep->created_at->timezone(Auth::user()->timezone ?? 'UTC')->format('M j, Y | g:i:s A') }}">
                {{-- Short relative time on small screens, full absolute time on sm+ --}}
                <span>{{ $bleep->created_at->timezone(Auth::user()->timezone ?? 'UTC')->format('M j, Y | g:i:s A') }}</span>
            </time>

            <div class="flex items-center gap-1">

                {{-- Timestamp - mobile only --}}
                <div class="text-xs text-gray-400 whitespace-nowrap block md:hidden">
                    @if ($bleep->updated_at->gt($bleep->created_at->addSeconds(5)))
                        <span>
                            edited {{ $bleep->updated_at->diffForHumans(['short' => true]) }}
                        </span>
                    @else
                        <span>
                            {{ $bleep->created_at->diffForHumans(['short' => true]) }}
                        </span>
                    @endif
                </div>

                {{-- dot divider --}}
                <span class="text-base-content mx-1 inline sm:hidden">•</span>

                {{-- views --}}
                <div class="flex items-center gap-1 text-base-content/60 text-xs shrink-0">
                    <span class="view-count">
                        @if($bleep->views >= 1000000)
                            {{ number_format($bleep->views / 1000000, 1) }}M
                        @elseif($bleep->views >= 1000)
                            {{ number_format($bleep->views / 1000, 1) }}k
                        @else
                            {{ $bleep->views }}
                        @endif
                    </span>
                    <i data-lucide="eye" class="w-4 h-4"></i>
                </div>
            </div>
        </div>

        {{-- Engagement Footer --}}
        @php
            $cols = 2; // Likes + Share (always visible)
            $cols += $showCommentsButton ? 1 : 0;
            $cols += auth()->check() ? 1 : 0; // Repost button for authenticated users

            $gridClass = match($cols) {
                2 => 'grid-cols-2',
                3 => 'grid-cols-3',
                4 => 'grid-cols-4',
                default => 'grid-cols-2',
            };
        @endphp
        <div class="grid {{ $gridClass }} items-center gap-2 pt-3 border-t border-base-300 text-sm min-h-10">
            {{-- Likes --}}
            <div class="flex items-center justify-center">
                <form method="POST" action="/bleeps/{{ $bleep->id }}/like" class="like-form inline-flex">
                    @csrf
                    <button type="submit"
                        class="btn btn-ghost btn-xs h-8 min-h-8 leading-none whitespace-nowrap gap-1 group like-btn
                        {{ Auth::check() && $bleep->isLikedBy(Auth::user()) ? 'text-red-600 hover:bg-red-100/50 hover:text-red-600' : 'hover:bg-red-100/50 hover:text-red-600' }}"
                        data-bleep-id="{{ $bleep->id }}">

                        <i data-lucide="heart" class="w-5 h-5 align-middle group-hover:scale-110 transition-transform heart-icon"></i>

                        <span class="inline sm:hidden text-xs like-count align-middle" data-bleep-id="{{ $bleep->id }}">
                            {{ $bleep->likes()->count() }}
                        </span>

                        <span class="hidden sm:inline text-xs like-text whitespace-nowrap align-middle">
                            @if (Auth::check() && $bleep->isLikedBy(Auth::user()))
                                {{ $bleep->likes()->count() }} {{ $bleep->likes()->count() === 1 ? 'Liked' : 'Likes' }}
                            @else
                                {{ $bleep->likes()->count() }} {{ $bleep->likes()->count() === 1 ? 'Like' : 'Likes' }}
                            @endif
                        </span>
                    </button>
                </form>
            </div>

            {{-- Comments --}}
            @if($showCommentsButton)
                <div class="flex items-center justify-center">
                    <button
                        x-data
                        @click.prevent="$dispatch('open-comments', { bleepId: '{{ $bleep->id }}' })"
                        data-bleep-id="{{ $bleep->id }}"
                        class="btn btn-ghost btn-xs h-8 min-h-8 leading-none whitespace-nowrap gap-1 hover:bg-blue-100/50 hover:text-blue-600 transition-colors group comment-btn"
                    >
                        <i data-lucide="message-circle" class="w-5 h-5 align-middle group-hover:scale-110 transition-transform"></i>
                        <span class="inline sm:hidden text-xs align-middle">{{ $bleep->comments()->count() }}</span>
                        <span class="hidden sm:inline text-xs whitespace-nowrap align-middle">
                            {{ $bleep->comments()->count() }} {{ $bleep->comments()->count() === 1 ? 'Comment' : 'Comments' }}
                        </span>
                    </button>
                </div>
            @endif

            {{-- Repost --}}
            @auth
                <div class="flex items-center justify-center">
                    <button type="button" data-bleep-id="{{ $bleep->id }}" data-reposted="{{ $hasReposted ? '1' : '0' }}"

                        class="btn btn-ghost btn-xs h-8 min-h-8 leading-none whitespace-nowrap gap-1 group repost-btn
                            {{ $hasReposted ? 'text-green-700 hover:bg-red-100 hover:text-red-600' : 'hover:bg-green-50 hover:text-green-600 text-gray-500' }}">
                        <i data-lucide="repeat" class="w-5 h-5 align-middle transition-transform duration-200 group-hover:scale-110 repost-icon"></i>
                        <span class="inline sm:hidden text-xs repost-meta-mobile align-middle" data-bleep-id="{{ $bleep->id }}">
                            {{ $totalRepostCount }}
                        </span>
                        <span class="hidden sm:inline text-xs repost-text whitespace-nowrap align-middle" data-bleep-id="{{ $bleep->id }}">
                            <span class="repost-label">
                                <span class="repost-count mr-0.5" data-bleep-id="{{ $bleep->id }}">{{ $totalRepostCount }}</span>
                                <span class="repost-text-label">{{ $hasReposted ? 'Reposted' : 'Repost' }}</span>
                                <span class="unrepost-text-label hidden">Remove</span>
                            </span>
                        </span>
                    </button>
                </div>
            @endauth

            {{-- Share --}}
            <div class="flex items-center justify-center">
                <button type="button" data-bleep-id="{{ $bleep->id }}" title="Share / Copy link"
                    class="btn btn-ghost btn-xs h-8 min-h-8 leading-none whitespace-nowrap gap-1 group share-btn hover:bg-yellow-300/20 hover:text-yellow-600">
                    <i data-lucide="forward" class="w-5 h-5 align-middle transition-transform duration-200 group-hover:scale-110 group-hover:text-yellow-600"></i>
                    <span class="inline sm:hidden text-xs share-count align-middle" data-bleep-id="{{ $bleep->id }}">
                        {{ $shareCount }}
                    </span>
                    <span class="hidden sm:inline text-xs share-text whitespace-nowrap align-middle" data-bleep-id="{{ $bleep->id }}">
                        {{ $shareCount }} {{ $shareCount === 1 ? 'Share' : 'Shares' }}
                    </span>
                </button>
            </div>
        </div>
    </div>

</article>

{{-- Report Modal --}}
<x-modals.posts.report />

@once
    @push('scripts')
        @vite([
            'resources/js/bleep/posts/like.js',
            'resources/js/bleep/posts/media.js',
            'resources/js/bleep/posts/nsfw.js',
            'resources/js/bleep/posts/repost.js',
            'resources/js/bleep/posts/share.js',
            'resources/js/bleep/users/follow.js',
            'resources/js/bleep/modals/mediamodal.js',
            'resources/js/bleep/modals/posts/reports.js',
        ])
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                let activeObserver = null;
                let selectedCard = null;

                // Listen for comment open
                window.addEventListener('open-comments', (e) => {
                    const bleepId = String(e.detail.bleepId);

                    // Clear previous observer
                    if (activeObserver) {
                        activeObserver.disconnect();
                        activeObserver = null;
                    }

                    // Clear previous selected
                    if (selectedCard) {
                        selectedCard.classList.remove('bleep-selected');
                        selectedCard = null;
                    }

                    // Find the clicked bleep card
                    const card = document.querySelector(`[data-bleep-card="${bleepId}"]`);
                    if (!card) return;

                    // Mark as selected
                    card.classList.add('bleep-selected');
                    selectedCard = card;

                    // Set up observer — threshold 0.1 means close when 90% scrolled away
                    activeObserver = new IntersectionObserver((entries) => {
                        entries.forEach(entry => {
                            if (!entry.isIntersecting) {
                                window.dispatchEvent(new CustomEvent('close-comments'));
                            }
                        });
                    }, { threshold: 0.1 });

                    activeObserver.observe(card);
                });

                // Listen for comment close
                window.addEventListener('close-comments', () => {
                    if (activeObserver) {
                        activeObserver.disconnect();
                        activeObserver = null;
                    }

                    if (selectedCard) {
                        selectedCard.classList.remove('bleep-selected');
                        selectedCard = null;
                    }
                });
            });
        </script>
    @endpush
@endonce
