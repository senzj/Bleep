@push('meta')
    <meta name="admin-dashboard-url" content="{{ route('admin.dashboard.chart-data') }}">
@endpush

@push('scripts')
    @vite('resources/js/admin/dashboard.js')
@endpush

<x-admin.layout>
<div class="mb-8 space-y-6">

    {{-- Header --}}
    <div class="rounded-2xl border border-base-300 bg-gradient-to-r from-base-200 to-base-100 p-5 md:p-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-black tracking-tight">Admin Dashboard</h1>
                <p class="text-sm opacity-70 mt-1 max-w-2xl">
                    Live overview of user growth, engagement, sessions, and system health.
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <select id="dashboard-range" class="select select-sm w-44">
                    <option value="daily"   {{ request('range','daily')=='daily'   ? 'selected' : '' }}>Last 30 days</option>
                    <option value="weekly"  {{ request('range')=='weekly'  ? 'selected' : '' }}>Last 12 weeks</option>
                    <option value="monthly" {{ request('range')=='monthly' ? 'selected' : '' }}>Last 12 months</option>
                    <option value="yearly"  {{ request('range')=='yearly'  ? 'selected' : '' }}>Last 5 years</option>
                </select>
                <button id="dashboard-refresh" class="btn btn-sm btn-outline gap-2">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                    Refresh
                </button>
            </div>
        </div>
    </div>

    {{-- KPI Row 1: Core --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="stat bg-base-100 rounded-2xl border border-base-300 shadow-sm">
            <div class="stat-figure text-primary"><i data-lucide="users" class="w-8 h-8"></i></div>
            <div class="stat-title text-xs">Total Users</div>
            <div class="stat-value text-primary text-2xl" id="kpi-users-total">{{ number_format($totalUsers) }}</div>
            <div class="stat-desc">+{{ number_format($newToday) }} today</div>
        </div>

        <div class="stat bg-base-100 rounded-2xl border border-base-300 shadow-sm">
            <div class="stat-figure text-success"><i data-lucide="activity" class="w-8 h-8"></i></div>
            <div class="stat-title text-xs">Online Now</div>
            <div class="stat-value text-success text-2xl" id="kpi-users-active">{{ number_format($activeSessions) }}</div>
            <div class="stat-desc">Active sessions: <span id="kpi-sessions-active">{{ number_format($activeSessions) }}</span></div>
        </div>

        <div class="stat bg-base-100 rounded-2xl border border-base-300 shadow-sm">
            <div class="stat-figure text-info"><i data-lucide="file-text" class="w-8 h-8"></i></div>
            <div class="stat-title text-xs">Total Bleeps</div>
            <div class="stat-value text-info text-2xl" id="kpi-bleeps-total">{{ number_format($totalBleeps) }}</div>
            <div class="stat-desc">Today: <span id="kpi-bleeps-today">{{ number_format($bleepsToday) }}</span></div>
        </div>

        <div class="stat bg-base-100 rounded-2xl border border-base-300 shadow-sm">
            <div class="stat-figure text-warning"><i data-lucide="flag" class="w-8 h-8"></i></div>
            <div class="stat-title text-xs">Open Reports</div>
            <div class="stat-value text-warning text-2xl" id="kpi-reports-open">{{ number_format($reportsPending + $reportsOngoing) }}</div>
            <div class="stat-desc">Pending: {{ number_format($reportsPending) }} · Ongoing: {{ number_format($reportsOngoing) }}</div>
        </div>
    </div>

    {{-- KPI Row 2: Averages & Peaks --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3">
        <div class="stat bg-base-100 rounded-2xl border border-base-300 shadow-sm py-3">
            <div class="stat-title text-xs">Avg Daily Users</div>
            <div class="stat-value text-base-content text-xl" id="kpi-avg-daily-users">—</div>
            <div class="stat-desc">Over selected range</div>
        </div>

        <div class="stat bg-base-100 rounded-2xl border border-base-300 shadow-sm py-3">
            <div class="stat-title text-xs">Avg Daily Bleeps</div>
            <div class="stat-value text-base-content text-xl" id="kpi-avg-daily-bleeps">—</div>
            <div class="stat-desc">Over selected range</div>
        </div>

        <div class="stat bg-base-100 rounded-2xl border border-base-300 shadow-sm py-3">
            <div class="stat-title text-xs">Peak Active Hour</div>
            <div class="stat-value text-base-content text-xl" id="kpi-peak-hour">—</div>
            <div class="stat-desc">Last 7 days</div>
        </div>

        <div class="stat bg-base-100 rounded-2xl border border-base-300 shadow-sm py-3">
            <div class="stat-title text-xs">Peak Active Day</div>
            <div class="stat-value text-base-content text-xl" id="kpi-peak-day">—</div>
            <div class="stat-desc">Last 8 weeks</div>
        </div>

        <div class="stat bg-base-100 rounded-2xl border border-base-300 shadow-sm py-3">
            <div class="stat-title text-xs">Engagement Rate</div>
            <div class="stat-value text-secondary text-xl" id="kpi-engagement-rate">—</div>
            <div class="stat-desc">Interactions / bleep</div>
        </div>

        <div class="stat bg-base-100 rounded-2xl border border-base-300 shadow-sm py-3">
            <div class="stat-title text-xs">Total Engagement</div>
            <div class="stat-value text-secondary text-xl" id="kpi-engagement-total">{{ number_format($totalEngagement) }}</div>
            <div class="stat-desc">Today: <span id="kpi-engagement-today">0</span></div>
        </div>

        <div class="stat bg-base-100 rounded-2xl border border-base-300 shadow-sm py-3">
            <div class="stat-title text-xs">Anonymous Bleeps</div>
            <div class="stat-value text-base-content text-xl" id="kpi-anon-bleeps">—</div>
            <div class="stat-desc">of total bleeps</div>
        </div>

        <div class="stat bg-base-100 rounded-2xl border border-base-300 shadow-sm py-3">
            <div class="stat-title text-xs">NSFW Bleeps</div>
            <div class="stat-value text-error text-xl" id="kpi-nsfw-bleeps">—</div>
            <div class="stat-desc">Flagged content</div>
        </div>

        <div class="stat bg-base-100 rounded-2xl border border-base-300 shadow-sm py-3">
            <div class="stat-title text-xs">NSFW Comments</div>
            <div class="stat-value text-error text-xl" id="kpi-nsfw-comments">—</div>
            <div class="stat-desc">Flagged comment</div>
        </div>

        <div class="stat bg-base-100 rounded-2xl border border-base-300 shadow-sm py-3">
            <div class="stat-title text-xs">Anonymous Comments</div>
            <div class="stat-value text-base-content text-xl" id="kpi-anon-comments">—</div>
            <div class="stat-desc">of total comments</div>
        </div>
    </div>

    {{-- User Growth + Reports --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4 xl:col-span-2">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide opacity-70">User Growth Trend</h3>
                <div class="flex gap-3 text-xs opacity-60">
                    <span>Peak: <strong id="chart-users-peak-label">—</strong></span>
                    <span>Avg: <strong id="chart-users-avg-label">—</strong>/period</span>
                </div>
            </div>
            <div class="h-56 md:h-64"><canvas id="admin-users-trend-chart"></canvas></div>
        </div>

        <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide opacity-70 mb-3">Reports by Category</h3>
            <div class="h-56 md:h-64"><canvas id="admin-reports-category-chart"></canvas></div>
        </div>
    </div>

    {{-- Bleeps + Sessions --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide opacity-70">Bleep Posts Trend</h3>
                <div class="flex gap-3 text-xs opacity-60">
                    <span>Peak: <strong id="chart-bleeps-peak-label">—</strong></span>
                    <span>Avg: <strong id="chart-bleeps-avg-label">—</strong>/period</span>
                </div>
            </div>
            <div class="h-52 md:h-60"><canvas id="admin-bleeps-trend-chart"></canvas></div>
        </div>

        <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold uppercase tracking-wide opacity-70">Sessions Trend</h3>
                <div class="flex gap-3 text-xs opacity-60">
                    <span>Peak: <strong id="chart-sessions-peak-label">—</strong></span>
                </div>
            </div>
            <div class="h-52 md:h-60"><canvas id="admin-sessions-trend-chart"></canvas></div>
        </div>
    </div>

    {{-- Engagement --}}
    <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold uppercase tracking-wide opacity-70">Engagement Trend</h3>
            <div class="flex gap-4 text-xs opacity-60">
                <span>Likes: <strong id="kpi-likes-total">—</strong></span>
                <span>Comments: <strong id="kpi-comments-total">—</strong></span>
                <span>Shares: <strong id="kpi-shares-total">—</strong></span>
                <span>Reposts: <strong id="kpi-reposts-total">—</strong></span>
            </div>
        </div>
        <div class="h-56 md:h-64"><canvas id="admin-engagement-trend-chart"></canvas></div>
    </div>

    {{-- Device / Browser / Hourly --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide opacity-70 mb-3">Device OS</h3>
            <div class="h-44 md:h-52"><canvas id="admin-top-os-chart"></canvas></div>
        </div>

        <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide opacity-70 mb-3">Top Browsers</h3>
            <div class="h-44 md:h-52"><canvas id="admin-top-browser-chart"></canvas></div>
        </div>

        <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide opacity-70 mb-3">Hourly Activity (Last 7 Days)</h3>
            <div class="h-44 md:h-52"><canvas id="admin-hourly-activity-chart"></canvas></div>
        </div>
    </div>

    {{-- New: Anonymous vs Public + Banned users --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide opacity-70 mb-3">
                Content Breakdown (Anon / NSFW)
            </h3>
            <div class="h-44 md:h-52"><canvas id="admin-anon-chart"></canvas></div>
        </div>

        <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide opacity-70 mb-3">User Roles Distribution</h3>
            <div class="h-44 md:h-52"><canvas id="admin-roles-chart"></canvas></div>
        </div>

        <div class="rounded-2xl border border-base-300 bg-base-100 p-4">
            <h3 class="text-sm font-semibold uppercase tracking-wide opacity-70 mb-3">Report Status Breakdown</h3>
            <div class="h-44 md:h-52"><canvas id="admin-report-status-chart"></canvas></div>
        </div>
    </div>

</div>
</x-admin.layout>
