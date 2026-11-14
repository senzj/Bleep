<nav id="app-navbar" class="navbar app-navbar bg-base-100 border-b border-base-200 sticky top-0 z-40 px-2 sm:px-4 gap-2">

    {{-- LEFT: Logo home button + desktop nav buttons --}}
    <div class="navbar-start nav-group nav-group--brand gap-1 min-w-0 flex-1">
        <a href="{{ route('home') }}" class="btn btn-ghost nav-brand normal-case text-xl gap-2 rounded-xl px-2 shrink-0" aria-current="{{ request()->routeIs('home') ? 'page' : 'false' }}" aria-label="Bleep home">
            <img src="{{ asset('Bleep_Icon.png') }}" alt="Bleep Logo" class="w-7 h-7">
            <span class="navbar-brand-text hidden sm:inline font-bold">Bleep</span>
        </a>

        {{-- Desktop nav links --}}
        <div class="nav-group nav-group--links hidden lg:flex items-center gap-1 min-w-0">
            <x-include.navlinks />
        </div>
    </div>

    {{-- RIGHT: notifications + profile --}}
    <div class="navbar-end nav-group nav-group--actions gap-1 shrink-0">
        {{-- Desktop bell. On phones/tablets notifications live in the mobile
             slide-over menu, so the bell dropdown is not duplicated. --}}
        <div class="hidden lg:block">
            <x-notifications.bell />
        </div>

        {{-- Desktop: user profile --}}
        <div class="nav-profile hidden lg:flex items-center">
            <x-include.userprofile />
        </div>
    </div>

</nav>

<x-include.compactnav />

@auth
    <x-notifications.activity />
@endauth

{{-- Mobile Nav --}}
<x-include.mobilenav />
