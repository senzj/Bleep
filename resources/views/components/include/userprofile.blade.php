@auth
    {{-- Profile, hidden when mobile --}}
    <div class="dropdown dropdown-end w-full"
         x-data
         @click="
             const rect = $el.getBoundingClientRect();
             const spaceBelow = window.innerHeight - rect.bottom;
             $el.classList.toggle('dropdown-top', spaceBelow < 250);
         ">
        <button tabindex="0" class="flex items-center gap-3 px-3 py-2 shadow-lg border border-gray-200 rounded-md hover:bg-base-200 transition-colors cursor-pointer w-full">
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
            <div class="ml-auto">
                <i data-lucide="more-vertical" class="w-4 h-4"></i>
            </div>
        </button>

        {{-- Dropdown Menu --}}
        <ul tabindex="0" class="dropdown-content z-1 shadow-lg bg-base-100 rounded-md w-full border border-base-200 p-2 space-y-1 mt-2 mb-2">
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
                <span class="text-xs text-base-content/70 inline-flex items-center gap-2 ml-3">
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
