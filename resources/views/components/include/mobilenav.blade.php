{{-- Mobile Slide-over Menu --}}
<div id="hor-mobile-overlay" class="fixed inset-0 bg-black/50 z-50 hidden lg:hidden transition-opacity duration-300" aria-hidden="true"></div>

<aside id="hor-mobile-drawer" class="lg:hidden fixed left-0 top-0 h-full w-72 bg-base-100 shadow-xl z-50 transform transition-transform duration-300 ease-in-out -translate-x-full flex flex-col">
    {{-- Header --}}
    <div class="flex items-center justify-between p-4 border-b border-base-200">
        <a href="/" class="flex items-center gap-2 text-xl font-bold">
            <img src="{{ asset('Bleep_Icon.png') }}" alt="Bleep Logo" class="w-8 h-8">
            <span>Bleep</span>
        </a>
        <button id="hor-mobile-close-btn" class="btn btn-ghost btn-sm btn-circle" aria-label="Close menu">
            <i data-lucide="x" class="w-5 h-5"></i>
        </button>
    </div>

    {{-- Navigation Links --}}
    <nav class="flex flex-col p-4 space-y-1 flex-1 overflow-y-auto">
        @auth
            <a href="/" class="flex items-center gap-3 px-3 py-3 rounded-lg hover:bg-base-200 transition-colors {{ request()->is('/') ? 'bg-primary text-primary-content' : '' }}">
                <i data-lucide="home" class="w-5 h-5 shrink-0"></i>
                <span>Home</span>
            </a>

            <a href="{{ route('messages') }}" class="flex items-center gap-3 px-3 py-3 rounded-lg hover:bg-base-200 transition-colors {{ request()->routeIs('messages') ? 'bg-primary text-primary-content' : '' }}">
                <i data-lucide="message-square" class="w-5 h-5 shrink-0"></i>
                <span>Messages</span>
            </a>

            <a href="/people" class="flex items-center gap-3 px-3 py-3 rounded-lg hover:bg-base-200 transition-colors {{ request()->is('people') ? 'bg-primary text-primary-content' : '' }}">
                <i data-lucide="users" class="w-5 h-5 shrink-0"></i>
                <span>People</span>
            </a>

            <a href="/announcements" class="flex items-center gap-3 px-3 py-3 rounded-lg hover:bg-base-200 transition-colors {{ request()->is('announcements') ? 'bg-primary text-primary-content' : '' }}">
                <i data-lucide="bell" class="w-5 h-5 shrink-0"></i>
                <span>Notifications</span>
            </a>
        @endauth
    </nav>

    {{-- Footer --}}
    <div class="p-4 border-t border-base-200 w-full mt-auto">
        @auth
            <x-include.userprofile />
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
    </div>
</aside>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const menuBtn = document.getElementById('hor-mobile-menu-btn');
        const closeBtn = document.getElementById('hor-mobile-close-btn');
        const drawer = document.getElementById('hor-mobile-drawer');
        const overlay = document.getElementById('hor-mobile-overlay');

        function openDrawer() {
            drawer?.classList.remove('-translate-x-full');
            overlay?.classList.remove('hidden');
        }

        function closeDrawer() {
            drawer?.classList.add('-translate-x-full');
            overlay?.classList.add('hidden');
        }

        menuBtn?.addEventListener('click', openDrawer);
        closeBtn?.addEventListener('click', closeDrawer);
        overlay?.addEventListener('click', closeDrawer);

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeDrawer();
        });

        // Re-initialize Lucide icons
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });
</script>
