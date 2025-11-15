import Chart from 'chart.js/auto';

const defaultColors = [
    '#3B82F6','#10B981','#F59E0B','#EF4444','#8B5CF6','#14B8A6','#F97316', '#60A5FA'
];

export function createChart(canvasEl, type, labels, datasets, customOptions = {}) {
    if (!(canvasEl instanceof HTMLCanvasElement)) {
        throw new Error('Canvas element required.');
    }
    const ctx = canvasEl.getContext('2d');
    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false, // rely on wrapper's CSS height
        animation: false, // avoid layout flicker
        plugins: {
            legend: { position: 'bottom' },
            tooltip: { mode: 'index', intersect: false }
        },
        scales: {
            y: { beginAtZero: true },
            x: { ticks: { autoSkip: true, maxRotation: 0, minRotation: 0 } }
        }
    };
    const config = {
        type,
        data: { labels, datasets },
        options: Object.assign({}, defaultOptions, customOptions)
    };
    return new Chart(ctx, config);
}

export function updateChart(chartInstance, labels, datasets) {
    if (!chartInstance) return;
    chartInstance.data.labels = labels;
    chartInstance.data.datasets = datasets;
    chartInstance.update();
}

export function simpleSeries(label, data, colorIndex=0, type='line') {
    const color = defaultColors[colorIndex % defaultColors.length];
    if (type === 'bar') {
        return { label, data, backgroundColor: color, borderColor: color };
    }
    return { label, data, borderColor: color, backgroundColor: color, fill: false, tension: 0.2 };
}
