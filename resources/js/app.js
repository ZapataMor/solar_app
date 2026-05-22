import Chart from 'chart.js/auto';

const activeSolarCharts = new Map();

const destroySolarCharts = () => {
    activeSolarCharts.forEach((chart) => chart.destroy());
    activeSolarCharts.clear();
};

const createChart = (id, config) => {
    const canvas = document.getElementById(id);

    if (!canvas) {
        return;
    }

    activeSolarCharts.set(id, new Chart(canvas, config));
};

const baseOptions = (yAxisTitle, tooltipFormatter = null) => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            labels: {
                color: '#71717a',
            },
        },
        tooltip: {
            callbacks: tooltipFormatter ? {
                label: tooltipFormatter,
            } : {},
        },
    },
    scales: {
        x: {
            ticks: {
                color: '#71717a',
            },
            grid: {
                color: 'rgba(113, 113, 122, 0.12)',
            },
        },
        y: {
            beginAtZero: true,
            title: {
                display: true,
                text: yAxisTitle,
                color: '#71717a',
            },
            ticks: {
                color: '#71717a',
            },
            grid: {
                color: 'rgba(113, 113, 122, 0.12)',
            },
        },
    },
});

const numberFormatter = new Intl.NumberFormat('es-CO', {
    maximumFractionDigits: 2,
});

const moneyFormatter = new Intl.NumberFormat('es-CO', {
    currency: 'COP',
    maximumFractionDigits: 0,
    style: 'currency',
});

const initSolarCharts = () => {
    const dataElement = document.getElementById('solar-monthly-chart-data');

    destroySolarCharts();

    if (!dataElement) {
        return;
    }

    const chartData = JSON.parse(dataElement.textContent);
    const labels = chartData.labels ?? [];

    if (labels.length === 0) {
        return;
    }

    createChart('solar-generation-chart', {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Generacion estimada kWh',
                data: chartData.generation ?? [],
                backgroundColor: '#f59e0b',
                borderColor: '#d97706',
                borderWidth: 1,
            }],
        },
        options: baseOptions('kWh', (context) => `${context.dataset.label}: ${numberFormatter.format(context.parsed.y)} kWh`),
    });

    createChart('solar-consumption-generation-chart', {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Generacion mensual kWh',
                    data: chartData.generation ?? [],
                    backgroundColor: '#22c55e',
                    borderColor: '#16a34a',
                    borderWidth: 1,
                },
                {
                    label: 'Consumo mensual kWh',
                    data: chartData.consumption ?? [],
                    backgroundColor: '#3b82f6',
                    borderColor: '#2563eb',
                    borderWidth: 1,
                },
            ],
        },
        options: baseOptions('kWh', (context) => `${context.dataset.label}: ${numberFormatter.format(context.parsed.y)} kWh`),
    });

    createChart('solar-savings-chart', {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Ahorro estimado COP',
                data: chartData.savings ?? [],
                backgroundColor: '#14b8a6',
                borderColor: '#0f766e',
                borderWidth: 1,
            }],
        },
        options: baseOptions('COP', (context) => `${context.dataset.label}: ${moneyFormatter.format(context.parsed.y)}`),
    });

    createChart('solar-coverage-chart', {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: 'Cobertura %',
                data: chartData.coverage ?? [],
                backgroundColor: 'rgba(168, 85, 247, 0.16)',
                borderColor: '#9333ea',
                borderWidth: 2,
                fill: true,
                tension: 0.35,
            }],
        },
        options: baseOptions('%', (context) => `${context.dataset.label}: ${numberFormatter.format(context.parsed.y)}%`),
    });
};

document.addEventListener('DOMContentLoaded', initSolarCharts);
document.addEventListener('livewire:navigated', initSolarCharts);
