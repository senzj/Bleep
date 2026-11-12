@props([
    'items' => [],
    'peak' => 1,
    'accent' => 'bg-primary',
    'height' => 'h-40',
])

<div class="{{ $height }} flex items-end gap-1" role="img" aria-label="Daily activity chart">
    @foreach ($items as $item)
        @php
            $total = (int) ($item['total'] ?? 0);
            $heightPercent = $total > 0 ? max(4, (int) round(($total / max(1, $peak)) * 100)) : 2;
        @endphp

        <div class="group relative flex h-full flex-1 flex-col justify-end">
            <div class="{{ $accent }} w-full rounded-t-sm transition-all duration-300"
                 style="height: {{ $heightPercent }}%"
                 title="{{ $item['label'] }}: {{ $total }}"></div>

            <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded-md bg-neutral px-2 py-1 text-[11px] text-neutral-content shadow group-hover:block">
                {{ $item['label'] }} · {{ $total }}
            </div>
        </div>
    @endforeach
</div>

<div class="mt-2 flex justify-between text-[11px] text-base-content/50">
    <span>{{ $items[0]['label'] ?? '' }}</span>
    <span>{{ $items[count($items) - 1]['label'] ?? '' }}</span>
</div>
