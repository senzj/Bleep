{{-- scripts --}}
@vite([
    'resources/js/bleep/likes',
    'resources/js/bleep/comments'
])

{{-- Props --}}
@props(['bleep'])

@php
    $isAnonymous = (bool) $bleep->is_anonymous;

    // For anonymous bleeps, generate a random two-word name (e.g. "rampage berry").
    // Username remains @Anonymous.
    if ($isAnonymous) {
        $firstParts = [
            'Rampage','Clam','Sunny','Brave','Sneaky','Mighty','Quiet','Spicy','Fuzzy','Neon',
            'Turbo','Happy','Icy','Rusty','Velvet','Silver','Crimson','Jolly','Gloomy','Zen'
        ];

        $secondParts = [
            'Berry','Banana','Fox','Tiger','Pancake','Nimbus','Penguin','Pixel','Breeze','Blossom',
            'Rocket','Dandelion','Echo','Shadow','Nova','Sailor','Comet','Mango','Quartz','Marsh'
        ];

        $first = $firstParts[array_rand($firstParts)];
        $second = $secondParts[array_rand($secondParts)];

        // Lowercase to match examples like "rampage berry"
        $displayName = ucwords($first . ' ' . $second);
        $username = '@anonymous';
    } else {
        $displayName = $bleep->user->dname ?? 'Unknown';
        $username = "@" . ($bleep->user->username ?? 'Unknown');
    }
@endphp

<article class="bg-base-100 rounded-lg p-4 shadow-md hover:shadow-lg transition-shadow duration-200">

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
                {{-- Left: Name and username --}}
                <div class="flex items-start space-x-3">

                    {{-- Name and username --}}
                    <div>
                        <div class="font-semibold text-gray-900>
                            <span class="font-semibold text-sm truncate">{{ $displayName }}</span>

                        </div>

                        <!-- Username -->
                        @if($bleep->user->username)
                            <div class="text-gray-500 text-sm">
                                <span class="text-base-content/60 text-sm truncate">{{ $username }}</span>
                            </div>
                        @endif

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
            </div>
        </div>
    </div>

    {{-- Message Content --}}
    <div class="mb-4">
        <p class="text-base leading-relaxed text-base-content">{{ $bleep->message }}</p>

        {{-- date and time created --}}
        <div class="text-xs text-gray-400 mt-5">
            {{ $bleep->created_at->timezone(Auth::user()->timezone ?? 'UTC')->format('F j, Y \a\t g:i A') }}
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
                <i data-lucide="heart" class="w-4 h-4 group-hover:scale-110 transition-transform heart-icon"></i>

                {{-- Count on mobile, text on desktop --}}
                <span class="inline sm:hidden text-xs like-count">
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
        <button class="btn btn-ghost btn-xs gap-1 hover:bg-blue-100/50 hover:text-blue-600 transition-colors group comment-btn"
            data-bleep-id="{{ $bleep->id }}">
            <i data-lucide="message-circle" class="w-4 h-4 group-hover:scale-110 transition-transform"></i>
            {{-- Mobile: number only / Desktop: text label --}}
            <span class="inline sm:hidden text-xs">{{ $bleep->comments()->count() }}</span>
            <span class="hidden sm:inline text-xs">
                {{ $bleep->comments()->count() }} {{ $bleep->comments()->count() === 1 ? 'Comment' : 'Comments' }}
            </span>
        </button>

        {{-- Shares --}}
        <button class="btn btn-ghost btn-xs gap-1 hover:bg-green-100/50 hover:text-green-600 transition-colors group">
            <i data-lucide="forward" class="w-4 h-4 group-hover:scale-110 transition-transform"></i>
            <span class="inline sm:hidden text-xs">130</span>
            <span class="hidden sm:inline text-xs">130 Share</span>
        </button>

        {{-- Views --}}
        <div class="flex items-center gap-1 text-base-content/60 text-xs">
            <i data-lucide="eye" class="w-4 h-4"></i>
            <span>5.2k</span>
            <span class="hidden sm:inline">Views</span>
        </div>
    </div>


</article>

<script>
function autoGrow(element) {
    element.style.height = "auto";
    element.style.height = (element.scrollHeight) + "px";
}
</script>
