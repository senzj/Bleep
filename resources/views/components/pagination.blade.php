@props([
    'paginator' => null,
])

<?php
// Build elements array from paginator
$elements = [];
if ($paginator) {
    $onEachSide = 1; // Show 1 page on each side of current
    $from = max($paginator->currentPage() - $onEachSide, 1);
    $to = min($paginator->currentPage() + $onEachSide, $paginator->lastPage());

    // Add pages before current (with ellipsis if needed)
    if ($from > 1) {
        $elements[] = [1 => $paginator->url(1)];
        if ($from > 2) {
            $elements[] = '...';
        }
    }

    // Add pages in range
    $pageLinks = [];
    for ($page = $from; $page <= $to; $page++) {
        $pageLinks[$page] = $paginator->url($page);
    }
    $elements[] = $pageLinks;

    // Add pages after current (with ellipsis if needed)
    if ($to < $paginator->lastPage()) {
        if ($to < $paginator->lastPage() - 1) {
            $elements[] = '...';
        }
        $elements[] = [$paginator->lastPage() => $paginator->url($paginator->lastPage())];
    }
}
?>

<div class="flex flex-col gap-4">
    {{-- Page Info --}}
    <div class="text-center text-sm text-base-content/60">
        Showing <span class="font-semibold">{{ $paginator->currentPage() }}</span> of <span class="font-semibold">{{ $paginator->lastPage() }}</span>
    </div>

    {{-- Pagination Controls --}}
    <div class="flex flex-wrap items-center justify-center gap-1 sm:gap-2">
        {{-- First Page Link --}}
        @if ($paginator->onFirstPage())
            <button type="button" disabled class="btn btn-sm btn-ghost btn-disabled cursor-not-allowed opacity-50" aria-label="First page" title="First page">
                <i data-lucide="chevrons-left" class="w-4 h-4"></i>
                <span class="hidden sm:inline">First</span>
            </button>
        @else
            <a href="{{ $paginator->url(1) }}" class="btn btn-sm btn-ghost hover:btn-primary" aria-label="First page" title="Go to first page">
                <i data-lucide="chevrons-left" class="w-4 h-4"></i>
                <span class="hidden sm:inline">First</span>
            </a>
        @endif

        {{-- Previous Page Link --}}
        @if ($paginator->onFirstPage())
            <button type="button" disabled class="btn btn-sm btn-ghost btn-disabled cursor-not-allowed opacity-50" aria-disabled="true" aria-label="Previous page" title="Previous page">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
                <span class="hidden sm:inline">Prev</span>
            </button>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-sm btn-ghost hover:btn-primary" rel="prev" aria-label="Previous page" title="Go to previous page">
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
                <span class="hidden sm:inline">Prev</span>
            </a>
        @endif

        {{-- Page Numbers --}}
        <div class="flex flex-wrap items-center justify-center gap-1">
            @foreach ($elements as $element)
                {{-- "Three Dots" Separator --}}
                @if (is_string($element))
                    <span class="px-2 py-1 text-base-content/50" aria-disabled="true">{{ $element }}</span>
                @endif

                {{-- Array Of Links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <button type="button" disabled aria-current="page" class="btn btn-sm btn-primary" title="Current page">
                                {{ $page }}
                            </button>
                        @else
                            <a href="{{ $url }}" class="btn btn-sm btn-ghost hover:btn-outline" title="Go to page {{ $page }}">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach
        </div>

        {{-- Next Page Link --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-sm btn-ghost hover:btn-primary" rel="next" aria-label="Next page" title="Go to next page">
                <span class="hidden sm:inline">Next</span>
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </a>
        @else
            <button type="button" disabled class="btn btn-sm btn-ghost btn-disabled cursor-not-allowed opacity-50" aria-disabled="true" aria-label="Next page" title="Next page">
                <span class="hidden sm:inline">Next</span>
                <i data-lucide="chevron-right" class="w-4 h-4"></i>
            </button>
        @endif

        {{-- Last Page Link --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->url($paginator->lastPage()) }}" class="btn btn-sm btn-ghost hover:btn-primary" aria-label="Last page" title="Go to last page">
                <span class="hidden sm:inline">Last</span>
                <i data-lucide="chevrons-right" class="w-4 h-4"></i>
            </a>
        @else
            <button type="button" disabled class="btn btn-sm btn-ghost btn-disabled cursor-not-allowed opacity-50" aria-label="Last page" title="Last page">
                <span class="hidden sm:inline">Last</span>
                <i data-lucide="chevrons-right" class="w-4 h-4"></i>
            </button>
        @endif
    </div>
</div>
