@auth
    {{-- Home --}}
    <a href="{{ route('home') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-base-100/50 hover:bg-base-300/80 border border-base-300/50 shadow-lg transition-colors {{ request()->is('/') ? 'bg-primary text-primary-content' : '' }}">
        <i data-lucide="home" class="w-5 h-5 shrink-0"></i>
        <span class="sidebar-text">Home</span>
    </a>

    {{-- Messages --}}
    <a href="{{ route('messages') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-base-100/50 hover:bg-base-300/80 border border-base-300/50 shadow-lg transition-colors {{ request()->routeIs('messages') ? 'bg-primary text-primary-content' : '' }}">
        <i data-lucide="message-square" class="w-5 h-5 shrink-0"></i>
        <span class="sidebar-text">Messages</span>
    </a>

    {{-- People --}}
    <a href="{{ route('social.people') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-base-100/50 hover:bg-base-300/80 border border-base-300/50 shadow-lg transition-colors {{ request()->routeIs('social.people') ? 'bg-primary text-primary-content' : '' }}">
        <i data-lucide="users" class="w-5 h-5 shrink-0"></i>
        <span class="sidebar-text">People</span>
    </a>

    {{-- Notifications --}}
    <a href="#" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-base-100/50 hover:bg-base-300/80 border border-base-300/50 shadow-lg transition-colors {{ request()->routeIs('announcements') ? 'bg-primary text-primary-content' : '' }}">
        <i data-lucide="bell" class="w-5 h-5 shrink-0"></i>
        <span class="sidebar-text">Notifications</span>
    </a>
@endauth
