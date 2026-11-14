@php
    $badgeClass = static function (string $level): string {
        return match ($level) {
            'EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR' => 'badge-error',
            'WARNING' => 'badge-warning',
            'NOTICE', 'INFO' => 'badge-info',
            default => 'badge-ghost',
        };
    };

    $errorCount = ($levelCounts['ERROR'] ?? 0) + ($levelCounts['CRITICAL'] ?? 0) + ($levelCounts['ALERT'] ?? 0) + ($levelCounts['EMERGENCY'] ?? 0);
    $warningCount = $levelCounts['WARNING'] ?? 0;
@endphp

<x-admin.layout>
    <x-admin.system-tabs tab="logs" />

    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold">System Logs</h1>
            <p class="mt-1 text-sm text-base-content/70">
                Read-only tail of the application log. Only developers and admins can open this page.
            </p>
            <p class="mt-1 break-all font-mono text-[11px] text-base-content/40">{{ $path }}</p>
        </div>

        <div class="flex flex-wrap gap-2">
            <span class="badge {{ $errorCount ? 'badge-error' : 'badge-success' }} gap-1.5">
                <i data-lucide="circle-alert" class="h-3.5 w-3.5"></i>
                {{ $errorCount }} {{ \Illuminate\Support\Str::plural('error', $errorCount) }}
            </span>
            <span class="badge {{ $warningCount ? 'badge-warning' : 'badge-ghost' }} gap-1.5">
                <i data-lucide="alert-triangle" class="h-3.5 w-3.5"></i>
                {{ $warningCount }} {{ \Illuminate\Support\Str::plural('warning', $warningCount) }}
            </span>
            <a href="{{ route('admin.system.logs') }}" class="btn btn-outline btn-sm gap-2">
                <i data-lucide="refresh-cw" class="h-4 w-4"></i>
                Refresh
            </a>
        </div>
    </div>

    {{-- File facts --}}
    <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-xl border border-base-300 bg-base-100 p-4">
            <div class="text-xs uppercase tracking-wide text-base-content/50">File status</div>
            <div class="mt-1 text-lg font-semibold {{ $exists ? 'text-success' : 'text-error' }}">
                {{ $exists ? 'Found' : 'Missing' }}
            </div>
        </div>
        <div class="rounded-xl border border-base-300 bg-base-100 p-4">
            <div class="text-xs uppercase tracking-wide text-base-content/50">Size</div>
            <div class="mt-1 text-lg font-semibold">
                {{ $size >= 1024 * 1024 ? number_format($size / 1024 / 1024, 2).' MB' : number_format($size / 1024, 1).' KB' }}
            </div>
        </div>
        <div class="rounded-xl border border-base-300 bg-base-100 p-4">
            <div class="text-xs uppercase tracking-wide text-base-content/50">Last written</div>
            <div class="mt-1 text-sm font-semibold">
                {{ $modifiedAt?->diffForHumans() ?? 'Never' }}
            </div>
        </div>
        <div class="rounded-xl border border-base-300 bg-base-100 p-4">
            <div class="text-xs uppercase tracking-wide text-base-content/50">Entries in tail</div>
            <div class="mt-1 text-lg font-semibold">{{ number_format(count($entries)) }}</div>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.system.logs') }}" class="mb-4 flex flex-col gap-2 rounded-xl border border-base-300 bg-base-200/40 p-3 sm:flex-row sm:items-center">
        <select name="level" class="select select-sm select-bordered sm:w-44">
            <option value="">All levels</option>
            @foreach ($levels as $option)
                <option value="{{ $option }}" @selected($level === $option)>{{ ucfirst(strtolower($option)) }}</option>
            @endforeach
        </select>

        <input
            type="search"
            name="q"
            value="{{ $search }}"
            placeholder="Search messages, paths or stack traces"
            class="input input-sm input-bordered flex-1"
        >

        <div class="flex items-center gap-2">
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
            <a href="{{ route('admin.system.logs') }}" class="btn btn-sm btn-ghost">Reset</a>
        </div>
    </form>

    @if (! $exists)
        <div class="rounded-xl border border-base-300 bg-base-100 p-8 text-center">
            <i data-lucide="file-x" class="mx-auto mb-3 h-10 w-10 text-base-content/30"></i>
            <p class="font-medium">No application log has been written yet.</p>
            <p class="mt-1 text-sm text-base-content/60">Once something is logged, it will appear here.</p>
        </div>
    @elseif ($entries === [])
        <div class="rounded-xl border border-base-300 bg-base-100 p-8 text-center">
            <i data-lucide="search-x" class="mx-auto mb-3 h-10 w-10 text-base-content/30"></i>
            <p class="font-medium">No entries match these filters.</p>
            <p class="mt-1 text-sm text-base-content/60">Try a different level or search term.</p>
        </div>
    @else
        <div class="space-y-2">
            @foreach ($entries as $entry)
                <article class="rounded-xl border border-base-300 bg-base-100 p-3 sm:p-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge badge-sm {{ $badgeClass($entry['level']) }}">{{ $entry['level'] }}</span>
                        <span class="font-mono text-xs text-base-content/50">{{ $entry['timestamp'] }}</span>
                        @if ($entry['environment'])
                            <span class="badge badge-ghost badge-xs">{{ $entry['environment'] }}</span>
                        @endif
                        @if ($entry['context'] !== '')
                            <span class="ml-auto text-[11px] text-base-content/40">has stack trace</span>
                        @endif
                    </div>

                    <p class="mt-2 break-words text-sm">{{ \Illuminate\Support\Str::limit($entry['message'], 500) }}</p>

                    @if ($entry['context'] !== '')
                        <details class="mt-2">
                            <summary class="cursor-pointer text-xs font-medium text-primary">Show full entry</summary>
                            <pre class="mt-2 max-h-72 overflow-auto whitespace-pre-wrap break-words rounded-lg bg-base-200 p-3 text-[11px] leading-relaxed text-base-content/80">{{ $entry['raw'] }}</pre>
                        </details>
                    @endif
                </article>
            @endforeach
        </div>

        <p class="mt-4 text-center text-xs text-base-content/50">
            Showing the most recent {{ count($entries) }} entries from the tail of the log file.
        </p>
    @endif
</x-admin.layout>
