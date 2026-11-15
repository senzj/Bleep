{{-- Vertical Sidebar Navigation --}}
{{-- Desktop: Static sidebar in document flow --}}
<aside id="vertical-nav-desktop" class="hidden lg:flex flex-col w-58 h-screen sticky top-0 bg-base-200/95 shadow-lg shrink-0">
    {{-- Logo --}}
    <div class="flex items-center justify-between gap-2 border-b border-base-200 p-4">
        <a href="{{ route('home') }}" class="flex items-center gap-2 text-xl font-bold rounded-lg" aria-current="{{ request()->routeIs('home') ? 'page' : 'false' }}">
            <img src="{{ asset('Bleep_Icon.png') }}" alt="Bleep Logo" class="w-8 h-8">
            <span class="sidebar-text">Bleep</span>
        </a>

        {{-- <x-notifications.bell /> --}}
    </div>

    {{-- Navigation Links --}}
    <nav class="flex flex-col p-2 space-y-1 flex-1 overflow-y-auto">
        <x-include.navlinks />
    </nav>

    {{-- User profile --}}
    <div class="p-2">
        <x-include.userprofile />
    </div>

</aside>

{{-- Mobile Nav --}}
{{-- Phones/tablets get the single bottom-right menu button from the mobile
     nav component; no floating activity bell is needed because notifications
     now live inside the slide-over menu. --}}

@auth
    <x-notifications.activity />
@endauth

<x-include.mobilenav />
