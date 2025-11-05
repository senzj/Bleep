{{-- scripts --}}
@vite(['resources/js/bleep/likes'])

{{-- Props --}}
@props(['bleep'])

<article class="bg-base-100 rounded-lg p-4 shadow-sm hover:shadow-md transition-shadow duration-200">

    {{-- Header: Avatar + Author Info --}}
    <div class="flex gap-3 mb-4">

        {{-- Avatar --}}
        @if($bleep->user)
            <div class="avatar shrink-0">
                <div class="size-12 rounded-full">
                    <img src="https://avatars.laravel.cloud/{{ urlencode($bleep->user->email) }}"
                         alt="{{ $bleep->user->name }}'s avatar" />
                </div>
            </div>
        @else
            <div class="avatar placeholder shrink-0">
                <div class="size-12 rounded-full ring ring-base-300 ring-offset-base-100 ring-offset-2 bg-base-300">
                    <span class="text-xl">?</span>
                </div>
            </div>
        @endif

        {{-- Author Info + Actions --}}
        <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 min-w-0">
                    <span class="font-semibold text-sm truncate">{{ $bleep->user->dname ?? 'Anonymous' }}</span>
                    @if($bleep->user->username)
                        <span class="text-base-content/60 text-sm truncate">{{ "@" . $bleep->user->username }}</span>
                    @endif
                    <span class="text-base-content/40">·</span>
                    <span class="text-sm whitespace-nowrap">{{ $bleep->created_at->diffForHumans() }}</span>
                    @if ($bleep->updated_at->gt($bleep->created_at->addSeconds(5)))
                        <span class="text-base-content/60">·</span>
                        <span class="text-sm text-base-content/60 italic">edited</span>
                    @endif
                </div>

                {{-- Action Dropdown --}}
                <div class="dropdown dropdown-end">
                    <button tabindex="0" class="btn btn-ghost btn-xs btn-circle hover:bg-base-300">
                        <i data-lucide="more-vertical" class="w-4 h-4"></i>
                    </button>

                    <ul tabindex="0"
                        class="dropdown-content z-1 shadow-lg bg-base-100 rounded-xl w-52 border border-base-200 p-2 space-y-1">

                        @can('update', $bleep)
                            <li>
                                <a href="/bleeps/{{ $bleep->id }}/edit"
                                class="flex items-center gap-2 px-3 py-2 text-sm rounded-md hover:bg-gray-50 transition">
                                    <i data-lucide="pencil" class="w-4 h-4 text-gray-500"></i>
                                    <span>Edit</span>
                                </a>
                            </li>

                            <li>
                                <form method="POST" action="/bleeps/{{ $bleep->id }}">
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

            {{-- Edited Badge --}}
            @if ($bleep->updated_at->gt($bleep->created_at->addSeconds(5)))
                <div class="text-xs text-base-content/50 mt-2">
                    <span class="badge badge-sm badge-ghost">edited {{ $bleep->updated_at->diffForHumans() }}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Message Content --}}
    <div class="mb-4">
        <p class="text-base leading-relaxed text-base-content">{{ $bleep->message }}</p>
    </div>

    {{-- Engagement Footer --}}
    <div class="flex items-center justify-between pt-3 border-t border-base-200">
        {{-- Likes --}}
        <form method="POST" action="/bleeps/{{ $bleep->id }}/like" class="like-form inline">
            @csrf
            <button type="submit"
                class="btn btn-ghost btn-xs gap-1 hover:bg-red-100/50 hover:text-red-600 transition-colors group like-btn
                {{ Auth::check() && $bleep->isLikedBy(Auth::user()) ? 'text-red-600' : '' }}"
                data-bleep-id="{{ $bleep->id }}">
                <i data-lucide="heart" class="w-4 h-4 group-hover:scale-110 transition-transform heart-icon"></i>
                <span class="hidden sm:inline text-xs like-count">
                    {{ $bleep->likes()->count() }} {{ $bleep->likes()->count() === 1 ? 'Like' : 'Likes' }}
                </span>
            </button>
        </form>

        {{-- Replies --}}
        <button class="btn btn-ghost btn-xs gap-1 hover:bg-blue-100/50 hover:text-blue-600 transition-colors group">
            <i data-lucide="message-circle" class="w-4 h-4 group-hover:scale-110 transition-transform"></i>

            <span class="hidden sm:inline text-xs">12 Reply</span>
        </button>

        {{-- Shares --}}
        <button class="btn btn-ghost btn-xs gap-1 hover:bg-green-100/50 hover:text-green-600 transition-colors group">
            <i data-lucide="forward" class="w-4 h-4 group-hover:scale-110 transition-transform"></i>
            <span class="hidden sm:inline text-xs">130 Share</span>
        </button>

        {{-- Views --}}
        <div class="flex items-center gap-1 text-base-content/60 text-xs">
            <i data-lucide="eye" class="w-4 h-4"></i>
            <span>5.2k Views</span>
        </div>
    </div>
</article>
