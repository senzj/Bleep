@php
    $stats = [
        ['label' => 'Profile visits', 'value' => $summary['profile_visits'], 'icon' => 'eye'],
        ['label' => 'Unique visitors', 'value' => $summary['unique_visitors'], 'icon' => 'users'],
        ['label' => 'Followers', 'value' => $summary['followers'], 'icon' => 'user-plus'],
        ['label' => 'Following', 'value' => $summary['following'], 'icon' => 'user-check'],
        ['label' => 'Bleeps', 'value' => $summary['bleeps'], 'icon' => 'message-square'],
        ['label' => 'Bleep views', 'value' => $summary['bleep_views'], 'icon' => 'bar-chart-3'],
        ['label' => 'Likes received', 'value' => $summary['likes_received'], 'icon' => 'heart'],
        ['label' => 'Comments received', 'value' => $summary['comments_received'], 'icon' => 'message-circle'],
    ];
@endphp

<x-layout>
    <x-slot:title>Profile analytics</x-slot:title>

    <div class="space-y-4">
        <x-ui.back-button fallback="{{ route('user.profile', ['username' => $user->username]) }}" />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <x-subcomponents.avatar :user="$user" size="size-12" />
                <div>
                    <h1 class="text-2xl font-bold">Profile analytics</h1>
                    <p class="text-sm text-base-content/70">
                        {{ '@' . $user->username }} · only you can see this
                    </p>
                </div>
            </div>

            <a href="{{ route('user.profile', ['username' => $user->username]) }}" class="btn btn-sm btn-outline">
                View profile
            </a>
        </div>

        @if ($summary['profile_visits'] === 0)
            <div class="rounded-xl border border-base-200 bg-base-200/40 p-4 text-sm text-base-content/70">
                Profile visits are only recorded when you have visit notifications enabled in
                <a href="{{ route('settings.preferences') }}" class="link link-primary">Preferences</a>.
                Your other analytics still work.
            </div>
        @endif

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach ($stats as $stat)
                <div class="rounded-xl border border-base-200 bg-base-100 p-3">
                    <div class="flex items-center gap-2 text-xs text-base-content/60">
                        <i data-lucide="{{ $stat['icon'] }}" class="h-3.5 w-3.5" aria-hidden="true"></i>
                        <span>{{ $stat['label'] }}</span>
                    </div>
                    <p class="mt-1 text-2xl font-bold">{{ number_format($stat['value']) }}</p>
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border border-base-200 bg-base-100 p-4">
            <h2 class="font-semibold">Profile visits in the last 14 days</h2>
            <p class="text-xs text-base-content/60">
                {{ number_format($chart->sum('total')) }} visits · {{ number_format($chart->sum('unique')) }} unique visitor-days
            </p>

            <div class="mt-4">
                <x-analytics.bar-chart :items="$chart" :peak="$peak" accent="bg-secondary" />
            </div>
        </div>

        @if ($recentVisitors->isNotEmpty())
            <div class="rounded-xl border border-base-200 bg-base-100 p-4">
                <h2 class="font-semibold">Recent visitors</h2>
                <ul class="mt-3 space-y-2">
                    @foreach ($recentVisitors as $visit)
                        @if ($visit->visitor)
                            <li class="flex items-center gap-3">
                                <x-subcomponents.avatar :user="$visit->visitor" size="size-8" />
                                <div class="min-w-0">
                                    <a href="{{ route('user.profile', ['username' => $visit->visitor->username]) }}"
                                       class="block truncate text-sm font-medium hover:underline">
                                        {{ $visit->visitor->dname ?: $visit->visitor->username }}
                                    </a>
                                    <p class="truncate text-xs text-base-content/60">
                                        {{ '@' . $visit->visitor->username }}
                                    </p>
                                </div>
                                <time class="ml-auto text-xs text-base-content/50">
                                    {{ $visit->visited_on?->diffForHumans() }}
                                </time>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-layout>
