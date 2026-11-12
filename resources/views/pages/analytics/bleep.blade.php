@php
    $stats = [
        ['label' => 'Total views', 'value' => $summary['views'], 'icon' => 'eye'],
        ['label' => 'Unique viewers', 'value' => $summary['unique_viewers'], 'icon' => 'users'],
        ['label' => 'Likes', 'value' => $summary['likes'], 'icon' => 'heart'],
        ['label' => 'Comments', 'value' => $summary['comments'], 'icon' => 'message-circle'],
        ['label' => 'Shares', 'value' => $summary['shares'], 'icon' => 'share-2'],
        ['label' => 'Reposts', 'value' => $summary['reposts'], 'icon' => 'repeat-2'],
        ['label' => 'Engagement rate', 'value' => $summary['engagement_rate'], 'suffix' => '%', 'icon' => 'activity'],
        ['label' => 'Total engagement', 'value' => $summary['total_engagement'], 'icon' => 'sparkles'],
        ['label' => 'Media items', 'value' => $summary['media_count'], 'icon' => 'paperclip'],
    ];

    $engagementBreakdown = [
        ['label' => 'Likes', 'value' => $summary['likes'], 'icon' => 'heart', 'bar' => 'bg-error'],
        ['label' => 'Comments', 'value' => $summary['comments'], 'icon' => 'message-circle', 'bar' => 'bg-primary'],
        ['label' => 'Shares', 'value' => $summary['shares'], 'icon' => 'share-2', 'bar' => 'bg-secondary'],
        ['label' => 'Reposts', 'value' => $summary['reposts'], 'icon' => 'repeat-2', 'bar' => 'bg-accent'],
    ];

    $breakdownPeak = max(1, collect($engagementBreakdown)->max('value'));
@endphp

<x-layout>
    <x-slot:title>Bleep analytics</x-slot:title>

    <div class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-2">
                <x-ui.back-button fallback="{{ route('home') }}" />
                <h1 class="text-2xl font-bold">Bleep analytics</h1>
                <p class="text-sm text-base-content/70">
                    Only you can see these numbers.
                </p>
            </div>

            <span class="badge badge-outline">
                {{ $bleep->created_at?->format('j M Y, H:i') }}
            </span>
        </div>

        <div class="rounded-xl border border-base-200 bg-base-100 p-4">
            <p class="line-clamp-3 text-sm text-base-content/80">
                {{ $bleep->message ?: 'Media-only bleep' }}
            </p>
            <p class="mt-2 text-xs text-base-content/50">
                {{ $bleep->is_anonymous ? 'Posted anonymously' : 'Posted as @' . $bleep->user?->username }}
            </p>

            @if ($bleep->media->isNotEmpty())
                <div class="mt-3">
                    @include('components.bleepsmedia', [
                        'mediaItems' => $bleep->media,
                        'isNsfw' => $bleep->is_nsfw,
                    ])
                </div>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            @foreach ($stats as $stat)
                <div class="rounded-xl border border-base-200 bg-base-100 p-3">
                    <div class="flex items-center gap-2 text-xs text-base-content/60">
                        <i data-lucide="{{ $stat['icon'] }}" class="h-3.5 w-3.5" aria-hidden="true"></i>
                        <span>{{ $stat['label'] }}</span>
                    </div>
                    <p class="mt-1 text-2xl font-bold">
                        {{ number_format($stat['value']) }}@isset($stat['suffix']){{ $stat['suffix'] }}@endisset
                    </p>
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border border-base-200 bg-base-100 p-4">
            <div class="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 class="font-semibold">Views in the last 14 days</h2>
                    <p class="text-xs text-base-content/60">
                        {{ number_format($periodViews) }} views this period
                        @if ($trend !== null)
                            <span class="{{ $trend >= 0 ? 'text-success' : 'text-error' }}">
                                ({{ $trend >= 0 ? '+' : '' }}{{ $trend }}% vs previous)
                            </span>
                        @endif
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs text-base-content/60">
                    <span class="inline-flex items-center gap-1">
                        <span class="h-2 w-2 rounded-full bg-primary"></span> Daily views
                    </span>
                </div>
            </div>

            <div class="mt-4">
                <x-analytics.bar-chart :items="$chart" :peak="$peak" />
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2 text-center text-xs sm:grid-cols-4">
                <div class="rounded-lg bg-base-200/60 p-2">
                    <p class="text-lg font-semibold">{{ number_format($summary['guest_views']) }}</p>
                    <p class="text-base-content/60">Guest views</p>
                </div>
                <div class="rounded-lg bg-base-200/60 p-2">
                    <p class="text-lg font-semibold">{{ number_format($summary['signed_in_viewers']) }}</p>
                    <p class="text-base-content/60">Unique signed-in viewers</p>
                </div>
                <div class="rounded-lg bg-base-200/60 p-2">
                    <p class="text-lg font-semibold">{{ number_format($periodViews / max(1, $chart->count()), 1) }}</p>
                    <p class="text-base-content/60">Avg views / day</p>
                </div>
                <div class="rounded-lg bg-base-200/60 p-2">
                    <p class="text-lg font-semibold">
                        @if ($bestDay)
                            {{ number_format($bestDay['total']) }}
                        @else
                            &mdash;
                        @endif
                    </p>
                    <p class="text-base-content/60">
                        Best day{{ $bestDay ? ' · ' . $bestDay['label'] : '' }}
                    </p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-base-200 bg-base-100 p-4">
            <h2 class="font-semibold">Engagement breakdown</h2>
            <p class="text-xs text-base-content/60">
                How people interacted with this bleep.
            </p>

            <ul class="mt-4 space-y-3">
                @foreach ($engagementBreakdown as $item)
                    <li>
                        <div class="flex items-center justify-between gap-2 text-sm">
                            <span class="inline-flex items-center gap-2">
                                <i data-lucide="{{ $item['icon'] }}" class="h-4 w-4 text-base-content/60" aria-hidden="true"></i>
                                {{ $item['label'] }}
                            </span>
                            <span class="font-semibold">{{ number_format($item['value']) }}</span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-full bg-base-200">
                            <div
                                class="{{ $item['bar'] }} h-full rounded-full transition-all duration-500"
                                style="width: {{ $item['value'] > 0 ? max(4, round(($item['value'] / $breakdownPeak) * 100)) : 0 }}%"
                            ></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-layout>
