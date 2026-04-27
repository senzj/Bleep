import Chart from 'chart.js/auto';

const defaultColors = [
    '#3B82F6','#10B981','#F59E0B','#EF4444','#8B5CF6','#14B8A6','#F97316','#60A5FA',
    '#EC4899','#06B6D4','#84CC16','#A855F7', '#22D3EE','#F43F5E','#6366F1','#2DD4BF',
    '#F87171','#2563EB','#34D399','#FBBF24','#E11D48','#7C3AED','#14B8A6','#F59E0B',
    '#EF4444','#3B82F6','#10B981','#F97316','#8B5CF6','#06B6D4','#F43F5E','#84CC16',
];

const CARTESIAN_TYPES = ['line', 'bar', 'scatter', 'bubble'];

export function createChart(canvasEl, type, labels, datasets, customOptions = {}) {
    if (!(canvasEl instanceof HTMLCanvasElement)) {
        throw new Error('Canvas element required.');
    }

    const ctx = canvasEl.getContext('2d');

    const isCartesian = CARTESIAN_TYPES.includes(type);

    const defaultOptions = {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: { mode: isCartesian ? 'index' : 'nearest', intersect: !isCartesian },
        },
        ...(isCartesian && {
            scales: {
                y: { beginAtZero: true },
                x: { ticks: { autoSkip: true, maxRotation: 0, minRotation: 0 } },
            },
        }),
    };

    return new Chart(ctx, {
        type,
        data: { labels, datasets },
        options: Object.assign({}, defaultOptions, customOptions),
    });
}

export function updateChart(chartInstance, labels, datasets) {
    if (!chartInstance) return;
    chartInstance.data.labels = labels;
    chartInstance.data.datasets = datasets;
    chartInstance.update();
}

export function simpleSeries(label, data, colorIndex = 0, type = 'line') {
    const color = defaultColors[colorIndex % defaultColors.length];
    if (type === 'bar') {
        return { label, data, backgroundColor: color, borderColor: color };
    }
    return { label, data, borderColor: color, backgroundColor: color, fill: false, tension: 0.2 };
}
