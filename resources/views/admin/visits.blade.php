@push('scripts')
    @vite('resources/js/admin/visits.js')
@endpush

<x-admin.layout>
    <div class="space-y-6">

        {{-- Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-base-content">Traffic Analytics</h1>
                <p class="text-base-content/60">Monitor website traffic and visitor statistics</p>
            </div>

            {{-- Date Range Picker --}}
            <div class="join shadow-md self-start sm:self-auto">
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
                <div class="p-4 border-b border-base-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <h2 class="card-title text-lg">Recent Visits Log</h2>

                    {{-- Filters: collapsible on small screens --}}
                    <details class="w-full sm:w-auto" @if(request()->anyFilled(['ip','platform','browser'])) open @endif>
                        <summary class="flex items-center gap-2 cursor-pointer p-2 rounded-md bg-base-100 border border-base-200 sm:hidden">
                            <i data-lucide="filter" class="w-4 h-4"></i>
                            <span class="text-sm">Filters</span>
                        </summary>

                        <form method="GET" action="{{ route('admin.visits') }}" class="flex flex-col sm:flex-row sm:items-center gap-2 p-3 sm:p-0">
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

                {{-- Desktop/Tablet Table (md+) --}}
                <div class="overflow-x-auto hidden md:block">
                    <table class="table table-zebra w-full">
                        <thead>
                            <tr>
                                <th class="w-40 text-center">Time</th>
                                <th class="w-32 text-center">IP Address</th>
                                <th class="w-50 text-center">Device Info</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentVisits as $v)
                                <tr class="hover">
                                    <td class="whitespace-nowrap text-xs text-center">
                                        <div class="font-bold">{{ $v->created_at->format('M d, Y') }}</div>
                                        <div class="text-base-content/50">{{ $v->created_at->format('H:i:s') }}</div>
                                    </td>
                                    <td class="font-mono text-xs text-center">
                                        <a href="{{ route('admin.visits', ['ip' => $v->ip_address]) }}" class="hover:underline hover:text-primary">
                                            {{ $v->ip_address }}
                                        </a>
                                    </td>
                                    <td class="text-center">
                                        <div class="text-sm">
                                            {!! $v->parsed_ua !!}
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-8 text-base-content/50">
                                        No visits found matching your filters.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Mobile stacked list --}}
                <div class="md:hidden space-y-3 p-4">
                    @forelse($recentVisits as $v)
                        <div class="bg-base-100 border border-base-200 rounded-lg p-3 shadow-sm">
                            <div class="flex justify-between items-start gap-2">
                                <div>
                                    <div class="text-sm font-semibold">{{ $v->created_at->format('M d, Y') }}</div>
                                    <div class="text-xs text-base-content/50">{{ $v->created_at->format('H:i:s') }}</div>
                                </div>
                                <div class="text-xs font-mono text-right">
                                    <a href="{{ route('admin.visits', ['ip' => $v->ip_address]) }}" class="hover:underline text-sm">{{ $v->ip_address }}</a>
                                </div>
                            </div>

                            <div class="mt-2 text-sm">
                                {!! $v->parsed_ua !!}
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-base-content/50 py-8">
                            No visits found matching your filters.
                        </div>
                    @endforelse
                </div>

                {{-- Pagination --}}
                @if($recentVisits->hasPages())
                    <div class="p-4 border-t border-base-200">
                        {{ $recentVisits->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-admin.layout>
