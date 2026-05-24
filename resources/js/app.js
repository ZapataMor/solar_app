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

const initSolarCharts = () => {
    const timeScaleDataElement = document.getElementById('solar-timescale-chart-data');
    const weatherStationDataElement = document.getElementById('weather-station-chart-data');
    const weatherStationRealtimeDataElement = document.getElementById('weather-station-realtime-chart-data');

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

    if (payload.chartRows) {
        upsertWeatherStationRealtimeChart(payload.chartRows);
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
