<nav
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
    class="relative"
    aria-label="Settings navigation"
>
    {{-- Mobile toggle (floating top-left) --}}
    <button @click="open = true"
        class="fixed bottom-5 left-5 z-10 md:hidden bg-base-100 border rounded-lg p-3 shadow hover:bg-base-200"
        :aria-expanded="open.toString()"
        aria-label="Open admin menu"
        type="button"
    >
        <i data-lucide="menu" class="w-5 h-5"></i>
    </button>

    {{-- Overlay --}}
    <div x-show="open" x-cloak x-transition.opacity
         class="fixed inset-0 bg-black/50 z-40 md:hidden"
         @click="open = false"
         aria-hidden="true"></div>

    {{-- Slide-out panel (mobile) --}}
    <aside x-show="open" x-cloak role="dialog" aria-modal="true"
           x-transition:enter="transition transform duration-300"
           x-transition:enter-start="-translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition transform duration-300"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="-translate-x-full"
           class="fixed top-0 left-0 z-50 h-full w-64 bg-base-100 p-4 shadow-lg md:hidden overflow-y-auto">

        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold">Settings</h3>
            <button @click="open = false" class="p-1 rounded hover:bg-base-200" aria-label="Close menu">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <nav class="space-y-1" aria-label="Mobile settings navigation">

            {{-- Profile edit --}}
            <a href="{{ route('settings.profile') }}"
               aria-current="{{ request()->routeIs('settings.profile') ? 'page' : 'false' }}"
               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium transition-colors border-l-2
               {{ request()->routeIs('settings.profile') ? 'bg-base-200 text-base-content border-primary' : 'text-base-content/70 hover:bg-base-200 hover:text-base-content border-transparent' }}"
               @click="open = false">
                <i data-lucide="user-round-pen" class="w-4 h-4" aria-hidden="true"></i>
                <span>Edit Profile</span>
            </a>

            {{-- Preferences --}}
            <a href="{{ route('settings.preferences') }}"
                aria-current="{{ request()->routeIs('settings.preferences') ? 'page' : 'false' }}"
                class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium transition-colors border-l-2 text-base-content/70 hover:bg-base-200 hover:text-base-content border-transparent"
                {{ request()->routeIs('settings.preferences') ? 'bg-base-200 text-base-content border-primary' : 'text-base-content/70 hover:bg-base-200 hover:text-base-content border-transparent' }}"
                @click.prevent="document.querySelector('.theme-button').click(); open = false">
                <i data-lucide="palette" class="w-4 h-4" aria-hidden="true"></i>
                <span>Preferences</span>
            </a>

            {{-- Password change --}}
            <a href="{{ route('settings.password') }}"
               aria-current="{{ request()->routeIs('settings.password') ? 'page' : 'false' }}"
               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium transition-colors border-l-2
               {{ request()->routeIs('settings.password') ? 'bg-base-200 text-base-content border-primary' : 'text-base-content/70 hover:bg-base-200 hover:text-base-content border-transparent' }}"
               @click="open = false">
                <i data-lucide="lock" class="w-4 h-4" aria-hidden="true"></i>
                <span>Change Password</span>
            </a>

            {{-- Device and Session --}}
            <a href="{{ route('settings.devices') }}"
               aria-current="{{ request()->routeIs('settings.device') ? 'page' : 'false' }}"
               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium transition-colors border-l-2
               {{ request()->routeIs('settings.device') ? 'bg-base-200 text-base-content border-primary' : 'text-base-content/70 hover:bg-base-200 hover:text-base-content border-transparent' }}"
               @click="open = false">
                <i data-lucide="monitor-smartphone" class="w-4 h-4" aria-hidden="true"></i>
                <span>Device and Session</span>
            </a>

            {{-- User Logs --}}
            <a href="{{ route('settings.logs') }}"
               aria-current="{{ request()->routeIs('settings.logs') ? 'page' : 'false' }}"
               class="flex items-center gap-2 px-3 py-2 rounded-md text-sm font-medium transition-colors border-l-2
               {{ request()->routeIs('settings.logs') ? 'bg-base-200 text-base-content border-primary' : 'text-base-content/70 hover:bg-base-200 hover:text-base-content border-transparent' }}"
               @click="open = false">
                <i data-lucide="file-text" class="w-4 h-4" aria-hidden="true"></i>
                <span>Account Logs</span>
            </a>
        </nav>
    </aside>

    {{-- Desktop nav (horizontal tabs) --}}
    <div class="hidden md:block">
        <nav class="flex flex-wrap gap-2" aria-label="Settings navigation">
            {{-- Profile  --}}
            <a href="{{ route('settings.profile') }}"
               aria-current="{{ request()->routeIs('settings.profile') ? 'page' : 'false' }}"
               class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors
               {{ request()->routeIs('settings.profile') ? 'bg-primary text-primary-content' : 'bg-base-200 text-base-content/70 hover:bg-base-300 hover:text-base-content' }}">
                <i data-lucide="user-round-pen" class="w-4 h-4" aria-hidden="true"></i>
                <span>Edit Profile</span>
            </a>

            {{-- Preferences --}}
            <a href="{{ route('settings.preferences') }}"
               aria-current="{{ request()->routeIs('settings.preferences') ? 'page' : 'false' }}"
               class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors
               {{ request()->routeIs('settings.preferences') ? 'bg-primary text-primary-content' : 'bg-base-200 text-base-content/70 hover:bg-base-300 hover:text-base-content' }}">
                <i data-lucide="palette" class="w-4 h-4" aria-hidden="true"></i>
                <span>Preferences</span>
            </a>

            {{-- Change Password --}}
            <a href="{{ route('settings.password') }}"
               aria-current="{{ request()->routeIs('settings.password') ? 'page' : 'false' }}"
               class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors
               {{ request()->routeIs('settings.password') ? 'bg-primary text-primary-content' : 'bg-base-200 text-base-content/70 hover:bg-base-300 hover:text-base-content' }}">
                <i data-lucide="lock" class="w-4 h-4" aria-hidden="true"></i>
                <span>Password</span>
            </a>

            {{-- Device and Session --}}
            <a href="{{ route('settings.devices') }}"
               aria-current="{{ request()->routeIs('settings.devices') ? 'page' : 'false' }}"
               class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors
               {{ request()->routeIs('settings.devices') ? 'bg-primary text-primary-content' : 'bg-base-200 text-base-content/70 hover:bg-base-300 hover:text-base-content' }}">
                <i data-lucide="monitor-smartphone" class="w-4 h-4" aria-hidden="true"></i>
                <span>Devices</span>
            </a>

            {{-- User Logs --}}
            <a href="{{ route('settings.logs') }}"
               aria-current="{{ request()->routeIs('settings.logs') ? 'page' : 'false' }}"
               class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-colors
               {{ request()->routeIs('settings.logs') ? 'bg-primary text-primary-content' : 'bg-base-200 text-base-content/70 hover:bg-base-300 hover:text-base-content' }}">
                <i data-lucide="file-text" class="w-4 h-4" aria-hidden="true"></i>
                <span>Logs</span>
            </a>
        </nav>
    </div>
</nav>
