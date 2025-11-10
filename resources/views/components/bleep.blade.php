{{-- scripts --}}
@vite([
    'resources/js/bleep/posts/comment.js',
    'resources/js/bleep/posts/like.js',
    'resources/js/bleep/posts/media.js',
    'resources/js/bleep/posts/repost.js',
    'resources/js/bleep/posts/share.js',
    'resources/js/bleep/users/follow.js',
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
    $totalRepostCount = \App\Models\Repost::where('bleep_id', $bleep->id)->count();
    $totalShareCount = $shareCount + $totalRepostCount;
    $hasReposted = auth()->check() ? \App\Models\Repost::where('bleep_id', $bleep->id)->where('user_id', auth()->id())->exists() : false;

    // Get reposts from followed users (for the repost tag)
    $followedReposts = collect();
    if (Auth::check()) {
        $followedReposts = isset($bleep->followedReposts)
            ? $bleep->followedReposts
            : \App\Models\Repost::visibleToUser(Auth::id(), $bleep->id);
    }

    $followedRepostCount = $followedReposts->count();

    $userProfileLink = $isAnonymous ? "#" : route('user.profile', ['username' => $bleep->user->username]);
@endphp

<article class="bg-base-100 rounded-lg p-4 shadow-md hover:shadow-lg transition-shadow duration-200">

    {{-- Repost Tag (if reposted by followed users) --}}
    @if($followedRepostCount > 0)
        <div class="flex items-center gap-2 mb-3 text-xs text-base-content/60">
            <i data-lucide="repeat" class="w-4 h-4"></i>

            @if($followedRepostCount === 1)
                {{-- Single reposter --}}
                <span>
                    <a href="{{ $userProfileLink }}" class="font-semibold hover:underline">{{ $followedReposts->first()->user->username }}</a> reposted
                </span>
            @elseif($followedRepostCount === 2)
                {{-- Two reposters --}}
                <span>
                    <a href="{{ $userProfileLink }}" class="font-semibold hover:underline">{{ $followedReposts->first()->user->username }}</a> and
                    <a href="{{ $userProfileLink }}" class="font-semibold hover:underline">{{ $followedReposts->skip(1)->first()->user->username }}</a> reposted
                </span>
            @else
                {{-- Multiple reposters - show tooltip --}}
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
                                                <img src="https://avatars.laravel.cloud/{{ urlencode($repost->user->email) }}"
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

        {{-- Author Info + Actions --}}
        <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-2">
                {{-- Left: Avatar, Name and username --}}
                <div class="flex items-start gap-3">

                    {{-- Avatar + Name/Username - Grouped hover area --}}
                    <a href="{{ $userProfileLink }}" class="group flex items-start gap-3">
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

                        <div class="min-w-0">
                            {{-- Display name --}}
                            <div class="font-semibold text-base-content">
                                <span class="text-sm truncate bleep-display-name group-hover:underline" data-bleep-id="{{ $bleep->id }}">{{ $displayName }}</span>
                            </div>

                            {{-- Username --}}
                            <div class="text-base-content/60 text-xs">
                                <span class="truncate bleep-username group-hover:underline" data-bleep-id="{{ $bleep->id }}">{{ $username }}</span>
                            </div>
                        </div>
                    </a>

                    {{-- Follow/Unfollow Button --}}
                    @if (! $isAnonymous && $bleep->user && Auth::check() && Auth::id() !== $bleep->user->id)
                        @php
                            $isFollowing = Auth::user()->isFollowing($bleep->user);
                        @endphp
                        <button type="button" data-user-id="{{ $bleep->user->id }}" data-following="{{ $isFollowing ? '1' : '0' }}"
                            class="cursor-pointer flex items-center gap-1.5 text-xs font-medium group follow-btn rounded-full px-2.5 py-1 transition-all duration-200 ease-out
                                {{ $isFollowing ? 'bg-blue-100 text-blue-700 shadow-sm hover:bg-red-100 hover:text-red-600' : 'bg-gray-200 text-gray-700 hover:bg-blue-50 hover:text-blue-600 shadow-sm' }}">

                            <i data-lucide="{{ $isFollowing ? 'user-round-check' : 'user-round-plus' }}" class="w-4 h-4 transition-transform duration-200 group-hover:scale-110 follow-icon"></i>
                            <span class="follow-text">
                                {{ $isFollowing ? 'Following' : 'Follow' }}
                            </span>
                            <span class="unfollow-text hidden">
                                Unfollow
                            </span>
                        </button>
                    @endif
                </div>

                {{-- Right: Time posted & Actions --}}
                <div class="flex items-start gap-3">
                    <div class="text-base-content/60 text-xs whitespace-nowrap">
                        <div>
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
                            class="dropdown-content z-[1] shadow-lg bg-base-100 rounded-xl w-52 border border-base-200 p-2 space-y-1">

                            @can('update', $bleep)
                                <li>
                                    <button type="button"
                                        class="cursor-pointer flex items-center gap-2 w-full px-3 py-2 text-sm text-base-content rounded-md hover:bg-base-200 transition edit-bleep-btn"
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
            @if(!empty($bleep->message))
                {{ $bleep->message }}
            @endif
        </p>

        @php
            $mediaItems = ($bleep->media ?? collect())->take(4);
        @endphp

        @if($mediaItems->count() > 0)
            @php $count = $mediaItems->count(); @endphp

            <div class="mt-2 overflow-hidden rounded-xl border border-base-300" data-bleep-media>
                {{-- ONE IMAGE --}}
                @if ($count === 1)
                    <div class="flex items-center justify-center bg-base-200">
                        @php $m = $mediaItems->first(); @endphp
                        <div class="relative cursor-pointer group"
                             data-media-index="0"
                             data-media-type="{{ $m->type }}"
                             data-media-src="{{ asset('storage/'.$m->path) }}"
                             data-media-alt="{{ $m->original_name }}"
                             data-media-mime="{{ $m->mime_type }}">
                            @if($m->type === 'image')
                                <img src="{{ asset('storage/'.$m->path) }}"
                                    alt="{{ $m->original_name }}"
                                    class="max-h-96 w-auto rounded-lg object-cover"
                                    loading="lazy">
                            @else
                                <video class="max-h-96 w-auto rounded-lg object-contain" controls preload="metadata">
                                    <source src="{{ asset('storage/'.$m->path) }}" type="{{ $m->mime_type }}">
                                </video>
                            @endif
                        </div>
                    </div>

                {{-- TWO IMAGES --}}
                @elseif ($count === 2)
                    <div class="grid grid-cols-2 gap-1 bg-base-200">
                        @foreach($mediaItems as $index => $m)
                            <div class="flex items-center justify-center overflow-hidden">
                                <div class="relative cursor-pointer group w-full"
                                     data-media-index="{{ $index }}"
                                     data-media-type="{{ $m->type }}"
                                     data-media-src="{{ asset('storage/'.$m->path) }}"
                                     data-media-alt="{{ $m->original_name }}"
                                     data-media-mime="{{ $m->mime_type }}">
                                    @if($m->type === 'image')
                                        <img src="{{ asset('storage/'.$m->path) }}"
                                            alt="{{ $m->original_name }}"
                                            class="max-h-64 w-full object-cover rounded-lg"
                                            loading="lazy">
                                    @else
                                        <video class="max-h-64 w-full rounded-lg object-contain" controls preload="metadata">
                                            <source src="{{ asset('storage/'.$m->path) }}" type="{{ $m->mime_type }}">
                                        </video>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                {{-- THREE IMAGES --}}
                @elseif ($count === 3)
                    <div class="grid grid-cols-3 grid-rows-2 gap-1 bg-base-200">
                        {{-- Left side: image 1 (top) --}}
                        <div class="col-span-1 row-span-1">
                            @php $m = $mediaItems[0]; @endphp
                            <div class="relative cursor-pointer group h-full"
                                 data-media-index="0"
                                 data-media-type="{{ $m->type }}"
                                 data-media-src="{{ asset('storage/'.$m->path) }}"
                                 data-media-alt="{{ $m->original_name }}"
                                 data-media-mime="{{ $m->mime_type }}">
                                @if($m->type === 'image')
                                    <img src="{{ asset('storage/'.$m->path) }}"
                                        alt="{{ $m->original_name }}"
                                        class="w-full h-40 object-cover rounded-lg"
                                        loading="lazy">
                                @else
                                    <video class="w-full h-40 object-contain rounded-lg" controls preload="metadata">
                                        <source src="{{ asset('storage/'.$m->path) }}" type="{{ $m->mime_type }}">
                                    </video>
                                @endif
                            </div>
                        </div>

                        {{-- Left side: image 2 (bottom) --}}
                        <div class="col-span-1 row-span-1">
                            @php $m = $mediaItems[1]; @endphp
                            <div class="relative cursor-pointer group h-full"
                                 data-media-index="1"
                                 data-media-type="{{ $m->type }}"
                                 data-media-src="{{ asset('storage/'.$m->path) }}"
                                 data-media-alt="{{ $m->original_name }}"
                                 data-media-mime="{{ $m->mime_type }}">
                                @if($m->type === 'image')
                                    <img src="{{ asset('storage/'.$m->path) }}"
                                        alt="{{ $m->original_name }}"
                                        class="w-full h-40 object-cover rounded-lg"
                                        loading="lazy">
                                @else
                                    <video class="w-full h-40 object-contain rounded-lg" controls preload="metadata">
                                        <source src="{{ asset('storage/'.$m->path) }}" type="{{ $m->mime_type }}">
                                    </video>
                                @endif
                            </div>
                        </div>

                        {{-- Right side: image 3 spans both rows --}}
                        <div class="col-span-2 row-span-2">
                            @php $m = $mediaItems[2]; @endphp
                            <div class="relative cursor-pointer group h-full"
                                 data-media-index="2"
                                 data-media-type="{{ $m->type }}"
                                 data-media-src="{{ asset('storage/'.$m->path) }}"
                                 data-media-alt="{{ $m->original_name }}"
                                 data-media-mime="{{ $m->mime_type }}">
                                @if($m->type === 'image')
                                    <img src="{{ asset('storage/'.$m->path) }}"
                                        alt="{{ $m->original_name }}"
                                        class="w-full h-full object-cover rounded-lg"
                                        loading="lazy">
                                @else
                                    <video class="w-full h-full object-contain rounded-lg" controls preload="metadata">
                                        <source src="{{ asset('storage/'.$m->path) }}" type="{{ $m->mime_type }}">
                                    </video>
                                @endif
                            </div>
                        </div>
                    </div>

                {{-- FOUR OR MORE (simple grid fallback) --}}
                @else
                    <div class="grid grid-cols-2 gap-1 bg-base-200">
                        @foreach($mediaItems as $index => $m)
                            <div class="relative overflow-hidden">
                                <div class="relative cursor-pointer group"
                                     data-media-index="{{ $index }}"
                                     data-media-type="{{ $m->type }}"
                                     data-media-src="{{ asset('storage/'.$m->path) }}"
                                     data-media-alt="{{ $m->original_name }}"
                                     data-media-mime="{{ $m->mime_type }}">
                                    @if($m->type === 'image')
                                        <img src="{{ asset('storage/'.$m->path) }}"
                                            alt="{{ $m->original_name }}"
                                            class="w-full h-40 object-cover rounded-lg"
                                            loading="lazy">
                                    @else
                                        <video class="w-full h-40 rounded-lg object-contain" controls preload="metadata">
                                            <source src="{{ asset('storage/'.$m->path) }}" type="{{ $m->mime_type }}">
                                        </video>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif


        {{-- date and time created + views --}}
        <div class="text-xs text-gray-400 mt-5 flex items-center justify-between">
            {{-- Left: Date posted --}}
            <span class="text-xs">
                {{ $bleep->created_at->timezone(Auth::user()->timezone ?? 'UTC')->format('M j, Y \| g:i:s A') }}
            </span>

            {{-- Right: Views --}}
            <div class="flex items-center gap-1 text-base-content/60 text-xs">
                <span>5.2k</span>
                <i data-lucide="banana" class="w-4 h-4"></i>
            </div>
        </div>
    </div>

    {{-- Engagement Footer --}}
    @if (Auth::user())
        <div class="grid grid-cols-4 gap-2 pt-3 border-t border-base-300 text-sm">
    @else
        <div class="grid grid-cols-3 gap-2 pt-3 border-t border-base-300 text-sm">
    @endif

        {{-- Likes --}}
        <div class="flex justify-center">
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
        </div>

        {{-- Comments --}}
        <div class="flex justify-center">
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
        </div>

        {{-- Repost --}}
        @auth
            <div class="flex justify-center">
                <button type="button" data-bleep-id="{{ $bleep->id }}" data-reposted="{{ $hasReposted ? '1' : '0' }}"
                    class="cursor-pointer flex items-center gap-2 text-sm font-medium group repost-btn rounded-full px-3 py-1.5 transition-all duration-200 ease-out
                        {{ $hasReposted ? 'bg-green-100 text-green-700 shadow-sm hover:bg-red-100 hover:text-red-600' : 'hover:bg-green-50 hover:text-green-600 text-gray-500' }}">
                    <i data-lucide="repeat" class="w-5 h-5 transition-transform duration-200 group-hover:scale-110 repost-icon"></i>

                    {{-- Mobile: compact repost count --}}
                    <span class="inline sm:hidden text-xs repost-meta-mobile" data-bleep-id="{{ $bleep->id }}">
                        {{ $totalRepostCount }}
                    </span>

                    {{-- Desktop: full text + count --}}
                    <span class="hidden sm:inline text-xs repost-text" data-bleep-id="{{ $bleep->id }}">
                        <span class="repost-label">
                            <span class="repost-count mr-0.5" data-bleep-id="{{ $bleep->id }}">{{ $totalRepostCount }} </span>
                            <span class="repost-text-label">{{ $hasReposted ? 'Reposted' : ($totalRepostCount === 1 ? 'Repost' : 'Reposts') }}</span>
                            <span class="unrepost-text-label hidden">Remove Repost</span>
                        </span>
                    </span>
                </button>
            </div>
        @endauth

        {{-- Share --}}
        <div class="flex justify-center">
            <button
                type="button"
                data-bleep-id="{{ $bleep->id }}"
                title="Share / Copy link"
                class="cursor-pointer flex items-center gap-2 text-sm font-medium group share-btn rounded-full px-3 py-1.5
                    transition-all duration-200 ease-out
                    hover:bg-yellow-300/20 hover:text-yellow-600">

                <i data-lucide="forward" class="w-5 h-5 transition-transform duration-200 group-hover:scale-110 group-hover:text-yellow-600"></i>

                {{-- show only actual shares (not reposts) --}}
                <span class="inline sm:hidden text-xs share-count" data-bleep-id="{{ $bleep->id }}">
                    {{ $shareCount }}
                </span>
                <span class="hidden sm:inline text-xs share-text" data-bleep-id="{{ $bleep->id }}">
                    {{ $shareCount }} {{ $shareCount === 1 ? 'Share' : 'Shares' }}
                </span>
            </button>
        </div>
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

{{-- Media View Modal --}}
<x-subcomponents.bleeps.mediamodal />


@push('script')
    <script>
        function autoGrow(element) {
            element.style.height = "auto";
            element.style.height = (element.scrollHeight) + "px";
        }
    </script>
@endpush
