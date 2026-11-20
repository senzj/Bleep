@php
    $navFabPosition = 'bottom-right';
    $navMobileMode = 'fab';
    if (Auth::check()) {
        $navFabPosition = Auth::user()->getNavFabPosition();
        $navMobileMode = Auth::user()->getNavMobileMode();
    }

    // Chat owns the whole viewport on phones, so it hides the app-level mobile
    // navigation and provides its own way back to the feed.
    $showMobileNav = ! request()->routeIs('messages', 'chat.conversation');

    $drawerSide = str_ends_with($navFabPosition, 'left') ? 'left' : 'right';
    $drawerHiddenClass = $drawerSide === 'right' ? 'translate-x-full' : '-translate-x-full';
@endphp

@if ($showMobileNav && $navMobileMode === 'bottom' && Auth::check())
    {{-- Bottom navigation bar: every primary section plus the full menu. --}}
    <nav
        id="mobile-nav-strip"
        class="mobile-nav-strip lg:hidden fixed inset-x-0 bottom-0 z-40 border-t border-base-200 bg-base-100/95 shadow-[0_-10px_30px_-20px_rgba(0,0,0,0.6)] backdrop-blur"
        aria-label="Quick navigation"
    >
        <div class="mobile-nav-strip__track flex items-stretch gap-1 overflow-x-auto px-2 py-1.5">
            <x-include.navlinks />

            <button
                id="mobile-strip-menu-btn"
                type="button"
                class="nav-link flex flex-col items-center justify-center rounded-sm bg-base-100/70 hover:bg-base-300/80 transition-colors"
                aria-label="Open menu"
                aria-controls="hor-mobile-drawer"
                aria-expanded="false"
            >
                <i data-lucide="menu" class="w-5 h-5 shrink-0"></i>
                <span class="sidebar-text">Menu</span>
            </button>
        </div>
    </nav>
@endif

@if ($showMobileNav && $navMobileMode === 'fab')
    {{-- Mobile slide-over trigger --}}
    <button
        id="mobile-menu-btn"
        type="button"
        class="nav-fab nav-fab--{{ $navFabPosition }} lg:hidden fixed z-40 btn btn-primary btn-circle shadow-xl h-13 w-13"
        aria-label="Open menu"
        aria-controls="hor-mobile-drawer"
        aria-expanded="false"
    >
        <i data-lucide="menu" class="w-6 h-6"></i>
    </button>
@endif

@if ($showMobileNav)
<div id="hor-mobile-overlay" class="fixed inset-0 bg-black/50 z-50 hidden lg:hidden transition-opacity duration-300" aria-hidden="true"></div>

<aside
    id="hor-mobile-drawer"
    data-side="{{ $drawerSide }}"
    class="lg:hidden fixed top-0 h-full w-60 bg-base-300/95 shadow-xl z-50 transform transition-transform duration-300 ease-in-out flex flex-col {{ $drawerSide === 'right' ? 'right-0' : 'left-0' }} {{ $drawerHiddenClass }}"
    aria-hidden="true"
>
    {{-- Header --}}
    <div class="flex items-center justify-between p-4 border-b border-base-200">
        <a href="{{ route('home') }}" class="flex items-center gap-2 text-xl font-bold" aria-current="{{ request()->routeIs('home') ? 'page' : 'false' }}">
            <img src="{{ asset('Bleep_Icon.png') }}" alt="Bleep Logo" class="w-8 h-8">
            <span>Bleep</span>
        </a>
        <button id="hor-mobile-close-btn" class="btn btn-ghost btn-sm btn-circle" aria-label="Close menu">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>

    {{-- Navigation Links --}}
    <nav class="flex flex-col p-4 space-y-1 flex-1 overflow-y-auto">
        <x-include.navlinks />

        @auth
            @php
                $drawerUnreadCount = once(fn () => app(\App\Services\Notifications\NotificationService::class)->unreadCount(auth()->user()));
            @endphp

            <a
                href="{{ route('notifications.index') }}"
                class="nav-link relative flex items-center gap-3 px-3 py-2 rounded-sm {{ request()->routeIs('notifications.*') ? 'bg-primary text-primary-content' : 'bg-base-100/70 hover:bg-base-300/80 transition-colors' }}"
                aria-current="{{ request()->routeIs('notifications.*') ? 'page' : 'false' }}"
            >
                <span class="relative shrink-0">
                    <i data-lucide="bell" class="w-5 h-5"></i>
                    <span
                        class="nav-notification-unread-badge absolute -top-1.5 -right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-error px-1 text-[10px] font-bold text-white leading-none{{ $drawerUnreadCount > 0 ? '' : ' hidden' }}"
                    >
                        {{ $drawerUnreadCount > 0 ? ($drawerUnreadCount > 99 ? '99+' : $drawerUnreadCount) : '' }}
                    </span>
                </span>
                <span>Notifications</span>
            </a>
        @endauth
    </nav>

    {{-- Footer --}}
    <div class="p-4 border-t border-base-200 w-full mt-auto">
        @auth
            <x-include.userprofile />
        @else
            <a href="{{ route('bulletin.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-base-200 transition-colors">
                <i data-lucide="scroll" class="w-5 h-5 shrink-0"></i>
                <span>Bulletin</span>
            </a>
            <a href="{{ route('login') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-base-200 transition-colors">
                <i data-lucide="log-in" class="w-5 h-5 shrink-0"></i>
                <span>Sign In</span>
            </a>
            <a href="{{ route('register') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-primary text-primary-content hover:bg-primary/90 transition-colors">
                <i data-lucide="user-plus" class="w-5 h-5 shrink-0"></i>
                <span>Sign Up</span>
            </a>
        @endauth
    </div>
</aside>
@endif
