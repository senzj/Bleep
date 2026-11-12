{{-- Vertical Sidebar Navigation --}}
{{-- Desktop: Static sidebar in document flow --}}
<aside id="vertical-nav-desktop" class="hidden lg:flex flex-col w-54 h-screen sticky top-0 bg-base-200/95 shadow-lg shrink-0">
    {{-- Logo --}}
    <div class="flex items-center justify-between gap-2 border-b border-base-200 p-4">
        <a href="/" class="flex items-center gap-2 text-xl font-bold rounded-lg">
            <img src="{{ asset('Bleep_Icon.png') }}" alt="Bleep Logo" class="w-8 h-8">
            <span class="sidebar-text">Bleep</span>
        </a>

        <x-notifications.bell />
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
{{-- Mobile Floating Activity Button --}}
<button id="activity-drawer-open" class="cursor-pointer fixed bottom-24 left-8 z-8 lg:hidden bg-base-100 border border-base-300 rounded-lg p-3 shadow-xl hover:bg-base-200 transition-colors" aria-label="Open announcements and notifications" aria-controls="activity-drawer" aria-expanded="false">
    <span class="relative inline-flex">
        <i data-lucide="bell" class="w-5 h-5"></i>
        <span data-activity-unread-badge class="absolute -top-1.5 -right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-error px-1 text-[10px] font-bold leading-none text-white hidden"></span>
    </span>
</button>

<button id="hor-mobile-menu-btn" class="cursor-pointer fixed bottom-8 left-8 z-8 lg:hidden bg-base-100 border border-base-300 rounded-lg p-3 shadow-xl hover:bg-base-200 transition-colors" aria-label="Open menu">
    <i data-lucide="menu" class="w-5 h-5"></i>
</button>

@auth
    <x-notifications.activity />
@endauth

<x-include.mobilenav />
