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
