<nav class="navbar bg-base-100 sticky top-0 z-40">
    <div class="navbar-start">
        {{-- Mobile hamburger menu --}}
        <div class="dropdown lg:hidden">
            <button tabindex="0" class="btn btn-ghost btn-circle" aria-label="Open menu">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
            <ul tabindex="0" class="dropdown-content z-[60] menu shadow-lg bg-base-100 rounded-box w-56 mt-3 border border-base-200 p-2">
                @auth
                    <li>
                        <a href="/" class="{{ request()->is('/') ? 'active' : '' }}">
                            <i data-lucide="home" class="w-4 h-4"></i>
                            Home
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('messages') }}" class="{{ request()->routeIs('messages') ? 'active' : '' }}">
                            <i data-lucide="message-square" class="w-4 h-4"></i>
                            Messages
                        </a>
                    </li>
                    <li>
                        <a href="/people" class="{{ request()->is('people') ? 'active' : '' }}">
                            <i data-lucide="users" class="w-4 h-4"></i>
                            People
                        </a>
                    </li>
                    <li>
                        <a href="/announcements" class="{{ request()->is('announcements') ? 'active' : '' }}">
                            <i data-lucide="bell" class="w-4 h-4"></i>
                            Notifications
                        </a>
                    </li>
                    <li class="menu-title mt-2">Account</li>
                    <li>
                        <a href="{{ route('user.profile', ['username' => Auth::user()->username]) }}">
                            <i data-lucide="user" class="w-4 h-4"></i>
                            Profile
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('settings') }}">
                            <i data-lucide="settings" class="w-4 h-4"></i>
                            Settings
                        </a>
                    </li>
                    @can('is_admin')
                        <li>
                            <a href="{{ route('admin.dashboard') }}">
                                <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                                Admin
                            </a>
                        </li>
                    @endcan
                @else
                    <li><a href="{{ route('login') }}"><i data-lucide="log-in" class="w-4 h-4"></i> Sign In</a></li>
                    <li><a href="{{ route('register') }}"><i data-lucide="user-plus" class="w-4 h-4"></i> Sign Up</a></li>
                @endauth
            </ul>
        </div>

        <a href="/" class="btn btn-ghost text-2xl">
            <img src="{{ asset('Bleep_Icon.png') }}" alt="Bleep Logo" class="w-6 h-6 mr-2">
            <span class="hidden sm:inline">Bleep</span>
        </a>

        {{-- Divider - hidden on mobile --}}
        <div class="divider divider-horizontal hidden lg:flex"></div>

        {{-- Nav Links - hidden on mobile --}}
        @auth
            <div class="hidden lg:flex gap-1">
                {{-- Home --}}
                <a href="/" class="btn btn-ghost btn-sm justify-start {{ request()->is('/') ? 'bg-primary text-primary-content' : '' }}" aria-current="page">
                    <i data-lucide="home" class="w-4 h-4 mr-2"></i>
                    Home
                </a>

                {{-- Messages --}}
                <a href="{{ route('messages') }}" class="btn btn-ghost btn-sm {{ request()->routeIs('messages') ? 'bg-primary text-primary-content' : '' }}">
                    <i data-lucide="message-square" class="w-4 h-4 mr-1"></i>
                    Messages
                </a>

                {{-- Add/search people --}}
                <a href="/people" class="btn btn-ghost btn-sm justify-start {{ request()->is('people') ? 'bg-primary text-primary-content' : '' }}">
                    <i data-lucide="users" class="w-4 h-4 mr-2"></i>
                    People
                </a>
                <a href="/announcements" class="btn btn-ghost btn-sm justify-start {{ request()->is('announcements') ? 'bg-primary text-primary-content' : '' }}">
                    <i data-lucide="bell" class="w-4 h-4 mr-2"></i>
                    Notifications
                </a>
            </div>
        @endauth

    </div>

    <div class="navbar-end gap-2">
        @auth
            {{-- Profile Dropdown --}}
            <div class="dropdown dropdown-end">
                <button tabindex="0" class="flex items-center gap-3 px-3 py-2 shadow-lg border border-gray-200 rounded-md hover:bg-base-200 transition-colors cursor-pointer">
                    {{-- Avatar --}}
                    <div class="avatar shrink-0">
                        <x-subcomponents.avatar :user="Auth::user()" :size="10" />
                    </div>

                    {{-- User Info --}}
                    <div class="text-left leading-tight">
                        <p class="font-semibold text-sm text-base-500">
                            {{ Auth::user()->dname }}
                        </p>
                        <p class="text-xs text-gray-500">
                            {{'@' . Auth::user()->username }}
                        </p>
                    </div>

                    {{-- three dot menu --}}
                    <div class="">
                        <i data-lucide="more-vertical" class="w-4 h-4"></i>
                    </div>
                </button>

                {{-- Dropdown Menu --}}
                <ul tabindex="0" class="dropdown-content z-1 shadow-lg bg-base-100 rounded-md w-52 border border-base-200 p-2 space-y-1 mt-2">
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
                        <span class="text-xs text-base-content/70 inline-flex items-center gap-2">
                            Theme:
                            <i data-lucide="sun" class="w-4 h-4 theme-current-icon"></i>
                            <span class="font-semibold theme-current-label">System</span>
                        </span>
                    </li>
                    <li class="pt-1 border-t border-base-400/80">
                        <form method="POST" action="/logout" class="w-full">
                            @csrf
                            <button type="submit"
                                    class="flex cursor-pointer items-center gap-2 w-full px-3 py-2 text-sm text-red-600 rounded-md hover:bg-red-200 transition">
                                <i data-lucide="log-out" class="w-4 h-4"></i>
                                <span>Logout</span>
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        @else
            <a href="{{ route('login') }}" class="btn btn-ghost btn-sm">Sign In</a>
            <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Sign Up</a>
        @endauth
    </div>
</nav>
