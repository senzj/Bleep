<nav class="relative" aria-label="Settings navigation">
    {{-- Full-width responsive tabs --}}
    <nav class="flex flex-wrap gap-2 w-full" aria-label="Settings navigation">
        {{-- Profile  --}}
        <a href="{{ route('settings.profile') }}"
           aria-current="{{ request()->routeIs('settings.profile') ? 'page' : 'false' }}"
           class="flex items-center gap-2 px-3 py-2 md:px-4 rounded-lg text-sm font-medium transition-colors
           {{ request()->routeIs('settings.profile') ? 'bg-primary text-primary-content' : 'bg-base-200 text-base-content/70 hover:bg-base-300 hover:text-base-content' }}">
            <i data-lucide="user-round-pen" class="w-4 h-4 shrink-0" aria-hidden="true"></i>
            <span class="hidden sm:inline">Edit Profile</span>
        </a>

        {{-- Preferences --}}
        <a href="{{ route('settings.preferences') }}"
           aria-current="{{ request()->routeIs('settings.preferences') ? 'page' : 'false' }}"
           class="flex items-center gap-2 px-3 py-2 md:px-4 rounded-lg text-sm font-medium transition-colors
           {{ request()->routeIs('settings.preferences') ? 'bg-primary text-primary-content' : 'bg-base-200 text-base-content/70 hover:bg-base-300 hover:text-base-content' }}">
            <i data-lucide="palette" class="w-4 h-4 shrink-0" aria-hidden="true"></i>
            <span class="hidden sm:inline">Preferences</span>
        </a>

        {{-- Change Password --}}
        <a href="{{ route('settings.password') }}"
           aria-current="{{ request()->routeIs('settings.password') ? 'page' : 'false' }}"
           class="flex items-center gap-2 px-3 py-2 md:px-4 rounded-lg text-sm font-medium transition-colors
           {{ request()->routeIs('settings.password') ? 'bg-primary text-primary-content' : 'bg-base-200 text-base-content/70 hover:bg-base-300 hover:text-base-content' }}">
            <i data-lucide="lock" class="w-4 h-4 shrink-0" aria-hidden="true"></i>
            <span class="hidden sm:inline">Change Password</span>
        </a>

        {{-- Device and Session --}}
        <a href="{{ route('settings.devices') }}"
           aria-current="{{ request()->routeIs('settings.devices') ? 'page' : 'false' }}"
           class="flex items-center gap-2 px-3 py-2 md:px-4 rounded-lg text-sm font-medium transition-colors
           {{ request()->routeIs('settings.devices') ? 'bg-primary text-primary-content' : 'bg-base-200 text-base-content/70 hover:bg-base-300 hover:text-base-content' }}">
            <i data-lucide="monitor-smartphone" class="w-4 h-4 shrink-0" aria-hidden="true"></i>
            <span class="hidden sm:inline">Devices</span>
        </a>

        {{-- Account Activity --}}
        <a href="{{ route('settings.logs') }}"
           aria-current="{{ request()->routeIs('settings.logs') ? 'page' : 'false' }}"
           class="flex items-center gap-2 px-3 py-2 md:px-4 rounded-lg text-sm font-medium transition-colors
           {{ request()->routeIs('settings.logs') ? 'bg-primary text-primary-content' : 'bg-base-200 text-base-content/70 hover:bg-base-300 hover:text-base-content' }}">
            <i data-lucide="file-text" class="w-4 h-4 shrink-0" aria-hidden="true"></i>
            <span class="hidden sm:inline">Account Activity</span>
        </a>
    </nav>
</nav>
