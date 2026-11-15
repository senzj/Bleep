@php
    $healthBadge = static fn (string $status): string => match ($status) {
        'ok' => 'badge-success',
        'warning' => 'badge-warning',
        'error' => 'badge-error',
        default => 'badge-info',
    };

    $runBadge = static fn (?array $run): string => match ($run['status'] ?? null) {
        'success' => 'badge-success',
        'failed' => 'badge-error',
        'skipped' => 'badge-ghost',
        default => 'badge-ghost',
    };

    $runLabel = static fn (?array $run): string => match ($run['status'] ?? null) {
        'success' => 'Succeeded',
        'failed' => 'Failed',
        'skipped' => 'Skipped',
        default => 'Never run',
    };

    $healthLabel = static fn (?string $status): string => match ($status) {
        'ok' => 'Healthy',
        'warning' => 'Warnings',
        'error' => 'Errors',
        default => 'Not run yet',
    };

    $geoipSize = $geoip['size'] >= 1024 * 1024
        ? number_format($geoip['size'] / 1024 / 1024, 1).' MB'
        : number_format($geoip['size'] / 1024, 1).' KB';

    $lastRunsByKey = collect($events)->pluck('last_run', 'key');
@endphp

<x-admin.layout>
    <x-admin.system-tabs tab="schedule" />

    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold">Schedule</h1>
            <p class="mt-1 max-w-2xl text-sm text-base-content/70">
                Everything the scheduler runs, when it runs next, and whether its last run succeeded.
                The monthly health check and GeoIP refresh are listed here too.
            </p>
        </div>

        <form method="POST" action="{{ route('admin.system.health') }}" class="shrink-0">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm gap-2">
                <i data-lucide="heart-pulse" class="h-4 w-4"></i>
                Run health check now
            </button>
        </form>
    </div>

    {{-- Health + GeoIP summary --}}
    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <section class="rounded-xl border border-base-300 bg-base-100 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <i data-lucide="heart-pulse" class="h-4 w-4 text-base-content/60"></i>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-base-content/70">Automated health check</h2>
                </div>
                <span class="badge badge-sm {{ $healthBadge($health['status'] ?? 'info') }}">
                    {{ $healthLabel($health['status'] ?? null) }}
                </span>
            </div>

            <p class="mt-2 text-xs text-base-content/60">
                @if ($healthCheckedAt)
                    Last run {{ $healthCheckedAt->diffForHumans() }} ({{ $healthCheckedAt->format('M d, Y H:i') }}).
                @else
                    Never run. Run it with <code class="font-mono">php artisan system:health</code> or the button above.
                @endif
            </p>

            @if ($health)
                <div class="mt-3 space-y-1.5">
                    @foreach ($health['checks'] as $check)
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="badge badge-xs {{ $healthBadge($check['status']) }}">{{ ucfirst($check['status']) }}</span>
                            <span class="font-medium">{{ $check['label'] }}</span>
                            <span class="text-base-content/50">{{ $check['detail'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="rounded-xl border border-base-300 bg-base-100 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <i data-lucide="map-pin" class="h-4 w-4 text-base-content/60"></i>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-base-content/70">GeoIP database</h2>
                </div>
                <span class="badge badge-sm {{ $geoip['exists'] ? ($geoip['stale'] ? 'badge-warning' : 'badge-success') : 'badge-warning' }}">
                    {{ $geoip['exists'] ? ($geoip['stale'] ? 'Stale' : 'Current') : 'Missing' }}
                </span>
            </div>

            @if ($geoip['exists'])
                <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                    <div>
                        <dt class="text-base-content/50">File</dt>
                        <dd class="break-all font-mono">{{ basename((string) $geoip['path']) }}</dd>
                    </div>
                    <div>
                        <dt class="text-base-content/50">Size</dt>
                        <dd>{{ $geoipSize }}</dd>
                    </div>
                    <div>
                        <dt class="text-base-content/50">Updated</dt>
                        <dd>{{ $geoip['modified_at']?->format('M d, Y') ?? 'Unknown' }} ({{ $geoip['age_days'] }} days ago)</dd>
                    </div>
                    <div>
                        <dt class="text-base-content/50">Build</dt>
                        <dd>{{ $geoip['build_epoch'] ? \Illuminate\Support\Carbon::createFromTimestamp($geoip['build_epoch'])->format('M d, Y') : 'Unknown' }}</dd>
                    </div>
                </dl>

                @if ($geoip['stale'])
                    <p class="mt-3 rounded-lg bg-warning/10 p-2.5 text-xs text-base-content/70">
                        The database is older than {{ config('geoip.stale_after_days') }} days. The monthly refresh runs on day 3 at 04:00.
                    </p>
                @endif
            @else
                <p class="mt-2 text-sm">
                    No GeoIP database is installed. Upload a DB-IP City Lite <code class="font-mono">.mmdb</code> file or run
                    <code class="font-mono">php artisan geoip:update</code>.
                </p>
            @endif

            <p class="mt-3 text-[11px] text-base-content/40">
                IP geolocation by DB-IP City Lite, licensed under CC BY 4.0.
            </p>
        </section>
    </div>

    {{-- Commands due on a date --}}
    <section class="mb-6 rounded-xl border border-base-300 bg-base-100">
        <div class="flex flex-col gap-3 border-b border-base-300 p-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-base-content/70">Commands due</h2>
                <p class="text-xs text-base-content/50">
                    {{ $date->format('F j, Y') }} · {{ count($runs) }} {{ \Illuminate\Support\Str::plural('task', count($runs)) }},
                    {{ array_sum(array_column($runs, 'count')) }} {{ \Illuminate\Support\Str::plural('run', array_sum(array_column($runs, 'count'))) }}
                </p>
            </div>

            <form method="GET" action="{{ route('admin.system.schedule') }}" class="flex items-center gap-2">
                <label for="schedule-date" class="text-xs text-base-content/60">Date</label>
                <input id="schedule-date" type="date" name="date" value="{{ $date->format('Y-m-d') }}" class="input input-sm input-bordered">
                <button type="submit" class="btn btn-sm btn-outline">Show</button>
            </form>
        </div>

        @if ($runs === [])
            <div class="p-8 text-center">
                <i data-lucide="calendar-off" class="mx-auto mb-3 h-9 w-9 text-base-content/30"></i>
                <p class="text-sm font-medium">Nothing is scheduled to run on this date.</p>
                <p class="mt-1 text-xs text-base-content/60">Pick another day to inspect its runs.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th class="w-52">Run times</th>
                            <th>Task</th>
                            <th class="w-32 text-center">Last run</th>
                            <th class="w-28 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($runs as $run)
                            @php($last = $lastRunsByKey[$run['key']] ?? null)
                            <tr>
                                <td class="text-xs">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach (array_slice($run['times'], 0, 6) as $time)
                                            <span class="rounded bg-base-200 px-1.5 py-0.5 font-mono">{{ $time }}</span>
                                        @endforeach
                                        @if ($run['count'] > 6)
                                            <span class="self-center text-base-content/50">+{{ $run['count'] - 6 }} more</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-sm">
                                    <div class="font-medium">{{ $run['description'] }}</div>
                                    <div class="text-[11px] text-base-content/40">Cron {{ $run['expression'] }}</div>
                                </td>
                                <td class="text-center text-xs text-base-content/60">
                                    {{ $last ? \Illuminate\Support\Carbon::parse($last['ran_at'])->diffForHumans() : '—' }}
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-sm {{ $runBadge($last) }}">{{ $runLabel($last) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Full schedule --}}
    <section class="rounded-xl border border-base-300 bg-base-100">
        <div class="border-b border-base-300 p-4">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-base-content/70">All scheduled tasks</h2>
            <p class="text-xs text-base-content/50">Last-run status comes from the scheduler's own success and failure events.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th class="w-44">Schedule</th>
                        <th class="w-36">Next run</th>
                        <th class="w-36">Last run</th>
                        <th class="w-28 text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        <tr>
                            <td class="text-sm">
                                <div class="font-medium break-all">{{ $event['description'] }}</div>
                                <div class="text-[11px] text-base-content/40">Cron {{ $event['expression'] }}</div>
                            </td>
                            <td class="text-xs text-base-content/70">{{ $event['human'] }}</td>
                            <td class="text-xs">
                                @if ($event['next_run'])
                                    <div>{{ $event['next_run']->format('M d, H:i') }}</div>
                                    <div class="text-[11px] text-base-content/40">{{ $event['next_run']->diffForHumans() }}</div>
                                @else
                                    <span class="text-base-content/40">—</span>
                                @endif
                            </td>
                            <td class="text-xs">
                                @if ($event['last_run'])
                                    <div>{{ \Illuminate\Support\Carbon::parse($event['last_run']['ran_at'])->format('M d, H:i') }}</div>
                                    <div class="text-[11px] text-base-content/40">
                                        {{ \Illuminate\Support\Carbon::parse($event['last_run']['ran_at'])->diffForHumans() }}
                                        @if ($event['last_run']['runtime'] !== null)
                                            · {{ $event['last_run']['runtime'] }}s
                                        @endif
                                    </div>
                                @else
                                    <span class="text-base-content/40">Not recorded yet</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="badge badge-sm {{ $runBadge($event['last_run']) }}">{{ $runLabel($event['last_run']) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-sm text-base-content/50">No scheduled tasks are registered.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <p class="mt-4 rounded-xl border border-base-300 bg-base-200/40 p-3 text-xs text-base-content/60">
        The scheduler itself has to be driven by cron. Add
        <code class="font-mono">* * * * * cd {{ base_path() }} &amp;&amp; php artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</code>
        to the server's crontab (or a Task Scheduler job on Windows).
    </p>
</x-admin.layout>
