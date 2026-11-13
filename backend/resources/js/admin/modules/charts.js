import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Filler } from 'chart.js';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Filler);

/**
 * <canvas data-chart='{"labels":[...],"datasets":[{"label":"Bookings","data":[...],"color":"#101a5c"}]}'>
 */
export function initCharts() {
    document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
        const config = JSON.parse(canvas.dataset.chart);

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: config.labels,
                datasets: config.datasets.map((dataset) => ({
                    label: dataset.label,
                    data: dataset.data,
                    borderColor: dataset.color,
                    backgroundColor: dataset.color,
                    yAxisID: dataset.axis ?? 'y',
                    borderWidth: 2,
                    tension: 0.3,
                    pointRadius: 0,
                    pointHoverRadius: 4,
                })),
            },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#6a6f8f', maxTicksLimit: 6, font: { size: 11 } } },
                    y: { beginAtZero: true, grid: { color: '#f2f3f9' }, ticks: { color: '#6a6f8f', precision: 0, font: { size: 11 } } },
                    y1: { beginAtZero: true, position: 'right', display: config.datasets.some((dataset) => dataset.axis === 'y1'), grid: { display: false }, ticks: { color: '#c0550a', font: { size: 11 }, callback: (value) => `€${value}` } },
                },
            },
        });
    });
}
