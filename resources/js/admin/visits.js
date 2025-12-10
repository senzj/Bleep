import { createChart, updateChart, simpleSeries } from '../chart.js';

document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('visitsChart');
    let chartInstance = null;

    // Range buttons
    const rangeButtons = document.querySelectorAll('[data-range]');

    // Tables
    const browsersTable = document.getElementById('browsersTable');
    const devicesTable = document.getElementById('devicesTable');
    const platformsTable = document.getElementById('platformsTable');

    // Stats
    const topBrowserStat = document.getElementById('topBrowserStat');
    const topBrowserCount = document.getElementById('topBrowserCount');

    async function fetchData(days = 30) {
        try {
            const response = await fetch(`/admin/visits/data?days=${days}`);
            const data = await response.json();
            updateDashboard(data);
        } catch (error) {
            console.error('Error fetching visits data:', error);
        }
    }

    function updateDashboard(data) {
        // 1. Update Chart
        const dataset = simpleSeries('Visits', data.series, 0, 'line');
        // Add fill for better look
        dataset.fill = true;
        dataset.backgroundColor = 'rgba(59, 130, 246, 0.1)';
        dataset.tension = 0.3;

        if (chartInstance) {
            updateChart(chartInstance, data.labels, [dataset]);
        } else {
            chartInstance = createChart(ctx, 'line', data.labels, [dataset], {
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: (context) => ` ${context.parsed.y} Visits`
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { borderDash: [2, 4] }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            });
        }

        // 2. Update Top Stats
        if (data.top.browsers.length > 0) {
            topBrowserStat.textContent = data.top.browsers[0].label;
            topBrowserCount.textContent = `${data.top.browsers[0].value} visits`;
        }

        // 3. Update Tables
        renderTable(browsersTable, data.top.browsers);
        renderTable(devicesTable, data.top.devices);
        renderTable(platformsTable, data.top.platforms);
    }

    function renderTable(tbody, items) {
        tbody.innerHTML = items.map(item => `
            <tr>
                <td class="text-xs font-medium">${item.label}</td>
                <td class="text-xs text-right font-mono">${item.value}</td>
            </tr>
        `).join('');
    }

    // Event Listeners
    rangeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            // Update active state
            rangeButtons.forEach(b => b.classList.remove('btn-active'));
            btn.classList.add('btn-active');

            // Fetch data
            fetchData(btn.dataset.range);
        });
    });

    // Initial load
    fetchData(30);
});
