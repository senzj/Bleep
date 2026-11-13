@auth
    @php
        $compactUnreadCount = once(fn () => app(\App\Services\Notifications\NotificationService::class)->unreadCount(auth()->user()));
    @endphp

    {{-- Floating compact navigation. The full navbar is hidden by body.nav-compact
         and these clusters take over the top of the viewport. The centre of the
         row is intentionally left empty so page tabs can rise into it. --}}

    {{-- Left cluster: App Icon and primary navigation --}}
    <div id="compact-nav" class="compact-nav" aria-hidden="true">
        <div class="compact-nav__cluster compact-nav__cluster--left hidden lg:flex">
            {{-- App Icon --}}
            @if (request()->routeIs('home'))
                <span class="compact-nav__brand btn btn-ghost rounded-lg cursor-default" aria-current="page" aria-label="Bleep home">
                    <img src="{{ asset('Bleep_Icon.png') }}" alt="Bleep Logo" class="h-8 w-8">
                </span>
            @else
                <a href="{{ route('home') }}" class="compact-nav__brand btn btn-ghost rounded-lg" aria-label="Bleep home">
                    <img src="{{ asset('Bleep_Icon.png') }}" alt="Bleep Logo" class="h-8 w-8">
                </a>
            @endif

            {{-- Primary navigation --}}
            <nav class="compact-nav__links flex items-center gap-0.5" aria-label="Primary">
                <x-include.navlinks />
            </nav>
        </div>

        {{-- Right cluster: notifications and user profile --}}
        <div class="compact-nav__cluster compact-nav__cluster--right hidden lg:flex">
            {{-- Notification icon --}}
            <a href="{{ route('notifications.index') }}"
               class="compact-nav__icon btn btn-ghost rounded-lg"
               aria-label="Notifications">
                <span class="relative inline-flex">
                    <i data-lucide="bell" class="h-5 w-5"></i>
                    <span
                        class="nav-notification-unread-badge absolute -top-1.5 -right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-error px-1 text-[10px] font-bold leading-none text-white{{ $compactUnreadCount > 0 ? '' : ' hidden' }}"
                    >
                        {{ $compactUnreadCount > 0 ? ($compactUnreadCount > 99 ? '99+' : $compactUnreadCount) : '' }}
                    </span>
                </span>
            </a>

            {{-- User profile --}}
            <div class="compact-nav__profile nav-profile flex items-center">
                <x-include.userprofile />
            </div>
        </div>

        {{-- Phones/tablets: app icon left, page tabs centre, menu right. --}}
        @if (request()->routeIs('home'))
            <span
                class="compact-nav__fab compact-nav__fab--left btn rounded-lg cursor-default lg:hidden"
                aria-current="page"
                aria-label="Bleep home"
            >
                <img src="{{ asset('Bleep_Icon.png') }}" alt="Bleep Logo" class="h-7 w-7">
            </span>
        @else
            <a
                href="{{ route('home') }}"
                class="compact-nav__fab compact-nav__fab--left btn rounded-lg lg:hidden"
                aria-label="Bleep home"
            >
                <img src="{{ asset('Bleep_Icon.png') }}" alt="Bleep Logo" class="h-7 w-7">
            </a>
        @endif

        <button
            type="button"
            id="compact-mobile-menu-btn"
            class="compact-nav__fab compact-nav__fab--right btn rounded-lg lg:hidden"
            aria-label="Open menu"
        >
            <i data-lucide="menu" class="h-7 w-7"></i>
        </button>
    </div>
@endauth
