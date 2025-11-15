@push('meta')
    <meta name="admin-dashboard-url" content="{{ route('admin.dashboard.chart-data') }}">
@endpush

@push('scripts')
    @vite('resources/js/admin/dashboard.js')
@endpush

<x-admin.layout>
    <div class="mb-8">

        {{-- Header --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold">Admin Dashboard</h1>
                <p class="text-sm opacity-70 mt-1">
                    Overview of system health, users, sessions, reports, and devices.
                </p>
            </div>

            {{-- Action Buttons (mobile scrollable) --}}
            <div class="flex gap-2 overflow-x-auto pb-1">
                <a href="{{ route('admin.users') }}" class="btn btn-sm btn-outline whitespace-nowrap">Manage Users</a>
                <a href="{{ route('admin.devices') }}" class="btn btn-sm btn-outline whitespace-nowrap">Sessions & Devices</a>
                <a href="{{ route('admin.reports') }}" class="btn btn-sm btn-outline whitespace-nowrap">View Reports</a>
            </div>
        </div>

        {{-- Stats Section (Horizontal scroll on mobile) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-6 overflow-x-auto">
            @php
                $stats = [
                    ['label' => 'Total Users', 'value' => $totalUsers, 'desc' => 'New today: '.$newToday],
                    ['label' => 'Active Now', 'value' => $activeSessions, 'class' => 'text-success', 'desc' => 'Last 5 minutes'],
                    ['label' => 'Total Sessions', 'value' => $totalSessions],
                    ['label' => 'Remembered Devices', 'value' => $totalDevices, 'class' => 'text-warning'],
                    ['label' => 'Reports', 'value' => $reportsPending + $reportsOngoing, 'desc' => "Pending: $reportsPending · Ongoing: $reportsOngoing"]
                ];
            @endphp

            @foreach ($stats as $stat)
                <div class="stat bg-base-100 rounded-xl border border-base-300 min-w-[200px]">
                    <div class="stat-title">{{ $stat['label'] }}</div>
                    <div class="stat-value text-2xl {{ $stat['class'] ?? '' }}">
                        {{ number_format($stat['value']) }}
                    </div>
                    @isset($stat['desc'])
                        <div class="stat-desc">{{ $stat['desc'] }}</div>
                    @endisset
                </div>
            @endforeach
        </div>

        {{-- Chart Filters --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
            <div class="flex items-center gap-3">
                <label class="text-sm opacity-70">Show</label>
                <select id="dashboard-range" class="select select-sm w-36">
                    <option value="daily" {{ request('range','daily')=='daily'?'selected':'' }}>Last 30 days</option>
                    <option value="weekly" {{ request('range')=='weekly'?'selected':'' }}>Last 12 weeks</option>
                    <option value="monthly" {{ request('range')=='monthly'?'selected':'' }}>Last 12 months</option>
                    <option value="yearly" {{ request('range')=='yearly'?'selected':'' }}>Last 5 years</option>
                </select>
            </div>

            <div class="flex gap-2 overflow-x-auto">
                <button id="dashboard-refresh" class="btn btn-sm btn-ghost whitespace-nowrap">Refresh</button>
                <a href="{{ route('admin.reports') }}" class="btn btn-sm btn-outline whitespace-nowrap">Open Reports</a>
            </div>
        </div>

        {{-- Charts Grid --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">

            {{-- Users Chart --}}
            <div class="bg-base-100 p-4 rounded-xl border border-base-300">
                <h3 class="text-sm uppercase opacity-70 mb-2">Users</h3>
                <details class="md:block">
                    <summary class="md:hidden cursor-pointer flex items-center justify-between">
                        <span class="text-sm">Users (tap to expand)</span>
                        <span class="text-xs opacity-70">New: {{ number_format($newToday) }}</span>
                    </summary>
                    <div class="mt-2 h-44 md:h-52 overflow-hidden">
                        <canvas id="admin-dashboard-chart" class="w-full h-full"></canvas>
                    </div>
                </details>
            </div>

            {{-- Device OS Chart --}}
            <div class="bg-base-100 p-4 rounded-xl border border-base-300">
                <h3 class="text-sm uppercase opacity-70 mb-2">Device OS Distribution</h3>
                <div class="h-44 md:h-52">
                    <canvas id="admin-top-os-chart"></canvas>
                </div>
            </div>

            {{-- Browser Chart --}}
            <div class="bg-base-100 p-4 rounded-xl border border-base-300">
                <h3 class="text-sm uppercase opacity-70 mb-2">Top Browsers</h3>
                <div class="h-44 md:h-52">
                    <canvas id="admin-top-browser-chart"></canvas>
                </div>
            </div>

            {{-- Hourly Sessions --}}
            <div class="bg-base-100 p-4 rounded-xl border border-base-300">
                <h3 class="text-sm uppercase opacity-70 mb-2">Hourly Sessions</h3>
                <div class="h-44 md:h-52">
                    <canvas id="admin-hourly-activity-chart"></canvas>
                </div>
            </div>

            {{-- Reports by Category --}}
            <div class="bg-base-100 p-4 rounded-xl border border-base-300">
                <h3 class="text-sm uppercase opacity-70 mb-2">Reports by Category</h3>
                <div class="h-44 md:h-52">
                    <canvas id="admin-reports-category-chart"></canvas>
                </div>
            </div>

            {{-- Top Bleeps --}}
            <div class="bg-base-100 p-4 rounded-xl border border-base-300">
                <h3 class="text-sm uppercase opacity-70 mb-2">Top Bleeps</h3>
                <div id="top-bleeps-list" class="divide-y"></div>
            </div>

        </div>
    </div>
</x-admin.layout>
