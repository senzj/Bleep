{{-- Vertical Sidebar Navigation --}}
{{-- Desktop: Static sidebar in document flow --}}
<aside id="vertical-nav-desktop" class="hidden lg:flex flex-col w-54 h-screen sticky top-0 bg-base-200/95 shadow-lg shrink-0">
    {{-- Logo --}}
    <div class="p-4 border-b border-base-200">
        <a href="/" class="flex items-center gap-2 text-xl font-bold rounded-lg">
            <img src="{{ asset('Bleep_Icon.png') }}" alt="Bleep Logo" class="w-8 h-8">
            <span class="sidebar-text">Bleep</span>
        </a>
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
{{-- Mobile Floating Menu Button --}}
<button id="hor-mobile-menu-btn" class="cursor-pointer fixed bottom-8 left-8 z-8 lg:hidden bg-base-100 border border-base-300 rounded-lg p-3 shadow-xl hover:bg-base-200 transition-colors" aria-label="Open menu">
    <i data-lucide="menu" class="w-5 h-5"></i>
</button>

<x-include.mobilenav />
