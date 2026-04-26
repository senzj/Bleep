<nav class="navbar bg-base-100 border-b border-base-200 sticky top-0 z-40 px-4">

    {{-- LEFT: Logo --}}
    <div class="navbar-start">
        <a href="/" class="btn btn-ghost normal-case text-xl gap-2 rounded-lg">
            <img src="{{ asset('Bleep_Icon.png') }}" alt="Bleep Logo" class="w-7 h-7">
            <span class="hidden sm:inline font-bold">Bleep</span>
        </a>
    </div>

    {{-- CENTER: Desktop nav links --}}
    <div class="navbar-center hidden lg:flex gap-2">
        <x-include.navlinks />
    </div>

    {{-- RIGHT: Profile + mobile hamburger --}}
    <div class="navbar-end gap-2">
        {{-- Desktop: user profile --}}
        <div class="hidden lg:flex items-center min-w-48">
            <x-include.userprofile />
        </div>

        {{-- Mobile: hamburger --}}
        <button id="hor-mobile-menu-btn" class="btn btn-ghost lg:hidden" aria-label="Open menu">
            <i data-lucide="menu" class="w-5 h-5"></i>
        </button>
    </div>

</nav>

{{-- Mobile Nav --}}
<x-include.mobilenav />
