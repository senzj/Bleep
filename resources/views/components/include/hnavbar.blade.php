<nav class="navbar bg-base-100 sticky top-0 z-40">

    {{-- LEFT --}}
    <div class="navbar-start md:hidden">
        <button id="hor-mobile-menu-btn" class="btn btn-ghost lg:hidden" aria-label="Open menu">
            <i data-lucide="menu" class="w-5 h-5"></i>
        </button>
    </div>

    {{-- CENTER --}}
    <div class="absolute left-1/2 -translate-x-1/2 lg:static lg:translate-x-0 flex items-center lg:items-start">
        <a href="/" class="btn btn-ghost text-2xl gap-2 normal-case flex items-center">
            <img src="{{ asset('Bleep_Icon.png') }}" alt="Bleep Logo" class="w-6 h-6 sm:w-8 sm:h-8">
            <span class="hidden sm:inline">Bleep</span>
        </a>

        {{-- Desktop nav links --}}
        <div class="hidden lg:flex items-center gap-2 ml-4">
            <div class="divider divider-horizontal"></div>
            <x-include.navlinks />
        </div>
    </div>

    {{-- RIGHT --}}
    <div class="navbar-end hidden lg:flex gap-2 ml-auto w-45">
        <x-include.userprofile />
    </div>

</nav>

{{-- Mobile Nav --}}
<x-include.mobilenav />
