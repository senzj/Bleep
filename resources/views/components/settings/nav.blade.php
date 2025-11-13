<nav class="space-y-1">
    <a href="{{ route('settings.profile') }}"
       class="flex items-center px-3 py-2 rounded-md text-sm font-medium
       {{ request()->routeIs('settings.profile') ? 'bg-base-200 text-base-content' : 'text-base-content/70 hover:bg-base-200' }}">
        <i data-lucide="user" class="w-4 h-4 mr-2"></i>
        Profile
    </a>
    <a href="{{ route('settings.password') }}"
       class="flex items-center px-3 py-2 rounded-md text-sm font-medium
       {{ request()->routeIs('settings.password') ? 'bg-base-200 text-base-content' : 'text-base-content/70 hover:bg-base-200' }}">
        <i data-lucide="lock" class="w-4 h-4 mr-2"></i>
        Password
    </a>
</nav>
