<nav id="app-navbar" class="navbar app-navbar bg-base-100 border-b border-base-200 sticky top-0 z-40 px-2 sm:px-4 gap-2">

    {{-- LEFT: Logo home button + desktop nav buttons --}}
    <div class="navbar-start nav-group nav-group--brand gap-1 min-w-0 flex-1">
        @if (request()->routeIs('home'))
            <span class="btn btn-ghost nav-brand normal-case text-xl gap-2 rounded-xl px-2 shrink-0 cursor-default" aria-current="page" aria-label="Bleep home">
                <img src="{{ asset('Bleep_Icon.png') }}" alt="Bleep Logo" class="w-7 h-7">
                <span class="navbar-brand-text hidden sm:inline font-bold">Bleep</span>
            </span>
        @else
            <a href="{{ route('home') }}" class="btn btn-ghost nav-brand normal-case text-xl gap-2 rounded-xl px-2 shrink-0" aria-label="Bleep home">
                <img src="{{ asset('Bleep_Icon.png') }}" alt="Bleep Logo" class="w-7 h-7">
                <span class="navbar-brand-text hidden sm:inline font-bold">Bleep</span>
            </a>
        @endif

        {{-- Desktop nav links --}}
        <div class="nav-group nav-group--links hidden lg:flex items-center gap-1 min-w-0">
            <x-include.navlinks />
        </div>
    </div>

    {{-- RIGHT: Activity + notifications + profile + mobile menu --}}
    <div class="navbar-end nav-group nav-group--actions gap-1 shrink-0">
        @auth
            <button
                type="button"
                id="activity-drawer-open"
                class="btn btn-ghost btn-circle lg:hidden"
                aria-label="Open announcements and notifications"
                aria-controls="activity-drawer"
                aria-expanded="false"
            >
                <span class="relative inline-flex">
                    <i data-lucide="bell" class="w-5 h-5"></i>
                    <span
                        data-activity-unread-badge
                        class="absolute -top-1.5 -right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-error px-1 text-[10px] font-bold leading-none text-white hidden"
                    ></span>
                </span>
            </button>
        @endauth

        {{-- Desktop bell. On phones/tablets the activity drawer is the single
             notification surface, so the bell dropdown is not duplicated. --}}
        <div class="hidden lg:block">
            <x-notifications.bell />
        </div>

        {{-- Compact mobile avatar (only visible when the navbar collapses) --}}
        @auth
            <a
                href="{{ route('user.profile', ['username' => Auth::user()->username]) }}"
                class="nav-compact-avatar btn btn-ghost btn-circle lg:hidden"
                aria-label="Your profile"
            >
                <span class="avatar">
                    <x-subcomponents.avatar :user="Auth::user()" :size="8" />
                </span>
            </a>
        @endauth

        {{-- Desktop: user profile --}}
        <div class="nav-profile hidden lg:flex items-center">
            <x-include.userprofile />
        </div>

        {{-- Mobile: hamburger --}}
        <button id="hor-mobile-menu-btn" class="btn btn-ghost btn-circle lg:hidden" aria-label="Open menu">
            <i data-lucide="menu" class="w-5 h-5"></i>
        </button>
    </div>

</nav>

<x-include.compactnav />

@auth
    <x-notifications.activity />
@endauth

{{-- Mobile Nav --}}
<x-include.mobilenav />
