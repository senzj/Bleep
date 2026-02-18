{{-- Vertical Sidebar Navigation --}}
{{-- Desktop: Static sidebar in document flow --}}
<aside id="vertical-nav-desktop" class="hidden lg:flex flex-col w-54 h-screen sticky top-0 bg-base-100 shadow-lg shrink-0">
    {{-- Logo --}}
    <div class="p-4 border-b border-base-200">
        <a href="/" class="flex items-center gap-2 text-xl font-bold">
            <img src="{{ asset('Bleep_Icon.png') }}" alt="Bleep Logo" class="w-8 h-8">
            <span class="sidebar-text">Bleep</span>
        </a>
    </div>

    {{-- Navigation Links --}}
    <nav class="flex flex-col p-4 space-y-1 flex-1 overflow-y-auto">
        @auth
            {{-- Home --}}
            <a href="/" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-base-200 transition-colors {{ request()->is('/') ? 'bg-primary text-primary-content' : '' }}">
                <i data-lucide="home" class="w-5 h-5 shrink-0"></i>
                <span class="sidebar-text">Home</span>
            </a>

            {{-- Messages --}}
            <a href="{{ route('messages') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-base-200 transition-colors {{ request()->routeIs('messages') ? 'bg-primary text-primary-content' : '' }}">
                <i data-lucide="message-square" class="w-5 h-5 shrink-0"></i>
                <span class="sidebar-text">Messages</span>
            </a>

            {{-- People --}}
            <a href="/people" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-base-200 transition-colors {{ request()->is('people') ? 'bg-primary text-primary-content' : '' }}">
                <i data-lucide="users" class="w-5 h-5 shrink-0"></i>
                <span class="sidebar-text">People</span>
            </a>

            {{-- Notifications --}}
            <a href="/announcements" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-base-200 transition-colors {{ request()->is('announcements') ? 'bg-primary text-primary-content' : '' }}">
                <i data-lucide="bell" class="w-5 h-5 shrink-0"></i>
                <span class="sidebar-text">Notifications</span>
            </a>
        @else
            <a href="{{ route('login') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-base-200 transition-colors">
                <i data-lucide="log-in" class="w-5 h-5 shrink-0"></i>
                <span class="sidebar-text">Sign In</span>
            </a>
            <a href="{{ route('register') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-primary text-primary-content hover:bg-primary/90 transition-colors">
                <i data-lucide="user-plus" class="w-5 h-5 shrink-0"></i>
                <span class="sidebar-text">Sign Up</span>
            </a>
        @endauth
    </nav>

    {{-- User profile section at bottom with dropdown --}}
    @auth
        <div class="p-4 border-t border-base-200 mt-auto">
            <div class="dropdown dropdown-top w-full">
                <button tabindex="0" class="flex items-center gap-3 w-full px-2 py-2 rounded-lg hover:bg-base-200 transition-colors cursor-pointer">
                    <div class="avatar shrink-0">
                        <x-subcomponents.avatar :user="Auth::user()" :size="10" />
                    </div>
                    <div class="sidebar-text flex-1 min-w-0 text-left">
                        <p class="font-semibold text-sm truncate">{{ Auth::user()->dname }}</p>
                        <p class="text-xs text-base-content/60 truncate">{{ '@' . Auth::user()->username }}</p>
                    </div>
                    <i data-lucide="more-vertical" class="w-4 h-4 shrink-0"></i>
                </button>
                <ul tabindex="0" class="dropdown-content z-[60] shadow-lg bg-base-100 rounded-md w-full border border-base-200 p-2 space-y-1 mb-2">
                    <li>
                        <a href="{{ route('user.profile', ['username' => Auth::user()->username]) }}"
                           class="flex items-center gap-2 w-full px-3 py-2 text-sm text-base-content rounded-md hover:bg-base-200 transition">
                            <i data-lucide="user" class="w-4 h-4"></i>
                            <span>Profile</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('settings') }}" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-base-content rounded-md hover:bg-base-200 transition">
                            <i data-lucide="settings" class="w-4 h-4"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                    @can('is_admin')
                        <li>
                            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-base-content rounded-md hover:bg-base-200 transition">
                                <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                                <span>Admin Dashboard</span>
                            </a>
                        </li>
                    @endcan
                    <li>
                        <span class="text-xs text-base-content/70 inline-flex items-center gap-2 px-3 py-1">
                            Theme:
                            <i data-lucide="sun" class="w-4 h-4 theme-current-icon"></i>
                            <span class="font-semibold theme-current-label">System</span>
                        </span>
                    </li>
                    <li class="pt-1 border-t border-base-200">
                        <form method="POST" action="/logout" class="w-full">
                            @csrf
                            <button type="submit" class="flex cursor-pointer items-center gap-2 w-full px-3 py-2 text-sm text-red-600 rounded-md hover:bg-red-100 transition">
                                <i data-lucide="log-out" class="w-4 h-4"></i>
                                <span>Logout</span>
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    @endauth
</aside>

{{-- Mobile: Slide-out sidebar (fixed overlay) --}}
<aside id="vertical-nav-mobile" class="lg:hidden fixed left-0 top-0 h-full w-64 bg-base-100 shadow-lg z-50 transform transition-transform duration-300 ease-in-out -translate-x-full">
    {{-- Mobile close button --}}
    <button type="button" class="absolute top-4 right-4 btn btn-ghost btn-sm btn-circle" id="close-sidebar-btn" aria-label="Close sidebar">
        <i data-lucide="x" class="w-5 h-5"></i>
    </button>

    {{-- Logo --}}
    <div class="p-4 border-b border-base-200">
        <a href="/" class="flex items-center gap-2 text-xl font-bold">
            <img src="{{ asset('Bleep_Icon.png') }}" alt="Bleep Logo" class="w-8 h-8">
            <span>Bleep</span>
        </a>
    </div>

    {{-- Navigation Links --}}
    <nav class="flex flex-col p-4 space-y-1">
        @auth
            <a href="/" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-base-200 transition-colors {{ request()->is('/') ? 'bg-primary text-primary-content' : '' }}">
                <i data-lucide="home" class="w-5 h-5 shrink-0"></i>
                <span>Home</span>
            </a>

            <a href="{{ route('messages') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-base-200 transition-colors {{ request()->routeIs('messages') ? 'bg-primary text-primary-content' : '' }}">
                <i data-lucide="message-square" class="w-5 h-5 shrink-0"></i>
                <span>Messages</span>
            </a>

            <a href="/people" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-base-200 transition-colors {{ request()->is('people') ? 'bg-primary text-primary-content' : '' }}">
                <i data-lucide="users" class="w-5 h-5 shrink-0"></i>
                <span>People</span>
            </a>

            <a href="/announcements" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-base-200 transition-colors {{ request()->is('announcements') ? 'bg-primary text-primary-content' : '' }}">
                <i data-lucide="bell" class="w-5 h-5 shrink-0"></i>
                <span>Notifications</span>
            </a>

            <div class="divider my-2"></div>

            <a href="{{ route('user.profile', ['username' => Auth::user()->username]) }}" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-base-200 transition-colors">
                <i data-lucide="user" class="w-5 h-5 shrink-0"></i>
                <span>Profile</span>
            </a>

            <a href="{{ route('settings') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-base-200 transition-colors">
                <i data-lucide="settings" class="w-5 h-5 shrink-0"></i>
                <span>Settings</span>
            </a>

            @can('is_admin')
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-base-200 transition-colors">
                    <i data-lucide="layout-dashboard" class="w-5 h-5 shrink-0"></i>
                    <span>Admin Dashboard</span>
                </a>
            @endcan

            <div class="divider my-2"></div>

            <form method="POST" action="/logout">
                @csrf
                <button type="submit" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-red-100 text-red-600 transition-colors w-full">
                    <i data-lucide="log-out" class="w-5 h-5 shrink-0"></i>
                    <span>Logout</span>
                </button>
            </form>
        @else
            <a href="{{ route('login') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-base-200 transition-colors">
                <i data-lucide="log-in" class="w-5 h-5 shrink-0"></i>
                <span>Sign In</span>
            </a>
            <a href="{{ route('register') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-primary text-primary-content hover:bg-primary/90 transition-colors">
                <i data-lucide="user-plus" class="w-5 h-5 shrink-0"></i>
                <span>Sign Up</span>
            </a>
        @endauth
    </nav>
</aside>

{{-- Mobile overlay --}}
<div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden" aria-hidden="true"></div>

{{-- Mobile hamburger button (floating) --}}
<button type="button" id="open-sidebar-btn" class="lg:hidden fixed top-4 left-4 z-30 btn btn-circle btn-primary shadow-lg" aria-label="Open menu">
    <i data-lucide="menu" class="w-5 h-5"></i>
</button>
