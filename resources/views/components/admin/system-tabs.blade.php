@props(['tab' => 'health'])

@php
    $tabs = [
        'health' => ['route' => 'admin.system', 'icon' => 'activity', 'label' => 'Health'],
        'logs' => ['route' => 'admin.system.logs', 'icon' => 'file-text', 'label' => 'System Logs'],
        'attacks' => ['route' => 'admin.system.attacks', 'icon' => 'shield-alert', 'label' => 'Attacks'],
        'schedule' => ['route' => 'admin.system.schedule', 'icon' => 'calendar-clock', 'label' => 'Schedule'],
    ];
@endphp

<nav class="mb-6 flex flex-wrap gap-1 rounded-xl bg-base-200 p-1" aria-label="System tabs">
    @foreach ($tabs as $key => $item)
        <a href="{{ route($item['route']) }}"
           aria-current="{{ $tab === $key ? 'page' : 'false' }}"
           class="flex min-w-24 flex-1 items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition-colors
               {{ $tab === $key
                   ? 'bg-primary text-primary-content shadow-sm'
                   : 'text-base-content/70 hover:bg-base-300 hover:text-base-content' }}">
            <i data-lucide="{{ $item['icon'] }}" class="h-4 w-4 shrink-0"></i>
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
