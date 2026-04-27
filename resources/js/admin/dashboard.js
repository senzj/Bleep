import { createChart, updateChart, simpleSeries } from '../chart';

function getApiUrl() {
    const meta = document.querySelector('meta[name="admin-dashboard-url"]');
    return meta?.getAttribute('content') || '/admin/dashboard/chart-data';
}

const charts = {};
let elements = {};

const DAYS = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

function debounce(fn, wait = 150) {
    let t;

    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
}

function isMobile() {
    return window.matchMedia('(max-width: 640px)').matches;
}

function getResponsiveOptions(extra = {}) {
    const base = {
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { position: 'bottom', labels: { boxWidth: isMobile() ? 10 : 14, usePointStyle: true } } },
    };

    return Object.assign(base, extra);
}

function ensureElements() {
    elements = {
        reportsCanvas:        document.getElementById('admin-reports-category-chart'),
        osCanvas:             document.getElementById('admin-top-os-chart'),
        browserCanvas:        document.getElementById('admin-top-browser-chart'),
        hourlyCanvas:         document.getElementById('admin-hourly-activity-chart'),
        usersTrendCanvas:     document.getElementById('admin-users-trend-chart'),
        bleepsTrendCanvas:    document.getElementById('admin-bleeps-trend-chart'),
        sessionsTrendCanvas:  document.getElementById('admin-sessions-trend-chart'),
        engagementTrendCanvas:document.getElementById('admin-engagement-trend-chart'),
        anonCanvas:           document.getElementById('admin-anon-chart'),
        rolesCanvas:          document.getElementById('admin-roles-chart'),
        reportStatusCanvas:   document.getElementById('admin-report-status-chart'),
        rangeSelect:          document.getElementById('dashboard-range'),
        refreshBtn:           document.getElementById('dashboard-refresh'),
    };
}

function fmt(v) { return Number(v || 0).toLocaleString(); }
function write(id, v) { const el = document.getElementById(id); if (el) el.textContent = v; }

function avg(arr) {
    if (!arr?.length) return 0;

    return Math.round(arr.reduce((a, b) => a + b, 0) / arr.length);
}

function peakLabel(labels, series) {
    if (!series?.length || !labels?.length) return '—';

    const maxVal = Math.max(...series);
    const idx = series.indexOf(maxVal);

    return `${labels[idx]} (${fmt(maxVal)})`;
}

function createOrUpdate(key, canvas, type, labels, datasets, options = {}) {
    if (!canvas) return;

    const isCartesian = ['line', 'bar', 'scatter', 'bubble'].includes(type);

    // Only apply the index-mode interaction override for cartesian charts
    const baseOptions = isCartesian
        ? getResponsiveOptions(options)
        : {
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: isMobile() ? 10 : 14, usePointStyle: true } },
            },
            ...options,
        };

    if (!charts[key]) {
        charts[key] = createChart(canvas, type, labels, datasets, baseOptions);
    } else {
        Object.assign(charts[key].options, baseOptions);
        updateChart(charts[key], labels, datasets);
    }
}

function buildTrendCharts(payload) {
    const labels = payload?.meta?.labels || payload?.users?.labels || [];

    if (!labels.length) return;

    // User growth
    const userSeries = payload?.users?.series?.[0];

    if (elements.usersTrendCanvas && userSeries) {
        const ds = [simpleSeries('New users', userSeries, 0, 'line')];

        createOrUpdate('usersTrend', elements.usersTrendCanvas, 'line', labels, ds);
        write('chart-users-peak-label', peakLabel(labels, userSeries));
        write('chart-users-avg-label', fmt(avg(userSeries)));
        write('kpi-avg-daily-users', fmt(avg(userSeries)));
    }

    // Bleeps
    const bleepSeries = payload?.bleeps?.series?.[0];
    if (elements.bleepsTrendCanvas && bleepSeries) {
        const ds = [simpleSeries('New bleeps', bleepSeries, 1, 'bar')];

        createOrUpdate('bleepsTrend', elements.bleepsTrendCanvas, 'bar', labels, ds);

        write('chart-bleeps-peak-label', peakLabel(labels, bleepSeries));
        write('chart-bleeps-avg-label', fmt(avg(bleepSeries)));
        write('kpi-avg-daily-bleeps', fmt(avg(bleepSeries)));
    }

    // Sessions
    const sessionSeries = payload?.sessions?.series?.[0];
    if (elements.sessionsTrendCanvas && sessionSeries) {
        const ds = [simpleSeries('Sessions', sessionSeries, 6, 'line')];

        createOrUpdate('sessionsTrend', elements.sessionsTrendCanvas, 'line', labels, ds);
        write('chart-sessions-peak-label', peakLabel(labels, sessionSeries));
    }

    // Engagement
    if (elements.engagementTrendCanvas && payload?.bleeps?.interactions) {
        const i = payload.bleeps.interactions;

        const ds = [
            simpleSeries('Likes',    i.likes    || [], 0, 'line'),
            simpleSeries('Comments', i.comments || [], 3, 'line'),
            simpleSeries('Shares',   i.shares   || [], 2, 'line'),
            simpleSeries('Reposts',  i.reposts  || [], 4, 'line'),
        ];
        createOrUpdate('engagementTrend', elements.engagementTrendCanvas, 'line', labels, ds);
    }
}

function buildReportsChart(data) {
    if (!data || !elements.reportsCanvas) return;

    const labels = Object.keys(data.byCategory || {});
    const values = Object.values(data.byCategory || {});

    createOrUpdate('reports', elements.reportsCanvas, 'doughnut', labels, [{
        label: 'Reports',
        data: values,
        backgroundColor: ['#ef4444','#f59e0b','#3b82f6','#10b981','#ec4899','#6366f1'],
    }]);
}

function buildReportStatusChart(data) {
    if (!elements.reportStatusCanvas) return;

    const pending  = data?.pending  || 0;
    const ongoing  = data?.ongoing  || 0;
    const resolved = data?.resolved || 0;

    // Chart.js doughnut with all-zero data renders nothing — show a placeholder slice
    const hasData = pending + ongoing + resolved > 0;

    const chartData = hasData
        ? [pending, ongoing, resolved]
        : [1]; // placeholder so the chart visually renders

    const chartLabels = hasData
        ? ['Pending', 'Ongoing', 'Resolved']
        : ['No data'];

    const bgColors = hasData
        ? ['#f59e0b', '#3b82f6', '#10b981']
        : ['#e5e7eb'];

    createOrUpdate('reportStatus', elements.reportStatusCanvas, 'doughnut',
        chartLabels,
        [{ label: 'Reports', data: chartData, backgroundColor: bgColors }]
    );
}

function buildDeviceCharts(data) {
    if (!data) return;

    if (elements.osCanvas && Array.isArray(data.os)) {
        createOrUpdate('os', elements.osCanvas, 'pie',
            data.os.map(d => d.label),
            [{ label: 'OS', data: data.os.map(d => d.value) }]
        );
    }

    if (elements.browserCanvas && Array.isArray(data.browser)) {
        createOrUpdate('browser', elements.browserCanvas, 'bar',
            data.browser.map(d => d.label),
            [{ label: 'Browsers', data: data.browser.map(d => d.value) }]
        );
    }
}

function buildHourlyChart(sessions) {
    if (!sessions || !elements.hourlyCanvas) return;

    const labels = Array.from({ length: 24 }, (_, i) => `${i}:00`);
    const dataMap = sessions.hourly || {};
    const values = Array.from({ length: 24 }, (_, i) => Number(dataMap[i] ?? 0));

    createOrUpdate('hourly', elements.hourlyCanvas, 'bar', labels,
        [simpleSeries('Sessions', values, 2, 'bar')]
    );
}

function buildAnonChart(bleeps, comments) {
    if (!bleeps || !elements.anonCanvas) return;

    const anonBleeps   = bleeps.anonymous || 0;
    const pubBleeps    = (bleeps.total || 0) - anonBleeps;
    const nsfwBleeps   = bleeps.nsfw || 0;
    const anonComments = comments?.anonymous || 0;

    createOrUpdate('anon', elements.anonCanvas, 'doughnut',
        ['Public Bleeps', 'Anonymous Bleeps', 'NSFW Bleeps', 'Anonymous Comments'],
        [{
            label: 'Content breakdown',
            data: [pubBleeps, anonBleeps, nsfwBleeps, anonComments],
            backgroundColor: ['#3b82f6', '#6366f1', '#ef4444', '#a855f7'],
        }]
    );
}

function buildRolesChart(users) {
    if (!users?.roles || !elements.rolesCanvas) return;

    const roles  = { ...users.roles };
    const labels = [...Object.keys(roles), 'Banned'];
    const values = [...Object.values(roles), users.banned || 0];

    createOrUpdate('roles', elements.rolesCanvas, 'doughnut', labels,
        [{ label: 'Users', data: values, backgroundColor: ['#10b981','#f59e0b','#ef4444','#6b7280'] }]
    );
}

function updateKpis(payload) {
    write('kpi-users-total',      fmt(payload?.users?.total));
    write('kpi-users-active',     fmt(payload?.users?.active));
    write('kpi-sessions-active',  fmt(payload?.sessions?.activeSessions));
    write('kpi-bleeps-total',     fmt(payload?.bleeps?.total));
    write('kpi-bleeps-today',     fmt(payload?.bleeps?.today));
    write('kpi-reports-open',     fmt((payload?.reports?.pending || 0) + (payload?.reports?.ongoing || 0)));
    write('kpi-engagement-total', fmt((payload?.bleeps?.likes || 0) + (payload?.bleeps?.comments || 0) + (payload?.bleeps?.shares || 0) + (payload?.bleeps?.reposts || 0)));
    write('kpi-engagement-today', fmt(payload?.bleeps?.engagementToday));
    write('kpi-engagement-rate',  (payload?.bleeps?.engagementRate ?? '—'));
    write('kpi-likes-total',      fmt(payload?.bleeps?.likes));
    write('kpi-comments-total',   fmt(payload?.bleeps?.comments));
    write('kpi-shares-total',     fmt(payload?.bleeps?.shares));
    write('kpi-reposts-total',    fmt(payload?.bleeps?.reposts));

    // Peak hour/day from sessions
    const peakHour = payload?.sessions?.peakHour;
    const peakDay  = payload?.sessions?.peakDay;

    write('kpi-peak-hour', peakHour != null ? `${peakHour}:00` : '—');
    write('kpi-peak-day',  peakDay  != null ? (DAYS[peakDay - 1] ?? peakDay) : '—');

    write('kpi-anon-bleeps',    fmt(payload?.bleeps?.anonymous));
    write('kpi-nsfw-bleeps',    fmt(payload?.bleeps?.nsfw));
    write('kpi-anon-comments',  fmt(payload?.comments?.anonymous));
    write('kpi-comments-total', fmt(payload?.comments?.total || payload?.bleeps?.comments));
    write('kpi-banned-users',   fmt(payload?.users?.banned));
    write('kpi-nsfw-comments',  fmt(payload?.comments?.nsfw));
}

function destroyCharts() {
    Object.values(charts).forEach(c => c?.destroy?.());
    Object.keys(charts).forEach(k => delete charts[k]);
}

async function fetchDashboard(range = 'daily') {
    try {
        const resp = await fetch(`${getApiUrl()}?range=${encodeURIComponent(range)}`, { credentials: 'same-origin' });

        if (!resp.ok) {
            console.error('Dashboard fetch failed');
            return;
        }

        const payload = await resp.json();

        updateKpis(payload);
        buildTrendCharts(payload);
        buildReportsChart(payload.reports);
        buildReportStatusChart(payload.reports);
        buildDeviceCharts(payload.devices);
        buildHourlyChart(payload.sessions);
        buildAnonChart(payload.bleeps, payload.comments);
        buildRolesChart(payload.users);

    } catch (e) {
        console.error('Dashboard error:', e);
    }
}

const handleResize = debounce(() => {
    Object.values(charts).forEach(c => {
        if (c?.resize) { c.resize(); c.update({ duration: 0 }); }
    });
}, 200);

function init() {
    ensureElements();
    fetchDashboard(elements.rangeSelect?.value ?? 'daily');

    elements.rangeSelect?.addEventListener('change',
        e => {
            destroyCharts();
            fetchDashboard(e.target.value);
        }
    );

    elements.refreshBtn?.addEventListener('click',
        () => {
            destroyCharts();
            fetchDashboard(elements.rangeSelect?.value ?? 'daily');
        }
    );
    
    window.addEventListener('resize', handleResize);
}

document.addEventListener('DOMContentLoaded', init);
