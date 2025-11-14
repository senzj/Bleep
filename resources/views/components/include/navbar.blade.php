<nav class="navbar bg-base-100">
    <div class="navbar-start">
        <a href="/" class="btn btn-ghost text-2xl">Bleep</a>
    </div>

    <div class="navbar-end gap-2">
        @auth
            {{-- Theme Toggle --}}
            <div class="dropdown dropdown-end">
                <button tabindex="0" class="btn btn-ghost btn-circle theme-button">
                    <i data-lucide="sun" class="w-5 h-5 theme-icon"></i>
                </button>
                <ul id="theme-menu" tabindex="0" class="dropdown-content z-1 shadow-lg bg-base-100 rounded-md w-44 border border-base-200 p-2 space-y-1 mt-2">
                    <li>
                        <button type="button" data-theme-name="lofi"
                                class="theme-toggle cursor-pointer flex items-center gap-3 w-full px-3 py-2 text-sm text-base-content rounded-md hover:bg-base-200 transition">
                            <i data-lucide="star" class="w-5 h-5"></i>
                            <span>Lofi</span>
                        </button>
                    </li>
                    <li>
                        <button type="button" data-theme-name="light"
                                class="theme-toggle cursor-pointer bg-transparent flex items-center gap-3 w-full px-3 py-2 text-sm text-base-content rounded-md hover:bg-base-200 transition">
                            <i data-lucide="sun" class="w-5 h-5"></i>
                            <span>Light</span>
                        </button>
                    </li>
                    <li>
                        <button type="button" data-theme-name="dark"
                                class="theme-toggle cursor-pointer flex items-center gap-3 w-full px-3 py-2 text-sm text-base-content rounded-md hover:bg-base-200 transition">
                            <i data-lucide="moon" class="w-5 h-5"></i>
                            <span>Dark</span>
                        </button>
                    </li>
                    <li>
                        <button type="button" data-theme-name="system"
                                class="theme-toggle cursor-pointer flex items-center gap-3 w-full px-3 py-2 text-sm text-base-content rounded-md hover:bg-base-200 transition">
                            <i data-lucide="laptop" class="w-5 h-5"></i>
                            <span>System</span>
                        </button>
                    </li>
                </ul>
            </div>

            {{-- Profile Dropdown --}}
            <div class="dropdown dropdown-end">
                <button tabindex="0" class="flex items-center gap-3 px-3 py-2 shadow-sm rounded-md hover:bg-base-200 transition-colors cursor-pointer">
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
                            <a href="#" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-base-content rounded-md hover:bg-base-200 transition">
                                <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                                <span>Admin Dashboard</span>
                            </a>
                        </li>
                    @endcan
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
