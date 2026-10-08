import Chart from 'chart.js/auto';
import { initSolarScenes } from './solar-scene';
import { initStationScenes } from './station-scene';
import { initApplianceScenes } from './appliance-scene';
import { initSystemScenes } from './system-scene';

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
        uva: '#2f80ed',
        uvb: '#7b61ff',
        uvIndex: '#d9480f',
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

const destroyChart = (id) => {
    const chart = activeSolarCharts.get(id);

    if (!chart) {
        return;
    }

    chart.destroy();
    activeSolarCharts.delete(id);
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

const integerFormatter = new Intl.NumberFormat('es-CO', {
    maximumFractionDigits: 0,
});

const radiationFormatter = new Intl.NumberFormat('es-CO', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
});

const moneyFormatter = new Intl.NumberFormat('es-CO', {
    currency: 'COP',
    maximumFractionDigits: 0,
    style: 'currency',
});

const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const normalizeChartNumber = (value) => {
    if (value === null || value === undefined || value === 'N/A' || value === '') {
        return null;
    }

    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }

    const normalized = String(value).replaceAll('.', '').replace(',', '.');
    const number = Number(normalized);

    return Number.isFinite(number) ? number : null;
};

const weatherStationChartRowsToData = (rows) => ({
    labels: rows.map((row) => row.recorded_at ?? 'N/A'),
    radiation: rows.map((row) => normalizeChartNumber(row.radiation)),
    uva: rows.map((row) => normalizeChartNumber(row.uva)),
    uvb: rows.map((row) => normalizeChartNumber(row.uvb)),
    uvIndex: rows.map((row) => normalizeChartNumber(row.uv_index)),
});

const latestNumericValue = (rows, key) => {
    const values = rows
        .map((row) => normalizeChartNumber(row[key]))
        .filter((value) => value !== null);

    return values.length ? values.at(-1) : null;
};

const uvRiskLabel = (value) => {
    if (value === null) {
        return 'Sin dato';
    }

    if (value < 3) {
        return 'Bajo';
    }

    if (value < 6) {
        return 'Moderado';
    }

    if (value < 8) {
        return 'Alto';
    }

    if (value < 11) {
        return 'Muy alto';
    }

    return 'Extremo';
};

const updateUvIndexIndicator = (rows) => {
    const value = latestNumericValue(rows, 'uv_index');
    const valueElement = document.querySelector('[data-weather-station-iuv-value]');
    const riskElement = document.querySelector('[data-weather-station-iuv-risk]');
    const barElement = document.querySelector('[data-weather-station-iuv-bar]');

    if (valueElement) {
        valueElement.textContent = value === null ? 'N/A' : numberFormatter.format(value);
    }

    if (riskElement) {
        riskElement.textContent = uvRiskLabel(value);
    }

    if (barElement) {
        barElement.style.width = `${Math.min(100, ((value ?? 0) / 11) * 100)}%`;
    }
};

const updateAmbientUvIndexIndicator = (rows) => {
    const value = latestNumericValue(rows, 'uv_index');
    const valueElement = document.querySelector('[data-ambient-iuv-value]');
    const riskElement = document.querySelector('[data-ambient-iuv-risk]');
    const barElement = document.querySelector('[data-ambient-iuv-bar]');

    if (valueElement) {
        valueElement.textContent = value === null ? 'N/A' : numberFormatter.format(value);
    }

    if (riskElement) {
        riskElement.textContent = uvRiskLabel(value);
    }

    if (barElement) {
        barElement.style.width = `${Math.min(100, ((value ?? 0) / 11) * 100)}%`;
    }
};

const formatDashboardMetric = (kpi) => {
    const value = kpi?.value;

    if (value === null || value === undefined) {
        return 'Pendiente';
    }

    switch (kpi?.type) {
        case 'money':
            return moneyFormatter.format(value);
        case 'percent':
            return `${numberFormatter.format(value)}%`;
        case 'kwp':
            return `${numberFormatter.format(value)} kWp`;
        case 'kwh':
            return `${numberFormatter.format(value)} kWh`;
        default:
            return String(value);
    }
};

const insightToneClass = (level) => ({
    success: 'solar-insight-card-success',
    warning: 'solar-insight-card-warning',
    danger: 'solar-insight-card-danger',
    info: 'solar-insight-card-info',
}[level] ?? 'solar-insight-card-info');

const recommendationTypeLabel = (type) => ({
    recommendation: 'Recomendacion',
    risk: 'Riesgo',
    alert: 'Alerta',
    opportunity: 'Oportunidad',
}[type] ?? 'Accion');

const recommendationPriorityClass = (priority) => ({
    alta: 'solar-pill-danger',
    media: 'solar-pill-warn',
    baja: 'solar-pill',
}[priority] ?? 'solar-pill-warn');

const compactMoney = (value) => moneyFormatter.format(value ?? 0).replace(/\s/g, ' ');

const tableHasDaysColumn = (scale) => (scale?.table?.headers ?? []).length > 6;

const renderScaleKpis = (scale) => {
    const container = document.querySelector('[data-scale-kpis]');

    if (!container) {
        return;
    }

    container.innerHTML = (scale?.kpis ?? []).map((kpi) => `
        <div class="solar-metric-card min-w-0">
            <p class="solar-metric-label">${escapeHtml(kpi.label)}</p>
            <p class="solar-metric-value ${escapeHtml(kpi.tone ?? 'text-[color:var(--solar-text)]')}">
                ${escapeHtml(formatDashboardMetric(kpi))}
            </p>
            <p class="solar-metric-copy">${escapeHtml(kpi.description ?? '')}</p>
        </div>
    `).join('');
};

const updateExecutiveReportKpis = (scale) => {
    const kpis = scale?.kpis ?? [];
    const generationKpi = kpis.find((kpi) => String(kpi.label ?? '').toLowerCase().includes('generacion'));
    const savingsKpi = kpis.find((kpi) => String(kpi.label ?? '').toLowerCase().includes('ahorro'));
    const coverageKpi = kpis.find((kpi) => String(kpi.label ?? '').toLowerCase().includes('cobertura'));
    const risk = String(scale?.risk ?? '');
    const riskLower = risk.toLowerCase();
    const alertTone = riskLower.includes('crit')
        ? 'danger'
        : (risk ? 'warn' : 'success');
    const alertLabel = alertTone === 'danger'
        ? 'Atencion requerida'
        : (alertTone === 'warn' ? 'Revisar condiciones' : 'Sin alerta critica');
    const bindings = [
        ['[data-report-generation-label]', generationKpi?.label ?? 'Generacion'],
        ['[data-report-generation-value]', generationKpi ? formatDashboardMetric(generationKpi) : 'Pendiente'],
        ['[data-report-savings-label]', savingsKpi?.label ?? 'Ahorro'],
        ['[data-report-savings-value]', savingsKpi ? formatDashboardMetric(savingsKpi) : 'Pendiente'],
        ['[data-report-coverage-label]', coverageKpi?.label ?? 'Cobertura'],
        ['[data-report-coverage-value]', coverageKpi ? formatDashboardMetric(coverageKpi) : 'Pendiente'],
        ['[data-report-alert-label]', alertLabel],
    ];

    bindings.forEach(([selector, value]) => {
        document.querySelectorAll(selector).forEach((element) => {
            element.textContent = value;
        });
    });

    document.querySelectorAll('[data-report-alert-kpi]').forEach((element) => {
        element.classList.remove('solar-report-kpi-warn', 'solar-report-kpi-danger', 'solar-report-kpi-success');
        element.classList.add(`solar-report-kpi-${alertTone === 'warn' ? 'warn' : alertTone}`);
    });
};

const renderScaleInsights = (scale) => {
    const container = document.querySelector('[data-scale-insights]');

    if (!container) {
        return;
    }

    container.innerHTML = (scale?.insights ?? []).length
        ? (scale?.insights ?? []).map((insight) => `
        <article class="solar-insight-card ${insightToneClass(insight.level ?? 'info')}">
            <div class="solar-insight-card-head">
                <p class="solar-insight-card-title">${escapeHtml(insight.title ?? 'Insight')}</p>
                <span class="solar-pill">${escapeHtml(String(insight.level ?? 'info'))}</span>
            </div>
            <p class="solar-insight-card-copy">${escapeHtml(insight.message ?? '')}</p>
        </article>
    `).join('')
        : '<div class="solar-alert solar-alert-warning">Sin insights para la escala activa.</div>';
};

const renderScaleRecommendations = (scale) => {
    const container = document.querySelector('[data-scale-recommendations]');

    if (!container) {
        return;
    }

    const recommendations = scale?.recommendations ?? [];

    container.innerHTML = recommendations.length
        ? recommendations.map((recommendation) => `
        <article class="solar-recommendation-card">
            <div class="solar-recommendation-card-head">
                <p class="solar-recommendation-card-title">${escapeHtml(recommendationTypeLabel(recommendation.type ?? 'recomendacion'))}</p>
                <span class="solar-pill ${recommendationPriorityClass(recommendation.priority ?? 'media')}">Prioridad ${escapeHtml(recommendation.priority ?? 'media')}</span>
            </div>
            <p class="solar-recommendation-card-copy">${escapeHtml(recommendation.message ?? '')}</p>
        </article>
    `).join('')
        : '<div class="solar-alert solar-alert-warning">Sin recomendaciones para la escala activa.</div>';
};

const renderScaleTable = (scale) => {
    const head = document.querySelector('[data-scale-table-head]');
    const body = document.querySelector('[data-scale-table-body]');
    const foot = document.querySelector('[data-scale-table-foot]');
    const title = document.querySelector('[data-scale-table-title]');
    const subtitle = document.querySelector('[data-scale-table-subtitle]');

    if (title) {
        title.textContent = scale?.table?.title ?? 'Resultados del periodo';
    }

    if (subtitle) {
        subtitle.textContent = scale?.table?.subtitle ?? 'Sin detalle disponible.';
    }

    if (head) {
        head.innerHTML = `<tr>${(scale?.table?.headers ?? []).map((header) => `<th>${escapeHtml(header)}</th>`).join('')}</tr>`;
    }

    if (body) {
        const hasDays = tableHasDaysColumn(scale);

        body.innerHTML = (scale?.table?.rows ?? []).map((row) => `
            <tr>
                <td class="solar-table-fit-period">${escapeHtml(row.period ?? '')}</td>
                ${hasDays ? `<td class="solar-table-fit-num">${escapeHtml(String(row.days ?? '—'))}</td>` : ''}
                <td class="solar-table-fit-num" title="${escapeHtml(numberFormatter.format(row.radiation ?? 0))} kWh/m²/día">${escapeHtml(radiationFormatter.format(row.radiation ?? 0))}</td>
                <td class="solar-table-fit-num" title="${escapeHtml(numberFormatter.format(row.generation ?? 0))} kWh">${escapeHtml(integerFormatter.format(row.generation ?? 0))}</td>
                <td class="solar-table-fit-num" title="${escapeHtml(numberFormatter.format(row.consumption ?? 0))} kWh">${escapeHtml(integerFormatter.format(row.consumption ?? 0))}</td>
                <td class="solar-table-fit-num" title="${escapeHtml(numberFormatter.format(row.coverage ?? 0))}%">${escapeHtml(integerFormatter.format(row.coverage ?? 0))}%</td>
                <td class="solar-table-fit-num" title="${escapeHtml(moneyFormatter.format(row.savings ?? 0))}">${escapeHtml(compactMoney(row.savings ?? 0))}</td>
            </tr>
        `).join('');
    }

    if (foot) {
        const footer = scale?.table?.footer ?? {};
        const hasDays = tableHasDaysColumn(scale);
        const labelColspan = hasDays ? 3 : 2;

        foot.innerHTML = `
            <tr>
                <td class="solar-table-fit-period" colspan="${labelColspan}">${escapeHtml(footer.label ?? 'Total')}</td>
                <td class="solar-table-fit-num" title="${escapeHtml(numberFormatter.format(footer.generation ?? 0))} kWh">${escapeHtml(integerFormatter.format(footer.generation ?? 0))}</td>
                <td class="solar-table-fit-num" title="${escapeHtml(numberFormatter.format(footer.consumption ?? 0))} kWh">${escapeHtml(integerFormatter.format(footer.consumption ?? 0))}</td>
                <td class="solar-table-fit-num"></td>
                <td class="solar-table-fit-num" title="${escapeHtml(moneyFormatter.format(footer.savings ?? 0))}">${escapeHtml(compactMoney(footer.savings ?? 0))}</td>
            </tr>
        `;
    }
};

const renderScaleHighlights = (scale) => {
    document.querySelectorAll('[data-report-highlights]').forEach((container) => {
        container.innerHTML = (scale?.highlights ?? []).map((highlight) => `
            <div>
                <span>${escapeHtml(highlight.label ?? '')}</span>
                <strong>${escapeHtml(highlight.value ?? '')}</strong>
            </div>
        `).join('');
    });

    document.querySelectorAll('[data-scale-highlights]').forEach((container) => {
        container.innerHTML = (scale?.highlights ?? []).map((highlight) => `
            <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                <dt class="text-zinc-500 dark:text-zinc-400">${escapeHtml(highlight.label ?? '')}</dt>
                <dd class="mt-1 font-semibold text-zinc-950 dark:text-zinc-50">${escapeHtml(highlight.value ?? '')}</dd>
            </div>
        `).join('');
    });
};

const renderSolarEnergyCharts = (chartData) => {
    const labels = chartData?.labels ?? [];

    ['solar-executive-chart', 'solar-generation-chart', 'solar-consumption-generation-chart', 'solar-savings-chart', 'solar-coverage-chart'].forEach(destroyChart);

    if (!labels.length) {
        return;
    }

    const solarColors = getSolarChartColors();

    createChart('solar-executive-chart', {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Generacion kWh',
                    data: chartData.generation ?? [],
                    yAxisID: 'energy',
                    backgroundColor: solarColors.gold,
                    borderColor: solarColors.goldDark,
                    borderWidth: 1,
                    borderRadius: 8,
                    order: 2,
                },
                {
                    label: 'Consumo kWh',
                    data: chartData.consumption ?? [],
                    yAxisID: 'energy',
                    backgroundColor: `${solarColors.clay}CC`,
                    borderColor: solarColors.clay,
                    borderWidth: 1,
                    borderRadius: 8,
                    order: 3,
                },
                {
                    label: 'Ahorro COP',
                    data: chartData.savings ?? [],
                    yAxisID: 'money',
                    type: 'line',
                    backgroundColor: `${solarColors.success}26`,
                    borderColor: solarColors.success,
                    borderWidth: 3,
                    fill: true,
                    pointBackgroundColor: solarColors.pointSurface,
                    pointBorderColor: solarColors.success,
                    pointBorderWidth: 2,
                    pointRadius: 3,
                    tension: 0.35,
                    order: 1,
                },
            ],
        },
        options: {
            ...baseOptions('kWh'),
            interaction: {
                intersect: false,
                mode: 'index',
            },
            plugins: {
                ...baseOptions('kWh').plugins,
                tooltip: {
                    ...baseOptions('kWh').plugins.tooltip,
                    callbacks: {
                        label: (context) => {
                            if (context.dataset.yAxisID === 'money') {
                                return `${context.dataset.label}: ${moneyFormatter.format(context.parsed.y)}`;
                            }

                            return `${context.dataset.label}: ${numberFormatter.format(context.parsed.y)} kWh`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    ticks: {
                        color: solarColors.text,
                        maxRotation: 0,
                        autoSkip: true,
                    },
                    grid: {
                        color: solarColors.grid,
                    },
                },
                energy: {
                    beginAtZero: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'kWh',
                        color: solarColors.text,
                    },
                    ticks: {
                        color: solarColors.text,
                    },
                    grid: {
                        color: solarColors.grid,
                    },
                },
                money: {
                    beginAtZero: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'COP',
                        color: solarColors.text,
                    },
                    ticks: {
                        color: solarColors.text,
                        callback: (value) => moneyFormatter.format(value).replace(',00', ''),
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                },
            },
        },
    });

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
                    label: 'Generacion kWh',
                    data: chartData.generation ?? [],
                    backgroundColor: solarColors.success,
                    borderColor: solarColors.successDark,
                    borderWidth: 1,
                    borderRadius: 10,
                },
                {
                    label: 'Consumo kWh',
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
                pointBackgroundColor: solarColors.pointSurface,
                pointBorderColor: solarColors.goldDark,
                pointBorderWidth: 2,
                pointRadius: 3,
                pointHoverRadius: 5,
            }],
        },
        options: baseOptions('%', (context) => `${context.dataset.label}: ${numberFormatter.format(context.parsed.y)}%`),
    });
};

const applyDashboardScale = (scaleKey, payload) => {
    const scale = payload?.scales?.[scaleKey];

    if (!scale) {
        return;
    }

    document.querySelectorAll('[data-scale-button]').forEach((button) => {
        button.classList.toggle('is-active', button.dataset.scaleButton === scaleKey);
    });

    const textBindings = [
        ['[data-scale-summary]', scale.summary],
        ['[data-scale-state-title]', scale.stateTitle],
        ['[data-scale-range-label]', scale.rangeLabel],
        ['[data-scale-risk]', scale.risk],
        ['[data-scale-primary-recommendation]', scale.primaryRecommendation],
        ['[data-scale-chart-range]', scale.chart?.rangeLabel],
        ['[data-scale-chart-generation-title]', scale.chart?.generationTitle],
        ['[data-scale-chart-comparison-title]', scale.chart?.comparisonTitle],
        ['[data-scale-chart-savings-title]', scale.chart?.savingsTitle],
        ['[data-scale-chart-coverage-title]', scale.chart?.coverageTitle],
    ];

    textBindings.forEach(([selector, value]) => {
        document.querySelectorAll(selector).forEach((element) => {
            if (!value) {
                return;
            }

            element.textContent = value;
        });
    });

    const stateTitle = document.querySelector('[data-scale-state-title]');

    if (stateTitle) {
        stateTitle.className = `mt-2 text-lg font-semibold ${scale.stateTone ?? 'text-zinc-500 dark:text-zinc-400'}`;
    }

    renderScaleKpis(scale);
    updateExecutiveReportKpis(scale);
    renderScaleInsights(scale);
    renderScaleRecommendations(scale);
    renderScaleTable(scale);
    renderScaleHighlights(scale);
    renderSolarEnergyCharts(scale.chart);
};

const upsertWeatherStationRealtimeChart = (rows) => {
    const canvas = document.getElementById('weather-station-realtime-chart');

    if (!canvas) {
        return;
    }

    const solarColors = getSolarChartColors();
    const chartData = weatherStationChartRowsToData(rows);
    const existingChart = activeSolarCharts.get('weather-station-realtime-chart');

    if (existingChart) {
        existingChart.data.labels = chartData.labels;
        existingChart.data.datasets[0].data = chartData.radiation;
        existingChart.data.datasets[1].data = chartData.uva;
        existingChart.data.datasets[2].data = chartData.uvb;
        existingChart.data.datasets[3].data = chartData.uvIndex;
        existingChart.update('none');
        updateUvIndexIndicator(rows);

        return;
    }

    createChart('weather-station-realtime-chart', {
        type: 'line',
        data: {
            labels: chartData.labels,
            datasets: [
                {
                    label: 'Radiacion',
                    data: chartData.radiation,
                    yAxisID: 'radiation',
                    backgroundColor: `${solarColors.gold}24`,
                    borderColor: solarColors.gold,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: solarColors.pointSurface,
                    pointBorderColor: solarColors.goldDark,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    spanGaps: true,
                },
                {
                    label: 'UVA',
                    data: chartData.uva,
                    yAxisID: 'uv',
                    borderColor: solarColors.uva,
                    backgroundColor: `${solarColors.uva}22`,
                    borderWidth: 2,
                    tension: 0.35,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    spanGaps: true,
                },
                {
                    label: 'UVB',
                    data: chartData.uvb,
                    yAxisID: 'uv',
                    borderColor: solarColors.uvb,
                    backgroundColor: `${solarColors.uvb}22`,
                    borderWidth: 2,
                    tension: 0.35,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    spanGaps: true,
                },
                {
                    label: 'IUV',
                    data: chartData.uvIndex,
                    yAxisID: 'uv',
                    borderColor: solarColors.uvIndex,
                    backgroundColor: `${solarColors.uvIndex}22`,
                    borderWidth: 2,
                    tension: 0.35,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    spanGaps: true,
                },
            ],
        },
        options: {
            ...baseOptions('Radiacion', (context) => `${context.dataset.label}: ${numberFormatter.format(context.parsed.y)}`),
            interaction: {
                intersect: false,
                mode: 'index',
            },
            scales: {
                x: {
                    ticks: {
                        color: solarColors.text,
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 8,
                    },
                    grid: {
                        color: solarColors.grid,
                    },
                },
                radiation: {
                    beginAtZero: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Radiacion',
                        color: solarColors.text,
                    },
                    ticks: {
                        color: solarColors.text,
                    },
                    grid: {
                        color: solarColors.grid,
                    },
                },
                uv: {
                    beginAtZero: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'UVA / UVB / IUV',
                        color: solarColors.text,
                    },
                    ticks: {
                        color: solarColors.text,
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                },
            },
        },
    });

    updateUvIndexIndicator(rows);
};

const upsertAmbientRealtimeChart = (rows) => {
    const canvas = document.getElementById('ambient-realtime-chart');

    if (!canvas) {
        return;
    }

    const solarColors = getSolarChartColors();
    const labels     = rows.map((row) => row.recorded_at ?? 'N/A');
    const radiation  = rows.map((row) => normalizeChartNumber(row.radiation));
    const temperature = rows.map((row) => normalizeChartNumber(row.temperature));
    const uvIndex    = rows.map((row) => normalizeChartNumber(row.uv_index));

    const existingChart = activeSolarCharts.get('ambient-realtime-chart');

    if (existingChart) {
        existingChart.data.labels              = labels;
        existingChart.data.datasets[0].data    = radiation;
        existingChart.data.datasets[1].data    = temperature;
        existingChart.data.datasets[2].data    = uvIndex;
        existingChart.update('none');
        updateAmbientUvIndexIndicator(rows);
        return;
    }

    createChart('ambient-realtime-chart', {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Radiacion solar (W/m²)',
                    data: radiation,
                    yAxisID: 'radiation',
                    backgroundColor: `${solarColors.gold}24`,
                    borderColor: solarColors.gold,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: solarColors.pointSurface,
                    pointBorderColor: solarColors.goldDark,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    spanGaps: true,
                },
                {
                    label: 'Temperatura (°C)',
                    data: temperature,
                    yAxisID: 'temp',
                    borderColor: solarColors.danger,
                    backgroundColor: `${solarColors.danger}18`,
                    borderWidth: 2,
                    tension: 0.35,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    spanGaps: true,
                },
                {
                    label: 'IUV',
                    data: uvIndex,
                    yAxisID: 'temp',
                    borderColor: solarColors.uvIndex,
                    backgroundColor: `${solarColors.uvIndex}18`,
                    borderWidth: 2,
                    tension: 0.35,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    spanGaps: true,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: {
                    labels: { color: solarColors.text, usePointStyle: true, pointStyle: 'circle', padding: 18 },
                },
                tooltip: {
                    backgroundColor: solarColors.tooltipBg,
                    titleColor: solarColors.tooltipTitle,
                    bodyColor: solarColors.tooltipBody,
                    borderColor: solarColors.tooltipBorder,
                    borderWidth: 1,
                    padding: 12,
                    callbacks: {
                        label: (context) => {
                            if (context.dataset.yAxisID === 'radiation') {
                                return `${context.dataset.label}: ${numberFormatter.format(context.parsed.y)} W/m²`;
                            }
                            return `${context.dataset.label}: ${numberFormatter.format(context.parsed.y)}`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    ticks: { color: solarColors.text, maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
                    grid: { color: solarColors.grid },
                },
                radiation: {
                    beginAtZero: true,
                    position: 'left',
                    title: { display: true, text: 'Radiacion (W/m²)', color: solarColors.text },
                    ticks: { color: solarColors.text },
                    grid: { color: solarColors.grid },
                },
                temp: {
                    beginAtZero: false,
                    position: 'right',
                    title: { display: true, text: '°C / IUV', color: solarColors.text },
                    ticks: { color: solarColors.text },
                    grid: { drawOnChartArea: false },
                },
            },
        },
    });

    updateAmbientUvIndexIndicator(rows);
};

// NASA POWER daily radiation (ADR-0009): solid bars are real NASA data, light bars are provisional estimates.
const renderNasaDailyChart = () => {
    const dataElement = document.getElementById('nasa-daily-chart-data');

    if (!dataElement) {
        return;
    }

    destroyChart('nasa-daily-chart');

    const rows = JSON.parse(dataElement.textContent || '[]');
    const solarColors = getSolarChartColors();
    const realOrNull = (real) => rows.map((row) => (row.real === real ? normalizeChartNumber(row.radiation) : null));

    createChart('nasa-daily-chart', {
        type: 'bar',
        data: {
            labels: rows.map((row) => row.date),
            datasets: [
                {
                    label: 'Dato real NASA',
                    data: realOrNull(true),
                    backgroundColor: `${solarColors.gold}cc`,
                    borderColor: solarColors.goldDark,
                    borderWidth: 1,
                    borderRadius: 3,
                    stack: 'radiation',
                },
                {
                    label: 'Estimado (pendiente de publicar)',
                    data: realOrNull(false),
                    backgroundColor: `${solarColors.gold}40`,
                    borderColor: solarColors.gold,
                    borderWidth: 1,
                    borderDash: [4, 3],
                    borderRadius: 3,
                    stack: 'radiation',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: {
                    labels: { color: solarColors.text, usePointStyle: true, pointStyle: 'rectRounded', padding: 18 },
                },
                tooltip: {
                    backgroundColor: solarColors.tooltipBg,
                    titleColor: solarColors.tooltipTitle,
                    bodyColor: solarColors.tooltipBody,
                    borderColor: solarColors.tooltipBorder,
                    borderWidth: 1,
                    padding: 12,
                    filter: (item) => item.parsed.y !== null,
                    callbacks: {
                        label: (context) => {
                            const row = rows[context.dataIndex];
                            return `${context.dataset.label}: ${numberFormatter.format(context.parsed.y)} W/m² · ${numberFormatter.format(row.peak_sun_hours ?? 0)} HSP`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    stacked: true,
                    ticks: { color: solarColors.text, maxRotation: 0, autoSkip: true, maxTicksLimit: 10 },
                    grid: { display: false },
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    title: { display: true, text: 'Radiación promedio diaria (W/m²)', color: solarColors.text },
                    ticks: { color: solarColors.text },
                    grid: { color: solarColors.grid },
                },
            },
        },
    });
};

const initSolarCharts = () => {
    const timeScaleDataElement = document.getElementById('solar-timescale-chart-data');
    const weatherStationDataElement = document.getElementById('weather-station-chart-data');
    const weatherStationRealtimeDataElement = document.getElementById('weather-station-realtime-chart-data');
    const ambientRealtimeDataElement = document.getElementById('ambient-realtime-chart-data');

    destroySolarCharts();

    if (timeScaleDataElement) {
        const payload = JSON.parse(timeScaleDataElement.textContent);
        const defaultScale = payload.defaultScale ?? 'monthly';

        document.querySelectorAll('[data-scale-button]').forEach((button) => {
            if (button.dataset.scaleBound === 'true') {
                return;
            }

            button.addEventListener('click', () => applyDashboardScale(button.dataset.scaleButton, payload));
            button.dataset.scaleBound = 'true';
        });

        applyDashboardScale(defaultScale, payload);
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

    if (weatherStationRealtimeDataElement) {
        upsertWeatherStationRealtimeChart(JSON.parse(weatherStationRealtimeDataElement.textContent || '[]'));
    }

    if (ambientRealtimeDataElement) {
        upsertAmbientRealtimeChart(JSON.parse(ambientRealtimeDataElement.textContent || '[]'));
    }

    renderNasaDailyChart();
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

let apiDataSyncTimer = null;
let apiDataSyncController = null;
let apiPaginationController = null;
let apiDataManualSyncActive = false;

const apiSourceConfig = {
    ambient: {
        countKey: 'ambientCount',
        countSelector: '[data-ambient-count]',
        emptyColspan: 9,
        emptyMessage: 'Aun no hay lecturas registradas desde Ambient Weather. Pulsa "Sincronizar Ambient Weather" para importar.',
        formSelector: '[data-api-fetch-form="ambient"]',
        pillSelector: '[data-ambient-count-pill]',
        renderRows: (rows) => rows.map((row) => `
            <tr>
                <td class="font-semibold text-[color:var(--solar-text)]">${escapeHtml(row.recorded_at)}</td>
                <td class="font-mono text-xs">${escapeHtml(row.mac_address)}</td>
                <td>${escapeHtml(row.radiation)} <span class="text-xs text-[color:var(--solar-text-muted)]">W/m²</span></td>
                <td>${escapeHtml(row.temperature)} <span class="text-xs text-[color:var(--solar-text-muted)]">°C</span></td>
                <td>${escapeHtml(row.humidity)} <span class="text-xs text-[color:var(--solar-text-muted)]">%</span></td>
                <td>${escapeHtml(row.wind_speed)}</td>
                <td>${escapeHtml(row.wind_direction)}</td>
                <td>${escapeHtml(row.rainfall)}</td>
                <td>${escapeHtml(row.uv_index)}</td>
            </tr>
        `).join(''),
        rowsSelector: '[data-ambient-rows]',
        statusBusy: 'Sincronizando Ambient Weather…',
        statusAuto: 'Buscando nuevas lecturas de Ambient…',
        updateChart: upsertAmbientRealtimeChart,
    },
    'weather-station': {
        countKey: 'weatherStationCount',
        countSelector: '[data-weather-station-count]',
        emptyColspan: 12,
        emptyMessage: 'Aun no hay lecturas registradas desde el centro meteorologico.',
        formSelector: '[data-api-fetch-form="weather-station"]',
        pillSelector: '[data-weather-station-count-pill]',
        renderRows: (rows) => renderWeatherStationRows(rows),
        rowsSelector: '[data-weather-station-rows]',
        statusBusy: 'Consultando estación…',
        statusAuto: 'Buscando nuevas lecturas meteorológicas…',
        updateChart: upsertWeatherStationRealtimeChart,
    },
    nasa: {
        autoSync: false,
        countKey: 'nasaCount',
        countSelector: '[data-api-data-nasa-count]',
        emptyColspan: 8,
        emptyMessage: 'Aun no hay datos registrados desde NASA POWER.',
        formSelector: '[data-api-fetch-form="nasa"]',
        pillSelector: '[data-api-data-nasa-count-pill]',
        renderRows: (rows) => renderNasaRows(rows),
        rowsSelector: '[data-nasa-rows]',
        statusBusy: 'Consultando NASA POWER…',
        statusAuto: 'NASA POWER se actualiza manualmente.',
    },
};

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

const renderNasaRows = (rows) => {
    if (!rows.length) {
        return `
            <tr>
                <td colspan="8" class="py-10 text-center">
                    Aun no hay datos registrados desde NASA POWER.
                </td>
            </tr>
        `;
    }

    return rows.map((row) => `
        <tr>
            <td class="font-semibold text-[color:var(--solar-text)]">${escapeHtml(row.recorded_at)}</td>
            <td>
                <span class="solar-pill ${row.is_incomplete ? 'solar-pill-warn' : ''}">
                    ${escapeHtml(row.status)}
                </span>
            </td>
            <td>${escapeHtml(row.radiation)}</td>
            <td>
                ${escapeHtml(row.radiation_source)}
                <span class="text-xs text-[color:var(--solar-text-muted)]">
                    (${escapeHtml(row.radiation_confidence)})
                </span>
            </td>
            <td>${escapeHtml(row.temperature)}</td>
            <td>${escapeHtml(row.humidity)}</td>
            <td>${escapeHtml(row.precipitation)}</td>
            <td>${escapeHtml(row.wind_speed)}</td>
        </tr>
    `).join('');
};

const renderEmptyApiRows = (config) => `
    <tr>
        <td colspan="${config.emptyColspan}" class="py-10 text-center">
            ${escapeHtml(config.emptyMessage)}
        </td>
    </tr>
`;

const setApiDataStatus = (section, source, message, tone = 'neutral', { busy = false } = {}) => {
    const status = section.querySelector(`[data-api-sync-status="${source}"]`);

    if (!status) {
        return;
    }

    // While waiting: a small spinner and the seconds it has taken so far (filled by the sync timer).
    status.replaceChildren();
    if (busy) {
        const spinner = document.createElement('span');
        spinner.className = 'solar-sync-spinner';
        spinner.setAttribute('aria-hidden', 'true');
        const elapsed = document.createElement('span');
        elapsed.className = 'solar-sync-elapsed';
        elapsed.dataset.apiSyncElapsed = '';
        status.append(spinner, `${message} `, elapsed);
    } else {
        status.append(message);
    }
    status.toggleAttribute('aria-busy', busy);
    status.classList.toggle('text-red-600', tone === 'error');
    status.classList.toggle('dark:text-red-300', tone === 'error');
    status.classList.toggle('text-zinc-500', tone !== 'error');
    status.classList.toggle('dark:text-zinc-400', tone !== 'error');
};

const updateApiDataTotal = () => {
    const totalCountElements = document.querySelectorAll('[data-api-data-total-count]');
    const ambientCountElement = document.querySelector('[data-ambient-count]');
    const stationCountElement = document.querySelector('[data-weather-station-count]');
    const nasaCountElement = document.querySelector('[data-api-data-nasa-count]');
    const totalCount = Number(ambientCountElement?.dataset.count ?? 0)
        + Number(stationCountElement?.dataset.count ?? 0)
        + Number(nasaCountElement?.dataset.count ?? 0);

    totalCountElements.forEach((element) => {
        element.textContent = apiDataCountFormatter.format(totalCount);
    });
};

const updateApiSourceDom = (section, source, payload) => {
    const config = apiSourceConfig[source];

    if (!config) {
        return;
    }

    const sourceCount = Number(payload[config.countKey] ?? 0);
    const formattedSourceCount = apiDataCountFormatter.format(sourceCount);
    const countElements = document.querySelectorAll(config.countSelector);
    const countPill = section.querySelector(config.pillSelector);

    countElements.forEach((element) => {
        element.textContent = formattedSourceCount;
        element.dataset.count = String(sourceCount);
    });

    if (countPill) {
        countPill.textContent = `${formattedSourceCount} registros`;
    }

    updateApiDataTotal();

    const sourceRowsElement = section.querySelector(config.rowsSelector);

    if (sourceRowsElement) {
        const rows = payload.rows ?? [];
        sourceRowsElement.innerHTML = rows.length ? config.renderRows(rows) : renderEmptyApiRows(config);
    }

    if (payload.chartRows) {
        config.updateChart(payload.chartRows);
    }

    if (payload.wind) {
        updateStationWind(payload.wind);
    }
};

// The vane of the Ambient Weather station follows the latest reading (ADR-0018).
const updateStationWind = ({ speedKmh, directionDegrees, text }) => {
    const figure = document.querySelector('[data-station-scene]');

    if (!figure) {
        return;
    }

    const hasDirection = directionDegrees !== null && directionDegrees !== undefined;
    figure.dataset.windSpeed = speedKmh ?? '';
    figure.dataset.windDirection = hasDirection ? directionDegrees : '';

    const label = figure.querySelector('[data-station-wind-text]');
    if (label && text) {
        label.textContent = text;
    }

    const compass = figure.querySelector('[data-station-compass]');
    compass?.toggleAttribute('data-no-direction', !hasDirection);
    if (compass && hasDirection) {
        compass.style.setProperty('--wind-direction', `${directionDegrees}deg`);
    }
};

const syncApiDataSource = async (source, { manual = false } = {}) => {
    const config = apiSourceConfig[source];
    const section = document.querySelector(`[data-api-sync-section="${source}"]`);
    const form = section?.querySelector(config?.formSelector);

    if (!config || !section || !form || apiDataSyncController) {
        return;
    }

    if (!manual && (config.autoSync === false || document.visibilityState !== 'visible' || apiDataManualSyncActive)) {
        return;
    }

    apiDataSyncController = new AbortController();
    apiDataManualSyncActive = manual;
    setApiDataStatus(section, source, manual ? config.statusBusy : config.statusAuto, 'neutral', { busy: true });

    // The button waits too, and the status counts the seconds.
    const button = form.querySelector('button[type="submit"]');
    const startedAt = performance.now();
    const seconds = () => Math.round((performance.now() - startedAt) / 1000);
    const showElapsed = () => {
        const elapsed = section.querySelector('[data-api-sync-elapsed]');
        if (elapsed) {
            elapsed.textContent = `${seconds()} s`;
        }
    };
    showElapsed();
    const elapsedTimer = window.setInterval(showElapsed, 1000);
    if (button) {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
    }

    const formData = new FormData(form);

    if (!manual) {
        formData.set('auto_sync', '1');
    }

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: formData,
            credentials: 'same-origin',
            signal: apiDataSyncController.signal,
        });

        const payload = await response.json();

        if (!response.ok) {
            throw new Error(payload.message ?? 'No fue posible actualizar los datos.');
        }

        updateApiSourceDom(section, source, payload);
        setApiDataStatus(section, source, `${payload.message ?? 'Datos actualizados.'} (tardó ${seconds()} s)`);
    } catch (error) {
        if (error.name !== 'AbortError') {
            setApiDataStatus(section, source, `${error.message} (después de ${seconds()} s)`, 'error');
        }
    } finally {
        window.clearInterval(elapsedTimer);
        if (button) {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
        apiDataSyncController = null;
        apiDataManualSyncActive = false;
    }
};

const runApiDataAutoSync = async () => {
    for (const source of Object.keys(apiSourceConfig)) {
        if (apiDataManualSyncActive || document.visibilityState !== 'visible') {
            return;
        }

        await syncApiDataSource(source);
    }
};

const initApiDataSync = () => {
    const page = document.querySelector('[data-api-auto-sync]');

    if (apiDataSyncTimer) {
        clearInterval(apiDataSyncTimer);
        apiDataSyncTimer = null;
    }

    if (apiDataSyncController) {
        apiDataSyncController.abort();
        apiDataSyncController = null;
    }

    if (!page) {
        return;
    }

    document.querySelectorAll('[data-api-fetch-form]').forEach((form) => {
        const source = form.dataset.apiFetchForm;

        if (!source || form.dataset.apiSyncSubmitBound) {
            return;
        }

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            syncApiDataSource(source, { manual: true });
        });
        form.dataset.apiSyncSubmitBound = 'true';
    });

    const interval = Number(page.dataset.apiSyncInterval ?? 300000);
    apiDataSyncTimer = window.setInterval(runApiDataAutoSync, interval);
};

document.addEventListener('DOMContentLoaded', initApiDataSync);
document.addEventListener('livewire:navigated', initApiDataSync);

const apiDataPagePath = '/api-data';

const sectionKeyFromUrl = (url) => {
    const parsedUrl = new URL(url, window.location.href);

    if (parsedUrl.searchParams.has('ambient_page')) {
        return 'ambient';
    }

    if (parsedUrl.searchParams.has('station_page')) {
        return 'weather-station';
    }

    if (parsedUrl.searchParams.has('nasa_page')) {
        return 'nasa';
    }

    return null;
};

const replaceApiPaginationSection = (html, sectionKey) => {
    const documentFragment = new DOMParser().parseFromString(html, 'text/html');
    const currentSection = document.querySelector(`[data-api-pagination-section="${sectionKey}"]`);
    const nextSection = documentFragment.querySelector(`[data-api-pagination-section="${sectionKey}"]`);

    if (!currentSection || !nextSection) {
        return false;
    }

    currentSection.replaceWith(nextSection);
    initApiDataPagination();
    initApiDataSync();
    showApiDataTab(sectionKey, { updateUrl: false });

    // The NASA section carries its own chart: redraw it on the new canvas.
    if (sectionKey === 'nasa') {
        renderNasaDailyChart();
    }

    return true;
};

const loadApiPaginationPage = async (url, { pushState = true } = {}) => {
    const sectionKey = sectionKeyFromUrl(url);

    if (!sectionKey) {
        window.location.href = url;
        return;
    }

    if (apiPaginationController) {
        apiPaginationController.abort();
    }

    const section = document.querySelector(`[data-api-pagination-section="${sectionKey}"]`);
    apiPaginationController = new AbortController();
    section?.classList.add('opacity-60', 'pointer-events-none');

    try {
        const response = await fetch(url, {
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: apiPaginationController.signal,
        });

        if (!response.ok) {
            throw new Error('No fue posible cargar la pagina solicitada.');
        }

        const replaced = replaceApiPaginationSection(await response.text(), sectionKey);

        if (!replaced) {
            window.location.href = url;
            return;
        }

        if (pushState) {
            window.history.pushState({ apiDataPagination: true }, '', url);
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            window.location.href = url;
        }
    } finally {
        section?.classList.remove('opacity-60', 'pointer-events-none');
        apiPaginationController = null;
    }
};

const initApiDataPagination = () => {
    document.querySelectorAll('[data-api-pagination-links] a[href]').forEach((link) => {
        if (link.dataset.apiPaginationBound) {
            return;
        }

        link.addEventListener('click', (event) => {
            const url = new URL(link.href);

            if (url.pathname !== apiDataPagePath) {
                return;
            }

            event.preventDefault();
            loadApiPaginationPage(link.href);
        });

        link.dataset.apiPaginationBound = 'true';
    });
};

window.addEventListener('popstate', () => {
    if (window.location.pathname === apiDataPagePath) {
        const sectionKey = sectionKeyFromUrl(window.location.href);

        if (sectionKey) {
            loadApiPaginationPage(window.location.href, { pushState: false });
        }
    }
});

document.addEventListener('DOMContentLoaded', initApiDataPagination);
document.addEventListener('livewire:navigated', initApiDataPagination);

// One tab per climate source (ADR-0008). Tabs are real links (?tab=…); this only avoids the reload.
function showApiDataTab(key, { updateUrl = true, focus = false } = {}) {
    const tabs = Array.from(document.querySelectorAll('[data-api-tab]'));
    const target = tabs.find((tab) => tab.dataset.apiTab === key);

    if (!target) {
        return;
    }

    tabs.forEach((tab) => {
        const selected = tab === target;
        tab.setAttribute('aria-selected', String(selected));
        tab.tabIndex = selected ? 0 : -1;
    });

    document.querySelectorAll('[data-api-tab-panel]').forEach((panel) => {
        panel.hidden = panel.dataset.apiTabPanel !== key;
    });

    // The hero shows the station of this source (ADR-0018): its caption by CSS, its 3D model by the scene.
    document.querySelector('[data-station-scene]')?.setAttribute('data-station', key);

    // Charts drawn while their panel was hidden have no size yet.
    activeSolarCharts.forEach((chart) => chart.resize());

    if (focus) {
        target.focus();
    }

    if (updateUrl) {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', key);
        window.history.replaceState(window.history.state, '', url);
    }
}

const initApiDataTabs = () => {
    const tablist = document.querySelector('[data-api-tabs]');

    if (!tablist || tablist.dataset.apiTabsBound) {
        return;
    }

    const tabs = () => Array.from(tablist.querySelectorAll('[data-api-tab]'));

    tablist.addEventListener('click', (event) => {
        const tab = event.target.closest('[data-api-tab]');

        if (!tab || event.metaKey || event.ctrlKey || event.shiftKey) {
            return;
        }

        event.preventDefault();
        showApiDataTab(tab.dataset.apiTab);
    });

    tablist.addEventListener('keydown', (event) => {
        const list = tabs();
        const index = list.indexOf(document.activeElement);

        if (index === -1) {
            return;
        }

        const next = {
            ArrowRight: (index + 1) % list.length,
            ArrowLeft: (index - 1 + list.length) % list.length,
            Home: 0,
            End: list.length - 1,
        }[event.key];

        if (next === undefined) {
            return;
        }

        event.preventDefault();
        showApiDataTab(list[next].dataset.apiTab, { focus: true });
    });

    tablist.dataset.apiTabsBound = 'true';
};

document.addEventListener('DOMContentLoaded', initApiDataTabs);
document.addEventListener('livewire:navigated', initApiDataTabs);

// Sidebar: animate the collapse only when the user toggles it (see .solar-shell-sidebar in app.css).
// Flux changes the sidebar in its own listener for this same event, before the next frame, so the
// transition applies to that change and never to the state restored on each page load.
let sidebarAnimationTimer = null;

document.addEventListener('flux-sidebar-toggle', () => {
    const sidebar = document.querySelector('ui-sidebar');

    if (!sidebar) {
        return;
    }

    sidebar.setAttribute('data-sidebar-animating', '');
    window.clearTimeout(sidebarAnimationTimer);
    sidebarAnimationTimer = window.setTimeout(() => sidebar.removeAttribute('data-sidebar-animating'), 400);
});

// Flash notifications: a success message shows as a Flux toast for a moment instead of an inline alert.
// window.solarToast(text) is also used by screens that save without reloading (e.g. the consumption diary).
const FLASH_TOAST_DURATION_MS = 3500;

window.solarToast = (text, variant = 'success') => {
    if (!text || !window.Flux?.toast) {
        return false;
    }

    window.Flux.toast({ text, variant, duration: FLASH_TOAST_DURATION_MS });

    return true;
};

// The layout leaves the session flash in [data-flash-toast]; show it once Alpine is listening.
const showFlashToasts = () => {
    document.querySelectorAll('[data-flash-toast]').forEach((flash) => {
        if (window.solarToast(flash.textContent.trim(), flash.dataset.variant || 'success')) {
            flash.remove();
        }
    });
};

document.addEventListener('livewire:navigated', showFlashToasts);
document.addEventListener('alpine:initialized', () => window.setTimeout(showFlashToasts));

// "Ver en kWh | Pesos" (resources/views/solar-projects/partials/unit-toolbar.blade.php): figures are drawn
// in both units and [data-unit-root] shows one. The choice is shared by every screen and remembered here.
const UNIT_STORAGE_KEY = 'natalia:unit';

const initUnitSwitches = () => {
    document.querySelectorAll('[data-unit-root]').forEach((root) => {
        if (root.dataset.unitReady) {
            return;
        }
        root.dataset.unitReady = '1';

        const buttons = Array.from(root.querySelectorAll('[data-unit-choice]'));
        const rate = Number(root.dataset.rate) || 0;

        const apply = (unit, { remember }) => {
            root.dataset.unit = unit === 'money' && rate > 0 ? 'money' : 'kwh';
            buttons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.unitChoice === root.dataset.unit)));

            if (remember) {
                try {
                    localStorage.setItem(UNIT_STORAGE_KEY, root.dataset.unit);
                } catch (_error) {
                    // Storage unavailable: the choice lasts until the page changes.
                }
            }

            root.dispatchEvent(new CustomEvent('unit:changed', { detail: { unit: root.dataset.unit } }));
        };

        buttons.forEach((button) => button.addEventListener('click', () => apply(button.dataset.unitChoice, { remember: true })));
        apply(root.dataset.unit, { remember: false });
    });
};

document.addEventListener('DOMContentLoaded', initUnitSwitches);
document.addEventListener('livewire:navigated', initUnitSwitches);

// Portfolio: search while typing. The server renders the results (same page, same query) and only
// [data-portfolio-results] is replaced; the URL keeps the search for reloads and the back button.
const PORTFOLIO_SEARCH_DELAY_MS = 300;
// Not a data-* flag: Livewire's back-button copy of the page would keep it without the listeners.
const portfolioSearchForms = new WeakSet();

const initPortfolioSearch = () => {
    const form = document.querySelector('[data-portfolio-search]');

    if (!form || portfolioSearchForms.has(form)) {
        return;
    }
    portfolioSearchForms.add(form);

    const input = form.querySelector('input[name="search"]');
    const clear = form.querySelector('[data-portfolio-clear]');
    let timer = null;
    let request = null;
    let lastTerm = input.value.trim();

    const search = async () => {
        const term = input.value.trim();

        if (term === lastTerm) {
            return;
        }
        lastTerm = term;

        const url = new URL(form.action);
        if (term !== '') {
            url.searchParams.set('search', term);
        }

        request?.abort();
        const current = new AbortController();
        request = current;
        const results = document.querySelector('[data-portfolio-results]');
        results?.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, { signal: current.signal, headers: { Accept: 'text/html' } });
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = page.querySelector('[data-portfolio-results]');

            if (!response.ok || !next || !results) {
                throw new Error(`Portfolio search failed (${response.status}).`);
            }

            results.replaceWith(next);
            clear.hidden = term === '';
            window.history.replaceState(window.history.state, '', url);
        } catch (error) {
            if (error.name !== 'AbortError') {
                form.submit(); // The classic search still works.
            }
        } finally {
            if (request === current) {
                results?.removeAttribute('aria-busy');
            }
        }
    };

    input.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(search, PORTFOLIO_SEARCH_DELAY_MS);
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        window.clearTimeout(timer);
        search();
    });

    clear?.addEventListener('click', (event) => {
        event.preventDefault();
        input.value = '';
        input.focus();
        window.clearTimeout(timer);
        search();
    });
};

document.addEventListener('DOMContentLoaded', initPortfolioSearch);
document.addEventListener('livewire:navigated', initPortfolioSearch);

// Portfolio as a table (partials/portfolio-table.blade.php): a native <dialog>, so Esc and focus come
// for free. It lives inside the search results, so it is looked up on each click.
document.addEventListener('click', (event) => {
    const table = document.querySelector('[data-portfolio-table]');

    if (!table) {
        return;
    }

    if (event.target.closest('[data-portfolio-table-open]')) {
        table.showModal();
    } else if (event.target.closest('[data-portfolio-table-close]') || event.target === table) {
        // A click on the dialog itself (not its content) is a click on the backdrop.
        table.close();
    }
});

// Filters that apply on change (e.g. the appliance catalog's order and "Para"): a plain GET form.
document.addEventListener('change', (event) => {
    const form = event.target.closest('form[data-autosubmit]');

    if (form) {
        form.requestSubmit();
    }
});

// Appliance catalog form (appliance-catalog/form.blade.php, ADR-0017): option rows, and the hours
// field follows the kind of use (per day, per week, or none when always on).
const catalogForms = new WeakSet();

const initCatalogForm = () => {
    const form = document.querySelector('[data-catalog-form]');

    if (!form || catalogForms.has(form)) {
        return;
    }
    catalogForms.add(form);

    const body = form.querySelector('[data-catalog-options]');
    const template = form.querySelector('[data-catalog-option-template]');
    const usage = form.querySelector('[data-catalog-usage]');
    const hours = form.querySelector('[data-catalog-hours]');
    const hoursLabel = form.querySelector('[data-catalog-hours-label]');
    let nextIndex = body.querySelectorAll('[data-catalog-option]').length;

    // The last option cannot be removed: every appliance has at least one power.
    const refreshRows = () => {
        const rows = body.querySelectorAll('[data-catalog-option]');
        rows.forEach((row) => {
            row.querySelector('[data-catalog-remove-option]').hidden = rows.length === 1;
        });
    };

    form.querySelector('[data-catalog-add-option]').addEventListener('click', () => {
        body.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(nextIndex++)));
        refreshRows();
        body.lastElementChild?.querySelector('input:not([type="hidden"])')?.focus();
    });

    body.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-catalog-remove-option]');

        if (remove) {
            remove.closest('[data-catalog-option]').remove();
            refreshRows();
        }
    });

    const followUsage = () => {
        hours.hidden = usage.value === 'always';
        hoursLabel.textContent = usage.value === 'week' ? 'Horas a la semana' : 'Horas al día';
        hours.querySelector('input').max = usage.value === 'week' ? '168' : '24';
    };

    usage.addEventListener('change', followUsage);
    followUsage();
    refreshRows();
};

document.addEventListener('DOMContentLoaded', initCatalogForm);
document.addEventListener('livewire:navigated', initCatalogForm);

// Installer form (installers/form.blade.php, ADR-0022): the municipality counter and the button that
// picks or clears them all. Without JavaScript the checkboxes still work; only the count stands still.
const installerForms = new WeakSet();

const initInstallerForm = () => {
    const fieldset = document.querySelector('[data-installer-municipalities]');

    if (!fieldset || installerForms.has(fieldset)) {
        return;
    }

    installerForms.add(fieldset);

    const boxes = [...fieldset.querySelectorAll('input[type="checkbox"]')];
    const count = fieldset.querySelector('[data-municipality-count]');
    const toggle = fieldset.querySelector('[data-municipality-toggle]');

    const refresh = () => {
        const chosen = boxes.filter((box) => box.checked).length;
        const all = chosen === boxes.length && boxes.length > 0;

        count.textContent = chosen === 0
            ? 'Ninguno elegido'
            : all
                ? `Toda La Guajira (${boxes.length})`
                : `${chosen} de ${boxes.length}`;
        toggle.textContent = all ? 'Quitar todos' : 'Seleccionar todos';
    };

    toggle.addEventListener('click', () => {
        const select = boxes.some((box) => !box.checked);

        boxes.forEach((box) => {
            box.checked = select;
        });
        // A "change" nobody fires is a map left painted with the old answer: the coverage map
        // repaints on this event, like it does for a click on a single municipality.
        fieldset.dispatchEvent(new Event('change', { bubbles: true }));
    });

    fieldset.addEventListener('change', refresh);
    refresh();
};

// Installer inbox (installers/inbox.blade.php, ADR-0023): the contract value belongs to a closed
// deal, so it only shows for that answer. A class, never [hidden]: Tailwind hides that one for good.
const initInboxAnswers = () => {
    document.querySelectorAll('[data-inbox-answer]').forEach((answer) => {
        const status = answer.querySelector('[data-inbox-status]');
        const contract = answer.querySelector('[data-inbox-contract]');

        if (!status || !contract || status.dataset.wired) {
            return;
        }

        status.dataset.wired = 'true';
        status.addEventListener('change', () => contract.classList.toggle('is-shown', status.value === 'won'));
    });
};

document.addEventListener('DOMContentLoaded', initInboxAnswers);
document.addEventListener('livewire:navigated', initInboxAnswers);

// Coverage map of the installer form (ADR-0022): Leaflet and the GeoJSON load only on that page.
const coverageMaps = new WeakSet();

const initCoverageMap = () => {
    const root = document.querySelector('[data-coverage-map]');

    if (!root || coverageMaps.has(root)) {
        return;
    }

    coverageMaps.add(root);
    import('./coverage-map.js')
        .then(({ mountCoverageMap }) => mountCoverageMap(root))
        .catch((error) => console.warn('Coverage map unavailable; the municipality list stays.', error));
};

document.addEventListener('DOMContentLoaded', initCoverageMap);
document.addEventListener('livewire:navigated', initCoverageMap);

document.addEventListener('DOMContentLoaded', initInstallerForm);
document.addEventListener('livewire:navigated', initInstallerForm);

// 3D illustration of "Mi sistema" (ADR-0012, resources/js/solar-scene): Three.js loads only where it is used.
document.addEventListener('DOMContentLoaded', initSolarScenes);
document.addEventListener('livewire:navigated', initSolarScenes);

// 3D stations of the climate data page (ADR-0018, resources/js/station-scene).
document.addEventListener('DOMContentLoaded', initStationScenes);
document.addEventListener('livewire:navigated', initStationScenes);

// The chosen appliance in 3D in the consumption diary (ADR-0019, resources/js/appliance-scene).
document.addEventListener('DOMContentLoaded', initApplianceScenes);
document.addEventListener('livewire:navigated', initApplianceScenes);

// The system animation of the 3D designer, development only (resources/js/system-scene).
document.addEventListener('DOMContentLoaded', initSystemScenes);
document.addEventListener('livewire:navigated', initSystemScenes);

// A quote in steps: the one the client reads (installers/quote.blade.php, ADR-0026) and the one
// the installer fills in (installers/quote-request.blade.php, ADR-0027). The steps are real links
// to each panel, so without JavaScript both pages work as one long page; here they fold.
//
// The panel is shown with a class and never with [hidden], which Tailwind's preflight hides for
// good; the .solar-js class of <html> is what hides the rest before the first paint.
//
// Reading, the steps are tabs (role=tab, one stop in the tab order, arrows to move). Filling a
// form they are steps: every one stays tabbable and the current one is aria-current.
const showQuoteStep = (key, { focus = false, updateHash = true } = {}) => {
    const steps = Array.from(document.querySelectorAll('[data-quote-step]'));
    const target = steps.find((step) => step.dataset.quoteStep === key);

    if (!target) {
        return;
    }

    steps.forEach((step) => {
        const selected = step === target;
        const isTab = step.getAttribute('role') === 'tab';

        if (isTab) {
            step.setAttribute('aria-selected', String(selected));
            step.tabIndex = selected ? 0 : -1;
        } else if (selected) {
            step.setAttribute('aria-current', 'step');
        } else {
            step.removeAttribute('aria-current');
        }
    });

    document.querySelectorAll('[data-quote-panel]').forEach((panel) => {
        panel.classList.toggle('is-current', panel.dataset.quotePanel === key);
    });

    if (focus) {
        target.focus();
    }

    if (updateHash) {
        // replaceState, not the hash itself: setting it would jump the page to the panel.
        window.history.replaceState(window.history.state, '', `#paso-${key}`);
    }
};

const initQuoteSteps = () => {
    const tablist = document.querySelector('[data-quote-steps]');

    if (!tablist || tablist.dataset.quoteStepsBound) {
        return;
    }

    tablist.dataset.quoteStepsBound = 'true';

    const steps = () => Array.from(tablist.querySelectorAll('[data-quote-step]'));

    tablist.addEventListener('click', (event) => {
        const step = event.target.closest('[data-quote-step]');

        if (!step || event.metaKey || event.ctrlKey || event.shiftKey) {
            return;
        }

        event.preventDefault();
        showQuoteStep(step.dataset.quoteStep);
    });

    tablist.addEventListener('keydown', (event) => {
        const list = steps();
        const index = list.indexOf(document.activeElement);

        // Arrows move between tabs; in a wizard each step is its own tab stop and the arrows belong
        // to the page.
        if (index === -1 || list[index].getAttribute('role') !== 'tab') {
            return;
        }

        const next = {
            ArrowRight: (index + 1) % list.length,
            ArrowLeft: (index - 1 + list.length) % list.length,
            Home: 0,
            End: list.length - 1,
        }[event.key];

        if (next === undefined) {
            return;
        }

        event.preventDefault();
        showQuoteStep(list[next].dataset.quoteStep, { focus: true });
    });

    // A required field inside a folded step cannot be focused, and the browser would refuse to
    // submit without saying why. Open its step first, and the native message lands on the field.
    tablist.closest('.solar-card')?.querySelector('form')?.addEventListener(
        'invalid',
        (event) => {
            const panel = event.target.closest('[data-quote-panel]');

            if (panel && !panel.classList.contains('is-current')) {
                showQuoteStep(panel.dataset.quotePanel);
            }
        },
        true,
    );

    // "Siguiente" and "Anterior" at the foot of each panel. The new panel takes the focus, or the
    // keyboard would stay at the bottom of a panel nobody is reading any more.
    document.querySelectorAll('[data-quote-go]').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (event.metaKey || event.ctrlKey || event.shiftKey) {
                return;
            }

            event.preventDefault();
            const key = link.dataset.quoteGo;
            showQuoteStep(key);
            document.querySelector(`[data-quote-panel="${key}"]`)?.focus();
            tablist.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });

    // After a rejected form, the step that holds the first error wins: the message is there.
    // Otherwise, opening the page on #paso-… lands on that step, which is what a shared link means.
    const withError = document.querySelector('[data-quote-panel].has-error');
    const fromHash = window.location.hash.replace('#paso-', '');
    const start = withError?.dataset.quotePanel
        ?? (steps().some((step) => step.dataset.quoteStep === fromHash) ? fromHash : steps()[0]?.dataset.quoteStep);

    showQuoteStep(start, { updateHash: false });
};

document.addEventListener('DOMContentLoaded', initQuoteSteps);
document.addEventListener('livewire:navigated', initQuoteSteps);
