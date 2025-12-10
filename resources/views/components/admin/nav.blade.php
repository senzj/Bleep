<nav x-data="{ open: false }"
     @keydown.escape.window="open = false"
     class="relative"
     aria-label="Admin navigation">

    @php
        $items = [
            ['route' => 'admin.dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
            ['route' => 'admin.reports', 'icon' => 'flag', 'label' => 'Reports'],
            ['route' => 'admin.users', 'icon' => 'users', 'label' => 'User Management'],
            ['route' => 'admin.devices', 'icon' => 'monitor-smartphone', 'label' => 'Devices & Sessions'],
            ['route' => 'admin.visits', 'icon' => 'bar-chart-2', 'label' => 'Site Visits'],
            ['route' => 'admin.logs', 'icon' => 'file-text', 'label' => 'System Logs'],
        ];
    @endphp

    {{-- Mobile menu button --}}
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
        aria-hidden="true"
    ></div>

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
            <h3 class="text-sm font-semibold">Admin</h3>
            <button @click="open = false" class="p-1 rounded hover:bg-base-200" aria-label="Close menu">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <nav class="space-y-1" aria-label="Mobile admin navigation">
            @foreach ($items as $item)
                <a href="{{ route($item['route']) }}" class="flex items-center gap-3 px-4 py-2 text-sm rounded-lg transition-colors
                        {{ request()->routeIs($item['route'])
                            ? 'bg-primary text-primary-content shadow-sm'
                            : 'hover:bg-base-200' }}"
                    @click="open = false">
                    <i data-lucide="{{ $item['icon'] }}" class="w-5 h-5"></i>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </aside>

    {{-- Desktop / normal nav (hidden on mobile) --}}
    <div class="hidden md:block">
        <nav class="space-y-1" aria-label="Admin navigation">
            @foreach ($items as $item)
                <a href="{{ route($item['route']) }}" class="flex items-center gap-3 px-4 py-2 text-sm rounded-lg transition-colors
                        {{ request()->routeIs($item['route'])
                            ? 'bg-primary text-primary-content shadow-sm'
                            : 'hover:bg-base-200' }}">
                    <i data-lucide="{{ $item['icon'] }}" class="w-5 h-5"></i>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </div>

</nav>
