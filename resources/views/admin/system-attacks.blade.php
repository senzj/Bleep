@php
    $severityBadge = static fn (string $severity): string => $severity === 'confirmed' ? 'badge-error' : 'badge-warning';
    $severityLabel = static fn (string $severity): string => $severity === 'confirmed' ? 'Confirmed' : 'Possible';
@endphp

<x-admin.layout>
    <x-admin.system-tabs tab="attacks" />

    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold">Attacks</h1>
            <p class="mt-1 max-w-2xl text-sm text-base-content/70">
                Signals of possible and confirmed attacks against the site. Findings come from failed
                sign-ins, attack-shaped log entries and unusually dense session clusters. Nothing is
                blocked automatically.
            </p>
        </div>

        <a href="{{ route('admin.system.attacks') }}" class="btn btn-outline btn-sm gap-2 self-start">
            <i data-lucide="refresh-cw" class="h-4 w-4"></i>
            Rescan
        </a>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-base-300 bg-base-100 p-4">
            <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-base-content/50">
                <i data-lucide="shield-alert" class="h-4 w-4 text-error"></i>
                Confirmed
            </div>
            <div class="mt-1 text-3xl font-bold {{ $summary['confirmed'] ? 'text-error' : '' }}">{{ $summary['confirmed'] }}</div>
        </div>
        <div class="rounded-xl border border-base-300 bg-base-100 p-4">
            <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-base-content/50">
                <i data-lucide="alert-triangle" class="h-4 w-4 text-warning"></i>
                Possible
            </div>
            <div class="mt-1 text-3xl font-bold {{ $summary['possible'] ? 'text-warning' : '' }}">{{ $summary['possible'] }}</div>
        </div>
        <div class="rounded-xl border border-base-300 bg-base-100 p-4">
            <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-base-content/50">
                <i data-lucide="clock" class="h-4 w-4"></i>
                Scanned
            </div>
            <div class="mt-1 text-sm font-semibold">{{ $summary['scanned_at']->diffForHumans() }}</div>
            <div class="text-[11px] text-base-content/40">Last 24 hours of signals</div>
        </div>
    </div>

    @if ($findings === [])
        <div class="rounded-xl border border-base-300 bg-base-100 p-8 text-center">
            <i data-lucide="shield-check" class="mx-auto mb-3 h-10 w-10 text-success"></i>
            <p class="font-medium">No attack signals in the last 24 hours.</p>
            <p class="mt-1 text-sm text-base-content/60">
                Failed sign-ins, attack-shaped log entries and session clusters are all below their thresholds.
            </p>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($findings as $finding)
                <article class="rounded-xl border border-base-300 bg-base-100 p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge badge-sm {{ $severityBadge($finding['severity']) }}">
                            {{ $severityLabel($finding['severity']) }}
                        </span>
                        <h2 class="text-sm font-semibold">{{ $finding['title'] }}</h2>
                        <span class="ml-auto font-mono text-xs text-base-content/60">{{ $finding['subject'] }}</span>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-base-content/60">
                        <span class="inline-flex items-center gap-1">
                            <i data-lucide="hash" class="h-3.5 w-3.5"></i>
                            {{ number_format($finding['count']) }} {{ \Illuminate\Support\Str::plural('hit', $finding['count']) }}
                        </span>
                        @if ($finding['last_seen'])
                            <span class="inline-flex items-center gap-1">
                                <i data-lucide="clock" class="h-3.5 w-3.5"></i>
                                Last seen {{ $finding['last_seen']->diffForHumans() }}
                            </span>
                        @endif
                    </div>

                    <p class="mt-2 text-sm text-base-content/80">{{ $finding['details'] }}</p>

                    <p class="mt-2 flex gap-2 rounded-lg bg-base-200/60 p-2.5 text-xs text-base-content/70">
                        <i data-lucide="lightbulb" class="mt-0.5 h-3.5 w-3.5 shrink-0"></i>
                        <span>{{ $finding['recommendation'] }}</span>
                    </p>
                </article>
            @endforeach
        </div>
    @endif
</x-admin.layout>
