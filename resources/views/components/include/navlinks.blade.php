@auth
    @php
        $unreadConversationCount = \Illuminate\Support\Facades\DB::table('conversation_user as cu')
            ->join('messages as m', 'm.conversation_id', '=', 'cu.conversation_id')
            ->where('cu.user_id', auth()->id())
            ->where('m.sender_id', '!=', auth()->id())
            ->whereNull('m.deleted_at')
            ->whereRaw('(cu.last_read_at IS NULL OR m.created_at > cu.last_read_at)')
            ->distinct()
            ->count('cu.conversation_id');
    @endphp

    {{-- Home --}}
    <a href="{{ route('home') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg bg-base-100/50 hover:bg-base-300/80 border border-base-300/50 shadow-lg transition-colors {{ request()->is('/') ? 'bg-primary text-primary-content' : '' }}">
        <i data-lucide="home" class="w-5 h-5 shrink-0"></i>
        <span class="sidebar-text">Home</span>
    </a>

    {{-- Messages --}}
    <a href="{{ route('messages') }}" class="relative flex items-center gap-3 px-3 py-2 rounded-lg bg-base-100/50 hover:bg-base-300/80 border border-base-300/50 shadow-lg transition-colors {{ request()->routeIs('messages') ? 'bg-primary text-primary-content' : '' }}">
        <span class="relative shrink-0">
            <i data-lucide="message-square" class="w-5 h-5"></i>
            <span
                id="nav-chat-unread-badge"
                class="absolute -top-1.5 -right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-error px-1 text-[10px] font-bold text-white leading-none{{ $unreadConversationCount > 0 ? '' : ' hidden' }}"
            >
                {{ $unreadConversationCount > 0 ? ($unreadConversationCount > 99 ? '99+' : $unreadConversationCount) : '' }}
            </span>
        </span>
        <span class="sidebar-text">Chat</span>
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

    {{-- Keep nav chat badge in sync with Vue chat store --}}
    <script>
        document.addEventListener('chat:unread-updated', function(e) {
            var badge = document.getElementById('nav-chat-unread-badge');
            if (!badge) return;
            var count = Number(e.detail?.count || 0);
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.classList.remove('hidden');
            } else {
                badge.textContent = '';
                badge.classList.add('hidden');
            }
        });
    </script>
@endauth
