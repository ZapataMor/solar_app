import Chart from 'chart.js/auto';

const activeSolarCharts = new Map();
let solarThemeObserver = null;

const getSolarChartColors = () => {
    const styles = getComputedStyle(document.documentElement);
    const isDark = document.documentElement.classList.contains('dark');

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
        tooltipBg: isDark ? 'rgba(16, 12, 8, 0.96)' : 'rgba(43, 28, 16, 0.96)',
        tooltipTitle: isDark ? '#fff6ea' : '#fff6ea',
        tooltipBody: isDark ? '#f3dcc0' : '#f0dcc4',
        tooltipBorder: isDark ? 'rgba(255, 209, 141, 0.4)' : 'rgba(239, 179, 95, 0.42)',
        pointSurface: isDark ? '#1d1711' : '#fff7ee',
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
            borderColor: getSolarChartColors().tooltipBorder,
            borderWidth: 1,
            displayColors: true,
            padding: 12,
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
                        hoverBackgroundColor: solarColors.goldDark,
                        hoverBorderColor: solarColors.goldDark,
                        hoverBorderWidth: 2,
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
                            hoverBackgroundColor: solarColors.successDark,
                            hoverBorderColor: solarColors.successDark,
                            hoverBorderWidth: 2,
                        },
                        {
                            label: 'Consumo mensual kWh',
                            data: chartData.consumption ?? [],
                            backgroundColor: solarColors.clay,
                            borderColor: solarColors.clay,
                            borderWidth: 1,
                            borderRadius: 10,
                            hoverBackgroundColor: solarColors.goldDark,
                            hoverBorderColor: solarColors.goldDark,
                            hoverBorderWidth: 2,
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
                        hoverBackgroundColor: solarColors.successDark,
                        hoverBorderColor: solarColors.successDark,
                        hoverBorderWidth: 2,
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
                        pointBackgroundColor: solarColors.pointSurface,
                        pointBorderColor: solarColors.goldDark,
                        pointBorderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: solarColors.goldDark,
                        pointHoverBorderColor: solarColors.pointSurface,
                        pointHoverBorderWidth: 2,
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
                        pointBackgroundColor: solarColors.pointSurface,
                        pointBorderColor: solarColors.goldDark,
                        pointBorderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: solarColors.goldDark,
                        pointHoverBorderColor: solarColors.pointSurface,
                        pointHoverBorderWidth: 2,
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

const apiDataCountFormatter = new Intl.NumberFormat('es-CO', {
    maximumFractionDigits: 0,
});

let weatherStationSyncTimer = null;
let weatherStationSyncController = null;

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const renderWeatherStationRows = (rows) => {
    if (!rows.length) {
        return `
            <tr>
                <td colspan="12" class="py-10 text-center">
                    Aun no hay lecturas registradas desde el centro meteorologico.
                </td>
            </tr>
        `;
    }

    return rows.map((row) => `
        <tr>
            <td class="font-semibold text-[color:var(--solar-text)]">${escapeHtml(row.recorded_at)}</td>
            <td>${escapeHtml(row.device_code)}</td>
            <td>${escapeHtml(row.radiation)}</td>
            <td>${escapeHtml(row.temperature)}</td>
            <td>${escapeHtml(row.humidity)}</td>
            <td>${escapeHtml(row.thermal_sensation)}</td>
            <td>${escapeHtml(row.co2)}</td>
            <td>${escapeHtml(row.pm25)}</td>
            <td>${escapeHtml(row.pm10)}</td>
            <td>${escapeHtml(row.uva)}</td>
            <td>${escapeHtml(row.uvb)}</td>
            <td>${escapeHtml(row.uv_index)}</td>
        </tr>
    `).join('');
};

const setWeatherStationStatus = (section, message, tone = 'neutral') => {
    const status = section.querySelector('[data-weather-station-status]');

    if (!status) {
        return;
    }

    status.textContent = message;
    status.classList.toggle('text-red-600', tone === 'error');
    status.classList.toggle('dark:text-red-300', tone === 'error');
    status.classList.toggle('text-zinc-500', tone !== 'error');
    status.classList.toggle('dark:text-zinc-400', tone !== 'error');
};

const updateWeatherStationDom = (section, payload) => {
    const stationCount = Number(payload.weatherStationCount ?? 0);
    const formattedStationCount = apiDataCountFormatter.format(stationCount);
    const stationCountElements = document.querySelectorAll('[data-weather-station-count]');
    const stationCountPill = section.querySelector('[data-weather-station-count-pill]');
    const totalCountElement = document.querySelector('[data-api-data-total-count]');
    const nasaCountElement = document.querySelector('[data-api-data-nasa-count]');
    const rowsElement = section.querySelector('[data-weather-station-rows]');

    stationCountElements.forEach((element) => {
        element.textContent = formattedStationCount;
        element.dataset.count = String(stationCount);
    });

    if (stationCountPill) {
        stationCountPill.textContent = `${formattedStationCount} registros`;
    }

    if (totalCountElement && nasaCountElement) {
        const nasaCount = Number(nasaCountElement.dataset.count ?? 0);
        totalCountElement.textContent = apiDataCountFormatter.format(nasaCount + stationCount);
    }

    if (rowsElement) {
        rowsElement.innerHTML = renderWeatherStationRows(payload.rows ?? []);
    }
};

const syncWeatherStationData = async (section, { manual = false } = {}) => {
    const form = section.querySelector('[data-weather-station-fetch-form]');

    if (!form || weatherStationSyncController) {
        return;
    }

    if (!manual && document.visibilityState !== 'visible') {
        return;
    }

    weatherStationSyncController = new AbortController();
    setWeatherStationStatus(section, manual ? 'Consultando estacion...' : 'Buscando nuevas lecturas...');

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(form),
            credentials: 'same-origin',
            signal: weatherStationSyncController.signal,
        });

        const payload = await response.json();

        if (!response.ok) {
            throw new Error(payload.message ?? 'No fue posible actualizar los datos.');
        }

        updateWeatherStationDom(section, payload);
        setWeatherStationStatus(section, payload.message ?? 'Datos actualizados.');
    } catch (error) {
        if (error.name !== 'AbortError') {
            setWeatherStationStatus(section, error.message, 'error');
        }
    } finally {
        weatherStationSyncController = null;
    }
};

const initWeatherStationSync = () => {
    const section = document.querySelector('[data-weather-station-sync]');

    if (weatherStationSyncTimer) {
        clearInterval(weatherStationSyncTimer);
        weatherStationSyncTimer = null;
    }

    if (weatherStationSyncController) {
        weatherStationSyncController.abort();
        weatherStationSyncController = null;
    }

    if (!section) {
        return;
    }

    const form = section.querySelector('[data-weather-station-fetch-form]');
    const interval = Number(section.dataset.syncInterval ?? 15000);

    if (form && !form.dataset.weatherStationSubmitBound) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            syncWeatherStationData(section, { manual: true });
        });
        form.dataset.weatherStationSubmitBound = 'true';
    }

    syncWeatherStationData(section);
    weatherStationSyncTimer = window.setInterval(() => syncWeatherStationData(section), interval);
};

document.addEventListener('DOMContentLoaded', initWeatherStationSync);
document.addEventListener('livewire:navigated', initWeatherStationSync);
