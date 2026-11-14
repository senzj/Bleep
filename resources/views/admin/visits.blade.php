@once
    @push('scripts')
        @vite('resources/js/admin/visits.js')
    @endpush
@endonce

<x-admin.layout>
    <div class="space-y-6">

        {{-- Header --}}
        <div class="flex flex-col flex-wrap gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-base-content">Traffic Analytics</h1>
                <p class="text-base-content/60">Monitor website traffic and visitor statistics</p>
            </div>

            {{-- Date Range Picker --}}
            <div class="join max-w-full shrink-0 shadow-md self-start sm:self-auto">
                <button class="join-item btn btn-sm btn-active" data-range="30">30 Days</button>
                <button class="join-item btn btn-sm" data-range="90">90 Days</button>
                <button class="join-item btn btn-sm" data-range="180">6 Months</button>
            </div>
        </div>

        {{-- Stats Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="stats shadow bg-base-100 border border-base-200">
                <div class="stat">
                    <div class="stat-figure text-primary bg-primary/10 p-2 rounded-full">
                        <i data-lucide="users" class="w-6 h-6"></i>
                    </div>
                    <div class="stat-title">Total Visits</div>
                    <div class="stat-value text-primary text-2xl sm:text-3xl">{{ number_format($totalVisits) }}</div>
                    <div class="stat-desc">All time recorded visits</div>
                </div>
            </div>

            <div class="stats shadow bg-base-100 border border-base-200">
                <div class="stat">
                    <div class="stat-figure text-secondary bg-secondary/10 p-2 rounded-full">
                        <i data-lucide="fingerprint" class="w-6 h-6"></i>
                    </div>
                    <div class="stat-title">Unique IPs</div>
                    <div class="stat-value text-secondary text-2xl sm:text-3xl" id="uniqueIps">{{ number_format($uniqueIPs) }}</div>
                    <div class="stat-desc">Distinct visitors</div>
                </div>
            </div>

            <div class="stats shadow bg-base-100 border border-base-200">
                <div class="stat">
                    <div class="stat-figure text-accent bg-accent/10 p-2 rounded-full">
                        <i data-lucide="globe" class="w-6 h-6"></i>
                    </div>
                    <div class="stat-title">Top Browser</div>
                    <div class="stat-value text-accent text-2xl sm:text-3xl" id="topBrowserStat">-</div>
                    <div class="stat-desc" id="topBrowserCount">Loading...</div>
                </div>
            </div>
        </div>

        {{-- Unique IPs table --}}
        <div class="card bg-base-100 shadow-sm border border-base-200">
            <div class="card-body p-4 sm:p-6">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="card-title text-lg">Unique IP Addresses</h2>
                    <span class="text-xs text-base-content/50">
                        Showing {{ $uniqueIpRows->count() }} of {{ number_format($uniqueIPs) }} distinct IPs
                    </span>
                </div>

                <div class="overflow-hidden rounded-xl border border-base-300">
                    <div class="hidden md:grid grid-cols-[1.4fr_.6fr_1fr_1.2fr] gap-3 px-4 py-3 bg-base-200 text-[11px] uppercase tracking-wide font-semibold text-center">
                        <div>IP Address</div>
                        <div>Visits</div>
                        <div>Last Seen</div>
                        <div>Browser / Platform</div>
                    </div>

                    @forelse($uniqueIpRows as $row)
                        <div class="grid grid-cols-1 md:grid-cols-[1.4fr_.6fr_1fr_1.2fr] gap-2 md:gap-3 px-4 py-4 border-t border-base-300 hover:bg-base-200/40 transition">
                            <div class="flex justify-between md:justify-center items-center">
                                <span class="md:hidden text-[10px] uppercase opacity-60 font-semibold mr-2">IP Address</span>
                                <a href="{{ route('admin.visits', ['ip' => $row->ip_address]) }}"
                                   class="link link-hover link-primary font-mono text-xs">
                                    {{ $row->ip_address }}
                                </a>
                            </div>

                            <div class="flex justify-between md:justify-center items-center">
                                <span class="md:hidden text-[10px] uppercase opacity-60 font-semibold mr-2">Visits</span>
                                <span class="font-semibold tabular-nums">{{ number_format($row->visits_count) }}</span>
                            </div>

                            <div class="flex justify-between md:justify-center items-center">
                                <span class="md:hidden text-[10px] uppercase opacity-60 font-semibold mr-2">Last Seen</span>
                                <span class="text-xs text-base-content/60">
                                    {{ \Illuminate\Support\Carbon::parse($row->last_seen_at)->diffForHumans() }}
                                </span>
                            </div>

                            <div class="flex justify-between md:justify-center items-center">
                                <span class="md:hidden text-[10px] uppercase opacity-60 font-semibold mr-2">Browser / Platform</span>
                                <span class="text-xs text-base-content/60">
                                    {{ $row->browser ?: 'Unknown browser' }}
                                    <span class="text-base-content/30">&middot;</span>
                                    {{ $row->platform ?: 'Unknown platform' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-8 text-center text-sm text-base-content/50">
                            No visits recorded yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Main Chart Section --}}
        <div class="card bg-base-100 shadow-sm border border-base-200">
            <div class="card-body p-4 sm:p-6">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <h2 class="card-title text-lg">Traffic Overview</h2>
                </div>

                <div class="h-[260px] sm:h-80 w-full relative">
                    <canvas id="visitsChart" class="w-full h-full"></canvas>
                </div>
            </div>
        </div>

        {{-- Breakdown Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            {{-- Browsers --}}
            <div class="card bg-base-100 shadow-sm border border-base-200">
                <div class="card-body p-4">
                    <h3 class="font-bold text-lg mb-3 flex items-center gap-2">
                        <i data-lucide="globe" class="w-4 h-4 text-base-content/50"></i>
                        Top Browsers
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="table table-xs table-zebra w-full">
                            <thead><tr><th>Browser</th><th class="text-right">Visits</th></tr></thead>
                            <tbody id="browsersTable">
                                <tr><td colspan="2" class="text-center py-4 text-base-content/50">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Devices --}}
            <div class="card bg-base-100 shadow-sm border border-base-200">
                <div class="card-body p-4">
                    <h3 class="font-bold text-lg mb-3 flex items-center gap-2">
                        <i data-lucide="smartphone" class="w-4 h-4 text-base-content/50"></i>
                        Top Devices
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="table table-xs table-zebra w-full">
                            <thead><tr><th>Device</th><th class="text-right">Visits</th></tr></thead>
                            <tbody id="devicesTable">
                                <tr><td colspan="2" class="text-center py-4 text-base-content/50">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Platforms --}}
            <div class="card bg-base-100 shadow-sm border border-base-200">
                <div class="card-body p-4">
                    <h3 class="font-bold text-lg mb-3 flex items-center gap-2">
                        <i data-lucide="monitor" class="w-4 h-4 text-base-content/50"></i>
                        Top Platforms
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="table table-xs table-zebra w-full">
                            <thead><tr><th>Platform</th><th class="text-right">Visits</th></tr></thead>
                            <tbody id="platformsTable">
                                <tr><td colspan="2" class="text-center py-4 text-base-content/50">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Visits Log --}}
        <div class="card bg-base-100 shadow-sm border border-base-200">
            <div class="card-body p-0 sm:p-6">

                {{-- Header + Filters --}}
                <div class="flex flex-col flex-wrap justify-between gap-4 border-b border-base-200 p-4 sm:flex-row sm:items-center">
                    <h2 class="card-title text-lg">Recent Visits Log</h2>

                    {{-- Filters: collapsible on small screens --}}
                    <details class="w-full sm:w-auto" @if(request()->anyFilled(['ip','platform','browser'])) open @endif>
                        <summary class="flex items-center gap-2 cursor-pointer p-2 rounded-md bg-base-100 border border-base-200 sm:hidden">
                            <i data-lucide="filter" class="w-4 h-4"></i>
                            <span class="text-sm">Filters</span>
                        </summary>

                        <form method="GET" action="{{ route('admin.visits') }}" class="flex flex-col gap-2 p-3 sm:flex-row sm:flex-wrap sm:items-center sm:p-0">
                            <input type="text" name="ip" placeholder="Search IP..." value="{{ request('ip') }}"
                                   class="input input-sm input-bordered w-full sm:w-40" />

                            <select name="platform" class="select select-sm select-bordered w-full sm:w-auto">
                                <option value="">All Platforms</option>
                                @foreach($platforms as $p)
                                    <option value="{{ $p }}" {{ request('platform') == $p ? 'selected' : '' }}>{{ $p }}</option>
                                @endforeach
                            </select>

                            <select name="browser" class="select select-sm select-bordered w-full sm:w-auto">
                                <option value="">All Browsers</option>
                                @foreach($browsers as $b)
                                    <option value="{{ $b }}" {{ request('browser') == $b ? 'selected' : '' }}>{{ $b }}</option>
                                @endforeach
                            </select>

                            <div class="flex items-center gap-2">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i data-lucide="filter" class="w-4 h-4"></i>
                                    <span class="hidden sm:inline"> Filter</span>
                                </button>

                                @if(request()->anyFilled(['ip', 'platform', 'browser']))
                                    <a href="{{ route('admin.visits') }}" class="btn btn-sm btn-ghost text-error" title="Clear filters">
                                        <i data-lucide="x" class="w-4 h-4"></i>
                                    </a>
                                @endif
                            </div>
                        </form>
                    </details>
                </div>

                {{-- Visit rows --}}
                <div class="overflow-hidden rounded-xl border border-base-300 mx-4 sm:mx-6">
                    <div class="hidden md:grid grid-cols-[1fr_1fr_1.6fr] gap-3 px-4 py-3 bg-base-200 text-[11px] uppercase tracking-wide font-semibold text-center">
                        <div>Date &amp; Time</div>
                        <div>IP Address</div>
                        <div>Device Info</div>
                    </div>

                    @forelse($recentVisits as $v)
                        <div class="grid grid-cols-1 md:grid-cols-[1fr_1fr_1.6fr] gap-2 md:gap-3 px-4 py-4 border-t border-base-300 hover:bg-base-200/40 transition">
                            <div class="flex justify-between md:justify-center items-center">
                                <span class="md:hidden text-[10px] uppercase opacity-60 font-semibold mr-2">Date &amp; Time</span>
                                <div class="text-center">
                                    <div class="text-xs font-bold">{{ $v->created_at->format('M d, Y') }}</div>
                                    <div class="text-xs text-base-content/50">{{ $v->created_at->format('H:i:s') }}</div>
                                </div>
                            </div>

                            <div class="flex justify-between md:justify-center items-center">
                                <span class="md:hidden text-[10px] uppercase opacity-60 font-semibold mr-2">IP Address</span>
                                <a href="{{ route('admin.visits', ['ip' => $v->ip_address]) }}"
                                   class="font-mono text-xs hover:underline hover:text-primary">
                                    {{ $v->ip_address }}
                                </a>
                            </div>

                            <div class="flex justify-between md:justify-center items-center">
                                <span class="md:hidden text-[10px] uppercase opacity-60 font-semibold mr-2">Device Info</span>
                                <div class="text-center text-sm">
                                    {!! $v->parsed_ua !!}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-8 text-center text-sm text-base-content/50">
                            No visits found matching your filters.
                        </div>
                    @endforelse
                </div>

                {{-- Pagination --}}
                @if($recentVisits->hasPages())
                    <x-pagination :paginator="$recentVisits" class="justify-center mt-4" />
                @endif
            </div>
        </div>
    </div>
</x-admin.layout>
