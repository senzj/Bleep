<nav class="space-y-1" aria-label="Settings navigation">
    <a href="{{ route('settings.profile') }}"
       aria-current="{{ request()->routeIs('settings.profile') ? 'page' : 'false' }}"

       class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium transition-colors border-l-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40
       {{ request()->routeIs('settings.profile') ? 'bg-base-200 text-base-content border-primary' : 'text-base-content/70 hover:bg-base-200 hover:text-base-content border-transparent' }}">
        <i data-lucide="user" class="w-4 h-4" aria-hidden="true"></i>
        <span>Profile</span>
    </a>
    <a href="{{ route('settings.password') }}"
       aria-current="{{ request()->routeIs('settings.password') ? 'page' : 'false' }}"

       class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium transition-colors border-l-2 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40
       {{ request()->routeIs('settings.password') ? 'bg-base-200 text-base-content border-primary' : 'text-base-content/70 hover:bg-base-200 hover:text-base-content border-transparent' }}">
        <i data-lucide="lock" class="w-4 h-4" aria-hidden="true"></i>
        <span>Password</span>
    </a>
</nav>
