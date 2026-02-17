import { createChart, updateChart, simpleSeries } from '../chart';

// Use window var -> meta tag -> fallback
function getApiUrl() {
    if (window.ADMIN_DASHBOARD_URL) return window.ADMIN_DASHBOARD_URL;
    const meta = document.querySelector('meta[name="admin-dashboard-url"]');
    if (meta) {
        return meta.getAttribute('content') || '/admin/dashboard/chart-data';
    }
    return '/admin/dashboard/chart-data';
}

const charts = {};
let elements = {};
let chartObservers = []; // keep observers to disconnect on navigation

// Simple debounce util
function debounce(fn, wait = 150) {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), wait);
    };
}

function isMobile() {
    return window.matchMedia('(max-width: 640px)').matches;
}

function getResponsiveOptions(type) {
    // Return different options depending on viewport
    if (isMobile()) {
        return {
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 8 } } },
            elements: { point: { radius: 1 } }
        };
    }
    return {
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
    };
}

function ensureElements() {
    elements = {
        reportsCanvas: document.getElementById('admin-reports-category-chart'),
        osCanvas: document.getElementById('admin-top-os-chart'),
        browserCanvas: document.getElementById('admin-top-browser-chart'),
        hourlyCanvas: document.getElementById('admin-hourly-activity-chart'),
        rangeSelect: document.getElementById('dashboard-range'),
        refreshBtn: document.getElementById('dashboard-refresh'),
    };
}

function mountChartOnVisible(canvasEl, createFn) {
    if (!canvasEl) return;
    const io = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                createFn(); // create chart when visible
                io.unobserve(entry.target);
            }
        });
    }, { threshold: 0.05 });
    io.observe(canvasEl);
    chartObservers.push(io);
}

// Repeat pattern for other charts — for heavy charts (like browser distro) use lazy mount and smaller options
function buildReportsChart(data) {
    const canvas = elements.reportsCanvas;
    if (!canvas || !data) return;

    const createReports = () => {
        const labels = Object.keys(data.byCategory);
        const values = Object.values(data.byCategory);
        const ds = [{ label:'Reports by category', data: values, backgroundColor: ['#ef4444','#f59e0b','#3b82f6','#10b981'] }];
        const options = getResponsiveOptions('doughnut');
        if (!charts.reports) charts.reports = createChart(canvas, 'doughnut', labels, ds, options);
        else updateChart(charts.reports, labels, ds);
    };

    if (isMobile()) mountChartOnVisible(canvas, createReports); else createReports();
}

function buildDeviceCharts(data) {
    if (!data) return;
    const osCanvas = elements.osCanvas;
    const browserCanvas = elements.browserCanvas;
    if (osCanvas && Array.isArray(data.os)) {
        const labels = data.os.map(d => d.label);
        const values = data.os.map(d => d.value);
        const ds = [{ label: 'OS', data: values, backgroundColor: undefined }];
        const opts = getResponsiveOptions('pie');
        if (!charts.os) charts.os = createChart(osCanvas, 'pie', labels, ds, opts);
        else updateChart(charts.os, labels, ds);
    }
    if (browserCanvas && Array.isArray(data.browser)) {
        const labels = data.browser.map(d => d.label);
        const values = data.browser.map(d => d.value);
        const ds = [{ label: 'Browsers', data: values, backgroundColor: undefined }];
        const opts = getResponsiveOptions('bar');
        if (!charts.browser) charts.browser = createChart(browserCanvas, 'bar', labels, ds, opts);
        else updateChart(charts.browser, labels, ds);
    }
}

function buildHourlyChart(sessions) {
    if (!sessions) return;
    const canvas = elements.hourlyCanvas;
    if (!canvas) return;

    // If a different canvas or you want to re-create, destroy previous instance first
    if (charts.hourly && charts.hourly.canvas !== canvas) {
        charts.hourly.destroy();
        delete charts.hourly;
    }

    const labels = Array.from({ length: 24 }, (_, i) => String(i));
    const dataMap = sessions.hourly || {};
    const values = labels.map(l => Number(dataMap[l] ?? dataMap[Number(l)] ?? 0));
    const ds = [ simpleSeries('Hourly sessions', values, 2, 'bar') ];
    const opts = getResponsiveOptions('bar');
    if (!charts.hourly) charts.hourly = createChart(canvas, 'bar', labels, ds, opts);
    else updateChart(charts.hourly, labels, ds);
}

// After fetch, call buildX functions:
async function fetchDashboard(range = 'daily') {
    const url = `${getApiUrl()}?range=${encodeURIComponent(range)}`;
    const resp = await fetch(url, { credentials: 'same-origin' });
    if (!resp.ok) {
        console.error('Failed to load dashboard data');
        return;
    }
    const payload = await resp.json();

    // Render parts
    buildReportsChart(payload.reports);
    buildDeviceCharts(payload.devices);
    buildHourlyChart(payload.sessions);
}

// Handle resize to update chart size and toggles
const handleResize = debounce(() => {
    Object.values(charts).forEach(chart => {
        if (chart && typeof chart.resize === 'function') {
            chart.resize();
            // update responsive plugins (legend position) if needed
            const opts = isMobile() ? { plugins: { legend: { position: 'bottom' } } } : { plugins: { legend: { position: 'bottom' } } };
            chart.options = Object.assign(chart.options || {}, opts);
            chart.update({ duration: 0 });
        }
    });
}, 200);

function init() {
    ensureElements();

    const initialRange = elements.rangeSelect?.value ?? 'daily';
    fetchDashboard(initialRange);

    elements.rangeSelect?.addEventListener('change', (e) => {
        // on range change, clear old charts & fetch new data
        // optionally destroy existing charts to free memory
        Object.values(charts).forEach(c => { if (c?.destroy) c.destroy(); });
        for (const k in charts) delete charts[k];
        chartObservers.forEach(o => o.disconnect());
        chartObservers = [];
        fetchDashboard(e.target.value);
    });

    elements.refreshBtn?.addEventListener('click', () => {
        Object.values(charts).forEach(c => { if (c?.destroy) c.destroy(); });
        for (const k in charts) delete charts[k];
        chartObservers.forEach(o => o.disconnect()); chartObservers = [];
        fetchDashboard(elements.rangeSelect?.value ?? 'daily');
    });

    window.addEventListener('resize', handleResize);
}

document.addEventListener('DOMContentLoaded', init);

// small helper: prevent raw HTML injection
function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return String(unsafe)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
