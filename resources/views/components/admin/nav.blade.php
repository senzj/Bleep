<nav aria-label="Admin navigation">

    @php
        $items = [
            ['route' => 'admin.dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
            ['route' => 'admin.reports', 'icon' => 'flag', 'label' => 'Reports'],
            ['route' => 'admin.users', 'icon' => 'users', 'label' => 'User Management'],
            ['route' => 'admin.devices', 'icon' => 'monitor-smartphone', 'label' => 'Devices & Sessions'],
            ['route' => 'admin.visits', 'icon' => 'chart-column', 'label' => 'Site Visits'],
            ['route' => 'admin.logs', 'icon' => 'file-text', 'label' => 'System Logs'],
        ];
    @endphp

    {{-- Mobile: horizontal nav --}}
    <div class="md:hidden flex justify-between bg-base-200 rounded-lg p-1">
        @foreach ($items as $item)
            <a href="{{ route($item['route']) }}"
                title="{{ $item['label'] }}"
                class="flex items-center justify-center p-2.5 rounded-md transition-colors flex-1
                    {{ request()->routeIs($item['route'])
                        ? 'bg-primary text-primary-content shadow-sm'
                        : 'hover:bg-base-300' }}">
                <i data-lucide="{{ $item['icon'] }}" class="w-5 h-5"></i>
            </a>
        @endforeach
    </div>

    {{-- Desktop: vertical nav --}}
    <div class="hidden md:block">
        <nav class="space-y-1" aria-label="Admin navigation">
            @foreach ($items as $item)
                <a href="{{ route($item['route']) }}"
                    class="flex items-center gap-3 px-4 py-2 text-sm rounded-lg transition-colors
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
