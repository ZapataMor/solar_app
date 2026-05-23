import Chart from 'chart.js/auto';

const activeSolarCharts = new Map();
let solarThemeObserver = null;

const getSolarChartColors = () => {
    const styles = getComputedStyle(document.documentElement);

    return {
        text: styles.getPropertyValue('--solar-text-muted').trim() || '#715841',
        grid: styles.getPropertyValue('--solar-border').trim() || 'rgba(113, 88, 65, 0.12)',
        gold: styles.getPropertyValue('--solar-sun').trim() || '#d1842e',
        goldDark: styles.getPropertyValue('--solar-gold').trim() || '#a85c1e',
        sand: styles.getPropertyValue('--solar-sun-soft').trim() || '#efb35f',
        success: styles.getPropertyValue('--solar-success').trim() || '#5b8a5d',
        successDark: styles.getPropertyValue('--solar-success').trim() || '#456f47',
        danger: styles.getPropertyValue('--solar-danger').trim() || '#c96a58',
        clay: styles.getPropertyValue('--solar-text').trim() || '#9c6540',
        tooltipBg: document.documentElement.classList.contains('dark') ? 'rgba(16, 12, 8, 0.94)' : 'rgba(53, 37, 21, 0.92)',
        tooltipTitle: styles.getPropertyValue('--solar-text').trim() || '#fff7ee',
        tooltipBody: styles.getPropertyValue('--solar-text-muted').trim() || '#f8e7cf',
    };
};

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
                color: getSolarChartColors().text,
                usePointStyle: true,
                pointStyle: 'circle',
                padding: 18,
            },
        },
        tooltip: {
            backgroundColor: getSolarChartColors().tooltipBg,
            titleColor: getSolarChartColors().tooltipTitle,
            bodyColor: getSolarChartColors().tooltipBody,
            borderColor: getSolarChartColors().sand,
            borderWidth: 1,
            callbacks: tooltipFormatter ? {
                label: tooltipFormatter,
            } : {},
        },
    },
    scales: {
        x: {
            ticks: {
                color: getSolarChartColors().text,
            },
            grid: {
                color: getSolarChartColors().grid,
            },
        },
        y: {
            beginAtZero: true,
            title: {
                display: true,
                text: yAxisTitle,
                color: getSolarChartColors().text,
            },
            ticks: {
                color: getSolarChartColors().text,
            },
            grid: {
                color: getSolarChartColors().grid,
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
    const weatherStationDataElement = document.getElementById('weather-station-chart-data');

    destroySolarCharts();

    if (dataElement) {
        const solarColors = getSolarChartColors();
        const chartData = JSON.parse(dataElement.textContent);
        const labels = chartData.labels ?? [];

        if (labels.length > 0) {
            createChart('solar-generation-chart', {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Generacion estimada kWh',
                        data: chartData.generation ?? [],
                        backgroundColor: solarColors.gold,
                        borderColor: solarColors.goldDark,
                        borderWidth: 1,
                        borderRadius: 10,
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
                            backgroundColor: solarColors.success,
                            borderColor: solarColors.successDark,
                            borderWidth: 1,
                            borderRadius: 10,
                        },
                        {
                            label: 'Consumo mensual kWh',
                            data: chartData.consumption ?? [],
                            backgroundColor: solarColors.clay,
                            borderColor: solarColors.clay,
                            borderWidth: 1,
                            borderRadius: 10,
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
                        backgroundColor: solarColors.success,
                        borderColor: solarColors.successDark,
                        borderWidth: 1,
                        borderRadius: 10,
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
                        backgroundColor: `${solarColors.sand}33`,
                        borderColor: solarColors.gold,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: document.documentElement.classList.contains('dark') ? '#1d1711' : '#fff7ee',
                        pointBorderColor: solarColors.goldDark,
                        pointBorderWidth: 2,
                        pointRadius: 3,
                    }],
                },
                options: baseOptions('%', (context) => `${context.dataset.label}: ${numberFormatter.format(context.parsed.y)}%`),
            });
        }
    }

    if (weatherStationDataElement) {
        const solarColors = getSolarChartColors();
        const weatherStationData = JSON.parse(weatherStationDataElement.textContent);
        const weatherStationLabels = weatherStationData.labels ?? [];

        if (weatherStationLabels.length > 0) {
            createChart('weather-station-radiation-chart', {
                type: 'line',
                data: {
                    labels: weatherStationLabels,
                    datasets: [{
                        label: 'Radiacion centro meteorologico',
                        data: weatherStationData.radiation ?? [],
                        backgroundColor: `${solarColors.gold}29`,
                        borderColor: solarColors.gold,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.35,
                        pointBackgroundColor: document.documentElement.classList.contains('dark') ? '#1d1711' : '#fff7ee',
                        pointBorderColor: solarColors.goldDark,
                        pointBorderWidth: 2,
                        pointRadius: 3,
                    }],
                },
                options: baseOptions('Radiacion', (context) => `${context.dataset.label}: ${numberFormatter.format(context.parsed.y)}`),
            });
        }
    }
};

const observeSolarTheme = () => {
    if (solarThemeObserver) {
        return;
    }

    solarThemeObserver = new MutationObserver((mutations) => {
        if (mutations.some((mutation) => mutation.attributeName === 'class')) {
            initSolarCharts();
        }
    });

    solarThemeObserver.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['class'],
    });
};

document.addEventListener('DOMContentLoaded', initSolarCharts);
document.addEventListener('DOMContentLoaded', observeSolarTheme);
document.addEventListener('livewire:navigated', initSolarCharts);
