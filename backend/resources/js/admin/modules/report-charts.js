import { Chart, BarController, BarElement, DoughnutController, ArcElement, LinearScale, CategoryScale, Tooltip, Legend } from 'chart.js';

Chart.register(BarController, BarElement, DoughnutController, ArcElement, LinearScale, CategoryScale, Tooltip, Legend);

const PALETTE = ['#101a5c', '#ee8431', '#2c74b3', '#1f8f5f', '#c0550a', '#4b45b8', '#c98a00', '#6a6f8f'];

/**
 * Reports charts: <canvas data-report-chart="bar|doughnut" data-chart-config='{labels, datasets|data}'>.
 */
export function initReportCharts() {
    document.querySelectorAll('canvas[data-report-chart]').forEach((canvas) => {
        const config = JSON.parse(canvas.dataset.chartConfig);
        const euro = (value) => `€${Number(value).toLocaleString('en', { maximumFractionDigits: 0 })}`;

        if (canvas.dataset.reportChart === 'doughnut') {
            new Chart(canvas, {
                type: 'doughnut',
                data: { labels: config.labels, datasets: [{ data: config.data, backgroundColor: PALETTE, borderWidth: 0 }] },
                options: { maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } },
            });

            return;
        }

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: config.labels,
                datasets: config.datasets.map((dataset) => ({ label: dataset.label, data: dataset.data, backgroundColor: dataset.color, borderRadius: 4, maxBarThickness: 36 })),
            },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } }, tooltip: { callbacks: { label: (item) => `${item.dataset.label}: ${euro(item.raw)}` } } },
                scales: {
                    x: { stacked: true, grid: { display: false }, ticks: { color: '#6a6f8f', font: { size: 11 } } },
                    y: { stacked: true, beginAtZero: true, grid: { color: '#f2f3f9' }, ticks: { color: '#6a6f8f', font: { size: 11 }, callback: euro } },
                },
            },
        });
    });
}
