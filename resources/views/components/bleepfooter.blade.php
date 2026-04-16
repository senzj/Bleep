@props([
    'bleep',
    'showCommentsButton' => true,
    'hasReposted' => false,
    'totalRepostCount' => 0,
    'shareCount' => 0,
    'gridClass' => 'grid-cols-2',
])

@php
    $showCommentsButton = filter_var($showCommentsButton, FILTER_VALIDATE_BOOLEAN);
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
