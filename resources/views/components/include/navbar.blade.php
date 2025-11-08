<nav class="navbar bg-base-100">
    <div class="navbar-start">
        <a href="/" class="btn btn-ghost text-2xl">Bleep</a>
    </div>

    <div class="navbar-end gap-2">
        @auth
            {{-- Profile section --}}
            <button type="button" class="flex items-center gap-3 px-3 py-2 shadow-sm rounded-lg hover:bg-base-200 transition-colors cursor-pointer">
                {{-- Avatar --}}
                <div class="avatar shrink-0">
                    <x-subcomponents.avatar :user="Auth::user()" :size="10" />
                </div>

                {{-- User Info --}}
                <div class="text-left leading-tight">
                    <p class="font-semibold text-sm text-base-500">
                        {{ Auth::user()->dname }}
                    </p>
                    <p class="text-xs text-gray-500">
                        {{'@' . Auth::user()->username }}
                    </p>
                </div>
            </button>

            <form method="POST" action="/logout" class="inline">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm">Logout</button>
            </form>
        @else
            <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">Sign In</a>
            <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Sign Up</a>
        @endauth
    </div>
</nav>
