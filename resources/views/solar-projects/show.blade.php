@php
    $technicalParameter = $solarProject->technicalParameter;
    $calculationResult  = $solarProject->calculationResult;
    $timeScales         = $timeScales ?? ['defaultScale' => 'monthly', 'scales' => [], 'activeScale' => null];
    $activeScaleKey     = $timeScales['defaultScale'] ?? 'monthly';
    $activeScale        = $timeScales['activeScale'] ?? ($timeScales['scales'][$activeScaleKey] ?? null);
    $dashboard          = $dashboard ?? ['executiveSummary' => ['enabled' => false]];
    $futurePredictions  = $dashboard['futurePredictions'] ?? [];
    $generateAiRecommendations = (bool) ($generateAiRecommendations ?? false);
    $aiFocus            = (string) ($aiFocus ?? 'savings');
    $aiFocusOptions     = [
        'savings'     => 'Ahorro economico',
        'load_shift'  => 'Traslado de cargas',
        'risk'        => 'Riesgo operativo',
        'maintenance' => 'Mantenimiento',
        'climate'     => 'Adaptacion climatica',
    ];
    $aiSelectedPackItem     = collect($dashboard['executiveSummary']['recommendationPack'] ?? [])->firstWhere('key', $aiFocus);
    $dailyRecommendationText = trim((string) ($dashboard['executiveSummary']['dailyRecommendation'] ?? ''));
    $selectedPackMessageText = trim((string) ($aiSelectedPackItem['message'] ?? ''));
    $showAiSelectedPackItem  = $aiSelectedPackItem
        && $selectedPackMessageText !== ''
        && mb_strtolower($selectedPackMessageText) !== mb_strtolower($dailyRecommendationText);
    $initialAiChatMessage = $showAiSelectedPackItem ? $selectedPackMessageText : $dailyRecommendationText;
    $solarAiConfig = [
        'endpoint' => route('solar-projects.ai-recommendations', $solarProject),
        'csrfToken' => csrf_token(),
        'initialFocus' => $aiFocus,
        'focusOptions' => $aiFocusOptions,
        'provider' => strtoupper((string) config('services.openai_recommendations.provider', 'openai')),
        'generated' => $generateAiRecommendations,
        'initialMessage' => $initialAiChatMessage,
        'initialError' => $dashboard['executiveSummary']['error'] ?? null,
    ];
    $solarAiPredictionConfig = [
        'endpoint' => route('solar-projects.ai-prediction', $solarProject),
        'csrfToken' => csrf_token(),
        'provider' => 'CLAUDE',
    ];
    $weatherStationStats    = $weatherStationStats ?? [];
    $recentWeatherStationReadings = $recentWeatherStationReadings ?? collect();
    $analysisClimateSource = $analysisClimateSource ?? 'nasa_power';
    $analysisClimateRows = collect($analysisClimateRows ?? []);

    // Ambient Weather (safe defaults)
    $ambientWeatherStats    = $ambientWeatherStats ?? [];
    $recentAmbientReadings  = $recentAmbientReadings ?? collect();
    $activeClimateSource    = $activeClimateSource ?? null;

    // Derived values
    $coverage      = $calculationResult ? (float) $calculationResult->coverage_percentage : null;
    $annualSavings = $calculationResult ? (float) $calculationResult->estimated_annual_savings_cop : null;
    $annualGen     = $calculationResult ? (float) $calculationResult->estimated_annual_generation_kwh : null;
    $monthlyGen    = $calculationResult ? (float) $calculationResult->estimated_monthly_generation_kwh : null;
    $installedKwp  = $calculationResult?->installed_capacity_kwp ? (float) $calculationResult->installed_capacity_kwp : null;
    $numPanels     = $calculationResult?->estimated_panels ?? null;
    $installationCost = $calculationResult?->installation_cost_cop !== null ? (float) $calculationResult->installation_cost_cop : null;
    $paybackYears = $calculationResult?->payback_period_years !== null ? (float) $calculationResult->payback_period_years : null;
    $monthlyConsumptionBase = (float) $solarProject->monthlyConsumption();
    $annualConsumptionBase = (float) $solarProject->annualConsumption();
    $monthlySavingsEstimated = $annualSavings !== null ? $annualSavings / 12 : null;
    $monthlyBalanceEstimated = $monthlyGen !== null ? $monthlyGen - $monthlyConsumptionBase : null;
    $annualBalanceEstimated = $annualGen !== null ? $annualGen - $annualConsumptionBase : null;
    $investmentBaseCost = $installationCost ?? ($installedKwp !== null ? $installedKwp * 5000000 : null);
    $paybackStatusText = $paybackYears === null
        ? 'No es posible calcular la recuperacion de la inversion porque no hay ahorro anual estimado.'
        : ($paybackYears <= 6
            ? 'Retorno atractivo en el escenario actual.'
            : ($paybackYears <= 10
                ? 'Retorno moderado; revisa eficiencia y costos.'
                : 'Retorno largo; conviene optimizar dimensionamiento o tarifa.'));
    $ambientDailyHsp = !empty($ambientWeatherStats['averageRadiation'])
        ? ((float) $ambientWeatherStats['averageRadiation'] * 24 / 1000)
        : null;
    $ambientContextText = $ambientDailyHsp !== null
        ? 'Ambient Weather (rango del proyecto): ' . number_format($ambientDailyHsp, 2, ',', '.') . ' HSP/dia. Sin degradacion anual ni costos O&M.'
        : 'Ambient Weather (rango del proyecto): sin lecturas suficientes para estimar HSP/dia.';

    $coverageTone  = $coverage === null ? 'warn' : ($coverage >= 100 ? 'success' : ($coverage >= 70 ? 'warn' : 'danger'));
    $coverageLabel = $coverage === null ? 'Pendiente' : number_format($coverage, 1, ',', '.') . '%';

    $tariffValue   = $solarProject->energy_rate_cop_per_kwh ?? $solarProject->energy_rate_cop_kwh ?? null;

    // Radiation source: prefer the latest registered reading, then aggregates.
    $latestAmbientReading = $recentAmbientReadings
        ->filter(fn ($reading) => $reading->radiationValue() !== null)
        ->sortByDesc('recorded_at')
        ->first();
    $latestStationReading = $recentWeatherStationReadings
        ->filter(fn ($reading) => $reading->radiationValue() !== null)
        ->sortByDesc('measured_at')
        ->first();

    $ambientMeasuredAt = $latestAmbientReading?->recorded_at;
    $stationMeasuredAt = $latestStationReading?->measured_at;
    $useAmbientReading = $latestAmbientReading
        && (!$latestStationReading || ($ambientMeasuredAt && $stationMeasuredAt && $ambientMeasuredAt->greaterThanOrEqualTo($stationMeasuredAt)));

    $avgRadiation = null;
    $radiationSource = 'none';
    $radiationMeasuredAt = null;

    if ($useAmbientReading) {
        $avgRadiation = (float) $latestAmbientReading->radiationValue();
        $radiationSource = 'ambient';
        $radiationMeasuredAt = $ambientMeasuredAt;
    } elseif ($latestStationReading) {
        $avgRadiation = (float) $latestStationReading->radiationValue();
        $radiationSource = 'station';
        $radiationMeasuredAt = $stationMeasuredAt;
    } elseif (!empty($ambientWeatherStats['averageRadiation'])) {
        $avgRadiation = (float) $ambientWeatherStats['averageRadiation'];
        $radiationSource = 'ambient';
    } elseif (!empty($weatherStationStats['averageRadiation'])) {
        $avgRadiation = (float) $weatherStationStats['averageRadiation'];
        $radiationSource = 'station';
    }

    $latestUvIndex = !empty($ambientWeatherStats['maxUvIndex'])
        ? (float) $ambientWeatherStats['maxUvIndex']
        : (isset($weatherStationStats['maxUvIndex']) ? (float) $weatherStationStats['maxUvIndex'] : null);

    // Solar condition scene: the animation follows registered irradiance.
    $heroScene = $avgRadiation === null ? 'unknown'
        : ($avgRadiation >= 550 ? 'high' : ($avgRadiation >= 250 ? 'medium' : 'low'));
    $solarLevel = $avgRadiation !== null ? (int) round(min(100, max(0, $avgRadiation / 550 * 100))) : null;
    $heroProduction = $installedKwp !== null && $solarLevel !== null
        ? $installedKwp * ($solarLevel / 100)
        : null;

    $heroSceneConfig = [
        'high'    => ['label' => 'Radiacion excelente', 'sublabel' => 'Produccion optima', 'status' => 'Irradiancia alta', 'state' => 'Sistema estable', 'color' => '#ffd05c', 'chart' => '#ffb703', 'icon' => 'A', 'efficiency' => $solarLevel],
        'medium'  => ['label' => 'Radiacion moderada', 'sublabel' => 'Produccion estable', 'status' => 'Irradiancia media', 'state' => 'Produccion variable', 'color' => '#ffc20a', 'chart' => '#ff9f0a', 'icon' => 'M', 'efficiency' => $solarLevel],
        'low'     => ['label' => 'Radiacion baja', 'sublabel' => 'Produccion reducida', 'status' => 'Irradiancia baja', 'state' => 'Capacidad reducida', 'color' => '#e37b61', 'chart' => '#e37b61', 'icon' => 'B', 'efficiency' => $solarLevel],
        'unknown' => ['label' => 'Sin lectura solar', 'sublabel' => 'Esperando irradiancia registrada', 'status' => 'Datos pendientes', 'state' => 'Sin dato solar', 'color' => '#94a3b8', 'chart' => '#94a3b8', 'icon' => '-', 'efficiency' => 0],
    ];
    $scene = $heroSceneConfig[$heroScene];
    $heroStatusLabel = $scene['status'];
    $heroTrendWeights = [5, 10, 22, 39, 58, 75, 88, 94, 91, 80, 62, 42, 22, 10, 5];

    $riskText  = (string) ($activeScale['risk'] ?? 'Sin riesgo critico detectado.');
    $riskTone  = str_contains(strtolower($riskText), 'crit') ? 'danger' : (filled($riskText) ? 'warn' : 'success');

    // Source badge config
    $sourceLabels = [
        'ambient' => ['label' => 'Ambient Weather', 'color' => 'var(--solar-success)',  'bg' => 'var(--solar-success-bg)'],
        'station' => ['label' => 'Estacion local',  'color' => 'var(--solar-warning)', 'bg' => 'var(--solar-warning-bg)'],
        'none'    => ['label' => 'Sin datos',        'color' => 'var(--solar-text-muted)','bg' => 'var(--solar-surface-muted)'],
    ];
    $sourceBadge = $sourceLabels[$radiationSource] ?? $sourceLabels['none'];

    $fmt    = fn ($v, $d = 2) => number_format((float) $v, $d, ',', '.');
    $fmtKwh = fn ($v) => $fmt($v) . ' kWh';
    $fmtCop = fn ($v) => '$ ' . number_format((float) $v, 0, ',', '.') . ' COP';
    $locationTypeLabels = [
        'urbana' => 'Urbana',
        'rural' => 'Rural',
        'rural_dispersa' => 'Rural dispersa',
        'alta_guajira' => 'Alta Guajira',
    ];

    $installedLabel   = $installedKwp ? $fmt($installedKwp) . ' kWp' : 'Pendiente';
    $usableAreaLabel  = $calculationResult?->usable_area_m2
        ? $fmt($calculationResult->usable_area_m2) . ' m²'
        : ($technicalParameter?->available_area_m2 ? $fmt($technicalParameter->available_area_m2) . ' m²' : 'Pendiente');

    $analysisSourceLabel = match ($analysisClimateSource) {
        'ambient' => 'Ambient Weather',
        'local' => 'Centro meteorologico',
        default => 'NASA POWER',
    };
    $tableRows = $analysisClimateRows->values()->all();

    $badgeClass = fn ($tone) => match ($tone) {
        'success' => 'sdash-badge--success',
        'warn'    => 'sdash-badge--warn',
        'danger'  => 'sdash-badge--danger',
        default   => '',
    };
@endphp

<x-layouts::app :title="$solarProject->name">
<style>
/* ── Design tokens & base ───────────────────────────────────── */
.sdash {
    --sdash-radius: 16px;
    --sdash-radius-sm: 10px;
    --sdash-gap: 1.25rem;
    --sdash-transition: 180ms ease;
    display: flex;
    flex-direction: column;
    gap: var(--sdash-gap);
    padding: 1.5rem clamp(1rem, 3vw, 2rem) 3rem;
    max-width: 90rem;
    margin: 0 auto;
}

/* ── Card base ──────────────────────────────────────────────── */
.sdash-card {
    background: var(--solar-surface-strong);
    border: 1px solid var(--solar-border);
    border-radius: var(--sdash-radius);
    box-shadow: var(--solar-shadow);
    overflow: hidden;
}
.dark .sdash-card {
    background: var(--solar-surface-elevated);
}

/* ── Alerts ─────────────────────────────────────────────────── */
.sdash-alert {
    display: flex;
    align-items: flex-start;
    gap: .75rem;
    padding: .875rem 1.125rem;
    border-radius: var(--sdash-radius-sm);
    font-size: .875rem;
    line-height: 1.4;
}
.sdash-alert--success { background: var(--solar-success-bg); color: var(--solar-success); border: 1px solid color-mix(in srgb, var(--solar-success) 30%, transparent); }
.sdash-alert--danger  { background: var(--solar-danger-bg);  color: var(--solar-danger);  border: 1px solid color-mix(in srgb, var(--solar-danger)  30%, transparent); }

/* ── Project header ─────────────────────────────────────────── */
.sdash-header {
    display: grid;
    gap: 1.25rem;
    padding: 1.75rem 2rem;
}
@media (min-width: 900px) {
    .sdash-header { grid-template-columns: 1fr auto; align-items: start; }
}

.sdash-header__kicker {
    display: flex;
    align-items: center;
    gap: .5rem;
    margin-bottom: .35rem;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: var(--solar-text-muted);
}
.sdash-header__kicker-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: var(--solar-sun);
}
.sdash-header__title {
    font-family: var(--font-display);
    font-size: clamp(1.4rem, 3vw, 2rem);
    font-weight: 700;
    color: var(--solar-text);
    letter-spacing: -.02em;
    margin: 0 0 .25rem;
}
.sdash-header__meta {
    font-size: .82rem;
    color: var(--solar-text-muted);
    margin: 0 0 .75rem;
}
.sdash-header__desc {
    font-size: .875rem;
    color: var(--solar-text-muted);
    margin-bottom: .85rem;
    max-width: 56ch;
    line-height: 1.45;
}
.sdash-badges {
    display: flex;
    flex-wrap: wrap;
    gap: .4rem;
}
.sdash-badge {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .25rem .7rem;
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 600;
    letter-spacing: .04em;
    border: 1px solid transparent;
    background: var(--solar-surface-muted);
    color: var(--solar-text-muted);
}
.sdash-badge--success { background: var(--solar-success-bg); color: var(--solar-success); border-color: color-mix(in srgb, var(--solar-success) 28%, transparent); }
.sdash-badge--warn    { background: var(--solar-warning-bg); color: var(--solar-warning); border-color: color-mix(in srgb, var(--solar-warning) 28%, transparent); }
.sdash-badge--danger  { background: var(--solar-danger-bg);  color: var(--solar-danger);  border-color: color-mix(in srgb, var(--solar-danger)  28%, transparent); }

/* ── Action buttons group ───────────────────────────────────── */
.sdash-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    align-items: flex-start;
}
.sdash-btn {
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    padding: .5rem 1rem;
    border-radius: var(--sdash-radius-sm);
    font-size: .8rem;
    font-weight: 600;
    border: 1px solid transparent;
    cursor: pointer;
    transition: opacity var(--sdash-transition), box-shadow var(--sdash-transition);
    white-space: nowrap;
    text-decoration: none;
}
.sdash-btn:hover { opacity: .85; }
.sdash-btn:disabled {
    cursor: not-allowed;
    opacity: .55;
}
.sdash-btn--primary {
    background: var(--solar-sun);
    color: #fff;
    box-shadow: 0 2px 12px -4px color-mix(in srgb, var(--solar-sun) 60%, transparent);
}
.sdash-btn--ghost {
    background: transparent;
    color: var(--solar-text-muted);
    border-color: var(--solar-border-strong);
}
.sdash-btn--ghost:hover { background: var(--solar-surface-muted); color: var(--solar-text); }
.sdash-btn--danger {
    background: var(--solar-danger-bg);
    color: var(--solar-danger);
    border-color: color-mix(in srgb, var(--solar-danger) 30%, transparent);
}
.sdash-btn--divider {
    width: 1px;
    height: 28px;
    background: var(--solar-border-strong);
    margin: auto 0;
    flex-shrink: 0;
}

/* ── KPI strip ──────────────────────────────────────────────── */
.sdash-kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: .75rem;
    padding: 1.25rem 1.5rem;
}
.sdash-kpi {
    display: flex;
    flex-direction: column;
    gap: .2rem;
    padding: 1rem 1.125rem;
    border-radius: var(--sdash-radius-sm);
    background: var(--solar-surface-muted);
    border: 1px solid var(--solar-border);
    transition: box-shadow var(--sdash-transition);
}
.sdash-kpi:hover { box-shadow: 0 4px 20px -8px var(--solar-border-strong); }
.sdash-kpi__icon { font-size: 1.1rem; margin-bottom: .15rem; }
.sdash-kpi__value {
    font-family: var(--font-display);
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--solar-text);
    letter-spacing: -.02em;
    line-height: 1.1;
}
.sdash-kpi__value--accent { color: var(--solar-sun); }
.sdash-kpi__value--success { color: var(--solar-success); }
.sdash-kpi__value--warn    { color: var(--solar-warning); }
.sdash-kpi__value--danger  { color: var(--solar-danger); }
.sdash-kpi__label {
    font-size: .7rem;
    font-weight: 600;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--solar-text-muted);
}
.sdash-kpi__sub {
    font-size: .7rem;
    color: var(--solar-text-muted);
    margin-top: .1rem;
}

/* ── Section headers ────────────────────────────────────────── */
.sdash-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: .75rem;
    padding: 1.25rem 1.5rem .75rem;
    border-bottom: 1px solid var(--solar-border);
}
.sdash-section-head__title {
    font-family: var(--font-display);
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--solar-text);
    margin: 0;
}
.sdash-section-head__sub {
    font-size: .78rem;
    color: var(--solar-text-muted);
    margin: .1rem 0 0;
}

/* ── Two-column grid ────────────────────────────────────────── */
.sdash-grid-2 {
    display: grid;
    gap: var(--sdash-gap);
}
@media (min-width: 1024px) {
    .sdash-grid-2 { grid-template-columns: 1fr 1fr; }
    .sdash-grid-2--wide { grid-template-columns: 2fr 1fr; }
}

/* ── Condition card ─────────────────────────────────────────── */
.sdash-condition {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
}
.sdash-condition__summary {
    display: flex;
    align-items: center;
    gap: 1rem;
    width: 100%;
}
.sdash-condition__orb {
    width: 52px; height: 52px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}
.sdash-condition__orb--high   { background: var(--solar-success-bg); }
.sdash-condition__orb--medium { background: var(--solar-warning-bg); }
.sdash-condition__orb--low    { background: var(--solar-danger-bg); }
.sdash-condition__orb--unknown { background: var(--solar-surface-muted); }
.sdash-condition__info p { margin: 0; }
.sdash-condition__status {
    font-weight: 700;
    font-size: 1rem;
    color: var(--solar-text);
}
.sdash-condition__detail {
    font-size: .8rem;
    color: var(--solar-text-muted);
    margin-top: .15rem !important;
}
.sdash-hero-stage {
    padding: 1rem 1rem 0;
}
.sdash-hero-stage .solar-live-dashboard {
    border-radius: calc(var(--sdash-radius) - 4px);
    margin: 0;
}
html:not(.dark) .sdash-hero-stage .solar-live-dashboard {
    background: transparent;
    box-shadow: none;
    color: var(--solar-text);
    padding: 0;
}
html:not(.dark) .sdash-hero-stage .solar-live-kicker,
html:not(.dark) .sdash-hero-stage .solar-live-meta,
html:not(.dark) .sdash-hero-stage .solar-live-footer {
    color: var(--solar-text-muted);
}
html:not(.dark) .sdash-hero-stage .solar-live-control {
    background: var(--solar-surface-muted);
    border-color: var(--solar-border);
    color: var(--solar-text-muted);
}
html:not(.dark) .sdash-hero-stage .solar-live-panel {
    box-shadow: none;
}
.sdash-hero-stage .solar-live-panel,
.sdash-hero-stage .solar-live-sky {
    min-height: clamp(320px, 40vh, 460px);
}
.sdash-hero-stage .solar-live-curve span {
    background: linear-gradient(180deg, color-mix(in srgb, var(--chart-color, #ff9f0a) 90%, white 10%), color-mix(in srgb, var(--chart-color, #ff9f0a) 12%, transparent));
}
.sdash-hero-stage .solar-live-curve {
    position: relative;
    display: block;
    overflow: visible;
    height: 5.25rem;
    border-radius: 0;
    filter: drop-shadow(0 0 18px color-mix(in srgb, var(--chart-color, #ff9f0a) 34%, transparent));
}
.sdash-hero-stage .solar-live-curve span {
    display: none;
}
.sdash-hero-stage .solar-live-curve::before,
.sdash-hero-stage .solar-live-curve::after {
    content: '';
    position: absolute;
    inset: 0;
    background: var(--chart-color, #ff9f0a);
    pointer-events: none;
    mask-repeat: no-repeat;
    mask-size: 100% 100%;
    mask-position: center;
}
.sdash-hero-stage .solar-live-curve::before {
    opacity: .95;
    mask-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 1000 160' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 135 C150 130 260 86 382 55 C520 18 655 32 782 83 C865 116 925 133 980 136' fill='none' stroke='white' stroke-width='8' stroke-linecap='round'/%3E%3C/svg%3E");
    animation: solarCurveTrace 2.9s ease-in-out infinite;
}
.sdash-hero-stage .solar-live-curve::after {
    opacity: .22;
    mask-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 1000 160' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M20 145 C150 138 260 92 382 62 C520 24 655 38 782 90 C865 122 925 138 980 142 L980 160 L20 160 Z' fill='white'/%3E%3C/svg%3E");
    animation: solarCurveGlow 3.4s ease-in-out infinite;
}
.sdash-hero-stage .solar-live-sky::before {
    content: '';
    position: absolute;
    inset: -18% -28%;
    z-index: 1;
    pointer-events: none;
    background:
        radial-gradient(ellipse at 18% 42%, rgba(234, 246, 255, .34) 0%, rgba(234, 246, 255, .12) 24%, transparent 46%),
        linear-gradient(115deg, transparent 18%, rgba(255, 255, 255, .16) 42%, transparent 66%);
    mix-blend-mode: screen;
    opacity: .64;
    animation: solarSkySweep 8s ease-in-out infinite;
}
.sdash-hero-stage .solar-live-sky::after {
    animation: solarSkyBreath 6s ease-in-out infinite;
}
.sdash-hero-stage .solar-hero-cloud {
    border-radius: 999px;
    background: rgba(226, 238, 248, .38);
    filter: blur(42px);
    transform: none;
    animation: solarMistDrift 9s ease-in-out infinite;
}
.sdash-hero-stage .solar-hero-cloud::before,
.sdash-hero-stage .solar-hero-cloud::after {
    display: none;
}
.sdash-hero-stage .solar-hero-cloud-a {
    top: 18%;
    left: 6%;
    width: 24rem;
    height: 9rem;
    opacity: .42;
}
.sdash-hero-stage .solar-hero-cloud-b {
    top: 34%;
    left: 18%;
    width: 36rem;
    height: 12rem;
    opacity: .32;
    animation-duration: 12s;
    animation-delay: -4s;
}
.sdash-hero-stage .solar-hero-cloud-c {
    top: 10%;
    right: 10%;
    left: auto;
    width: 18rem;
    height: 8rem;
    opacity: .22;
    animation-duration: 10s;
    animation-delay: -7s;
}
.sdash-hero-stage .solar-hero-scene-high .solar-hero-cloud {
    opacity: .14;
}
.sdash-hero-stage .solar-hero-scene-low .solar-hero-cloud {
    opacity: .36;
}
.sdash-hero-stage .solar-hero-rain {
    opacity: 0 !important;
}
.sdash-hero-stage .solar-hero-particles {
    opacity: 1;
    z-index: 2;
}
.sdash-hero-stage .solar-hero-particles span {
    width: .28rem;
    height: .28rem;
    background: color-mix(in srgb, var(--scene-color, #ffc20a) 78%, white 22%);
    box-shadow: 0 0 14px color-mix(in srgb, var(--scene-color, #ffc20a) 70%, transparent);
    animation: solarParticleFloat 4.8s ease-in-out infinite;
}
.sdash-hero-stage .solar-hero-particles span:nth-child(1) { left: 12%; bottom: 58%; animation-delay: 0s; }
.sdash-hero-stage .solar-hero-particles span:nth-child(2) { left: 22%; bottom: 70%; animation-delay: -.8s; }
.sdash-hero-stage .solar-hero-particles span:nth-child(3) { left: 31%; bottom: 48%; animation-delay: -1.6s; }
.sdash-hero-stage .solar-hero-particles span:nth-child(4) { left: 42%; bottom: 64%; animation-delay: -2.1s; }
.sdash-hero-stage .solar-hero-particles span:nth-child(5) { left: 52%; bottom: 54%; animation-delay: -.4s; }
.sdash-hero-stage .solar-hero-particles span:nth-child(6) { left: 62%; bottom: 72%; animation-delay: -1.2s; }
.sdash-hero-stage .solar-hero-particles span:nth-child(7) { left: 72%; bottom: 46%; animation-delay: -2.6s; }
.sdash-hero-stage .solar-hero-particles span:nth-child(8) { left: 82%; bottom: 66%; animation-delay: -1.8s; }
.sdash-hero-stage .solar-hero-particles span:nth-child(9) { left: 88%; bottom: 36%; animation-delay: -.9s; }
.sdash-hero-stage .solar-hero-particles span:nth-child(10) { left: 18%; bottom: 34%; animation-delay: -2.9s; }
.sdash-hero-stage .solar-hero-sun-glow {
    z-index: 2;
    animation: solarSoftPulse 3.2s ease-in-out infinite, solarSunDrift 9s ease-in-out infinite;
}
.sdash-hero-stage .solar-hero-sun-core {
    z-index: 3;
    animation: solarCorePulse 2.8s ease-in-out infinite, solarSunDrift 9s ease-in-out infinite;
}
@keyframes solarMistDrift {
    0%, 100% { transform: translate3d(-2.2rem, .35rem, 0) scale(1); opacity: .24; }
    50% { transform: translate3d(2.4rem, -.75rem, 0) scale(1.1); opacity: .52; }
}
@keyframes solarSoftPulse {
    0%, 100% { opacity: .56; filter: blur(0); }
    50% { opacity: 1; filter: blur(1px); }
}
@keyframes solarCorePulse {
    0%, 100% { filter: saturate(1); box-shadow: 0 0 26px color-mix(in srgb, var(--scene-color, #ffc20a) 42%, transparent); }
    50% { filter: saturate(1.18) brightness(1.08); box-shadow: 0 0 46px color-mix(in srgb, var(--scene-color, #ffc20a) 62%, transparent); }
}
@keyframes solarSunDrift {
    0%, 100% { translate: 0 0; }
    50% { translate: .75rem -.45rem; }
}
@keyframes solarCurveTrace {
    0%, 100% { opacity: .72; transform: translateY(.16rem) scaleX(.965); }
    50% { opacity: 1; transform: translateY(-.18rem) scaleX(1.015); }
}
@keyframes solarCurveGlow {
    0%, 100% { opacity: .12; transform: translateY(.35rem) scaleY(.92); }
    50% { opacity: .26; transform: translateY(-.15rem) scaleY(1.06); }
}
@keyframes solarSkySweep {
    0%, 100% { transform: translate3d(-6%, 2%, 0) scale(1); opacity: .34; }
    50% { transform: translate3d(8%, -3%, 0) scale(1.08); opacity: .72; }
}
@keyframes solarSkyBreath {
    0%, 100% { opacity: .78; }
    50% { opacity: 1; }
}
@keyframes solarParticleFloat {
    0%, 100% { transform: translate3d(0, 0, 0) scale(.72); opacity: .18; }
    35% { opacity: .9; }
    50% { transform: translate3d(.45rem, -1.6rem, 0) scale(1.08); opacity: .75; }
    80% { opacity: .34; }
}
.sdash-hero-stage .solar-live-efficiency i::before {
    background: linear-gradient(90deg, color-mix(in srgb, var(--scene-color, #ffc20a) 42%, transparent), var(--scene-color, #ffc20a));
}
.sdash-hero-stage .solar-live-lights .is-current {
    background: var(--scene-color, #ffc20a);
    box-shadow: 0 0 22px color-mix(in srgb, var(--scene-color, #ffc20a) 72%, transparent);
}
.sdash-hero-stage .solar-hero-scene-unknown .solar-hero-sky {
    background: linear-gradient(180deg, #1f2937 0%, #475569 52%, #94a3b8 100%);
}
.sdash-hero-stage .solar-hero-scene-unknown .solar-hero-rain,
.sdash-hero-stage .solar-hero-scene-unknown .solar-hero-particles {
    opacity: 0;
}
.sdash-card > .sdash-hero-stage + .sdash-header {
    border-top: 1px solid var(--solar-border);
    padding-top: 1.75rem;
}

/* ── Stats row inside card ──────────────────────────────────── */
.sdash-stat-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
    border-top: 1px solid var(--solar-border);
}
.sdash-stat-cell {
    padding: .875rem 1.125rem;
    border-right: 1px solid var(--solar-border);
}
.sdash-stat-cell:last-child { border-right: none; }
.sdash-stat-cell__label {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--solar-text-muted);
    margin-bottom: .2rem;
}
.sdash-stat-cell__value {
    font-size: .95rem;
    font-weight: 700;
    color: var(--solar-text);
    font-family: var(--font-display);
}

/* ── Chart containers ───────────────────────────────────────── */
.sdash-chart-wrap {
    padding: 1rem 1.25rem 1.25rem;
}
.sdash-chart-canvas {
    position: relative;
    height: 220px;
}
.sdash-chart-canvas--tall { height: 260px; }

/* ── Scale tabs ─────────────────────────────────────────────── */
.sdash-scale-tabs {
    display: inline-flex;
    gap: .25rem;
    background: var(--solar-surface-muted);
    border: 1px solid var(--solar-border);
    border-radius: 8px;
    padding: .2rem;
}
.sdash-scale-tab {
    padding: .3rem .8rem;
    border-radius: 6px;
    font-size: .75rem;
    font-weight: 600;
    color: var(--solar-text-muted);
    background: transparent;
    border: none;
    cursor: pointer;
    transition: background var(--sdash-transition), color var(--sdash-transition);
}
.sdash-scale-tab.is-active,
.sdash-scale-tab:hover { background: var(--solar-surface-strong); color: var(--solar-sun); }

/* ── Recommendation cards ───────────────────────────────────── */
.sdash-rec-grid {
    display: grid;
    gap: .75rem;
    padding: 1.25rem 1.5rem;
}
@media (min-width: 768px) { .sdash-rec-grid { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 1280px) { .sdash-rec-grid--3 { grid-template-columns: repeat(3, 1fr); } }

.sdash-rec {
    background: var(--solar-surface-muted);
    border: 1px solid var(--solar-border);
    border-radius: var(--sdash-radius-sm);
    padding: 1rem 1.125rem;
}
.sdash-rec__label {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: var(--solar-sun);
    margin-bottom: .5rem;
}
.sdash-rec__text {
    font-size: .84rem;
    color: var(--solar-text-muted);
    line-height: 1.5;
}
.sdash-rec--ai { border-color: color-mix(in srgb, var(--solar-sun) 35%, transparent); }

/* ── Source indicator ───────────────────────────────────────── */
.sdash-source {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .75rem 1.5rem;
    font-size: .78rem;
    color: var(--solar-text-muted);
    border-bottom: 1px solid var(--solar-border);
}
.sdash-source__dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* ── AI select ──────────────────────────────────────────────── */
.sdash-ai-select {
    padding: .3rem .6rem;
    border-radius: 6px;
    border: 1px solid var(--solar-border-strong);
    background: var(--solar-surface-muted);
    color: var(--solar-text);
    font-size: .78rem;
    cursor: pointer;
}
.sdash-ai-select:focus {
    outline: 2px solid color-mix(in srgb, var(--solar-sun) 62%, transparent);
    outline-offset: 2px;
}

/* ── AI chat recommendations ───────────────────────────────── */
.sdash-ai-panel {
    border-color: color-mix(in srgb, var(--solar-sun) 28%, var(--solar-border));
}
.sdash-ai-shell {
    display: grid;
    gap: 1rem;
    padding: 1rem clamp(1rem, 2vw, 1.35rem) 1.25rem;
}
.sdash-ai-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    flex-wrap: wrap;
}
.sdash-ai-meta {
    display: flex;
    align-items: center;
    gap: .45rem;
    flex-wrap: wrap;
}
.sdash-prediction-panel {
    overflow: hidden;
}
.sdash-prediction-head {
    align-items: flex-start;
    padding-bottom: 1rem;
}
.sdash-prediction-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: .65rem;
    flex-wrap: wrap;
    margin-left: auto;
}
.sdash-prediction-body {
    display: grid;
    gap: 1rem;
    padding: 1rem clamp(1rem, 2vw, 1.35rem) 1.25rem;
}
.sdash-prediction-result {
    display: grid;
    gap: 1rem;
    padding: 1.05rem;
    border: 1px solid color-mix(in srgb, var(--solar-sun) 24%, var(--solar-border));
    border-radius: 12px;
    background:
        linear-gradient(180deg, color-mix(in srgb, var(--solar-surface-muted) 62%, transparent), transparent 60%),
        var(--solar-surface-strong);
}
.dark .sdash-prediction-result {
    background:
        linear-gradient(180deg, color-mix(in srgb, var(--solar-surface-muted) 44%, transparent), transparent 62%),
        var(--solar-surface-elevated);
}
.sdash-prediction-title {
    margin: 0;
    color: var(--solar-sun);
    font-size: .76rem;
    font-weight: 800;
    letter-spacing: .08em;
    line-height: 1.35;
    text-transform: uppercase;
}
.sdash-prediction-grid {
    display: grid;
    gap: .85rem;
}
@media (min-width: 900px) {
    .sdash-prediction-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
.sdash-prediction-section {
    min-width: 0;
    padding: .85rem .95rem;
    border: 1px solid var(--solar-border);
    border-radius: 10px;
    background: color-mix(in srgb, var(--solar-surface-muted) 56%, transparent);
}
.sdash-prediction-section--wide {
    grid-column: 1 / -1;
}
.sdash-prediction-label {
    margin: 0 0 .35rem;
    color: var(--solar-text-muted);
    font-size: .68rem;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
}
.sdash-prediction-text {
    max-width: 78rem;
    margin: 0;
    color: var(--solar-text);
    font-size: .9rem;
    line-height: 1.62;
}
.sdash-prediction-actions-list {
    display: grid;
    gap: .45rem;
    margin: 0;
    padding: 0;
    list-style: none;
}
.sdash-prediction-actions-list li {
    position: relative;
    padding-left: 1.05rem;
    color: var(--solar-text);
    font-size: .86rem;
    line-height: 1.5;
}
.sdash-prediction-actions-list li::before {
    content: "";
    position: absolute;
    left: 0;
    top: .62em;
    width: .35rem;
    height: .35rem;
    border-radius: 999px;
    background: var(--solar-sun);
}
.sdash-prediction-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    flex-wrap: wrap;
    padding-top: .1rem;
}
.sdash-prediction-note {
    margin: 0;
    color: var(--solar-text-muted);
    font-size: .76rem;
    line-height: 1.45;
}
.sdash-ai-chat {
    min-height: 18rem;
    max-height: 31rem;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: .85rem;
    padding: 1rem;
    border: 1px solid var(--solar-border);
    border-radius: 12px;
    background:
        linear-gradient(180deg, color-mix(in srgb, var(--solar-surface-muted) 60%, transparent), transparent 42%),
        var(--solar-surface-strong);
    scroll-behavior: smooth;
}
.dark .sdash-ai-chat {
    background:
        linear-gradient(180deg, color-mix(in srgb, var(--solar-surface-muted) 50%, transparent), transparent 42%),
        var(--solar-surface-elevated);
}
.sdash-ai-empty {
    margin: auto;
    max-width: 34rem;
    text-align: center;
    color: var(--solar-text-muted);
    font-size: .86rem;
    line-height: 1.55;
}
.sdash-ai-message {
    display: grid;
    gap: .35rem;
    max-width: min(44rem, 94%);
    animation: sdashFadeSlide 180ms ease both;
}
.sdash-ai-message--user {
    align-self: flex-end;
}
.sdash-ai-message--assistant {
    align-self: flex-start;
}
.sdash-ai-message__label {
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--solar-text-muted);
}
.sdash-ai-message--user .sdash-ai-message__label {
    text-align: right;
}
.sdash-ai-bubble {
    white-space: pre-line;
    line-height: 1.58;
    font-size: .9rem;
    border-radius: 12px;
    padding: .9rem 1rem;
    border: 1px solid var(--solar-border);
    color: var(--solar-text);
    overflow-wrap: anywhere;
}
.sdash-ai-message--user .sdash-ai-bubble {
    background: var(--solar-sun);
    border-color: var(--solar-sun);
    color: #fff;
}
.sdash-ai-message--assistant .sdash-ai-bubble {
    background: var(--solar-surface-muted);
}
.sdash-ai-cursor {
    display: inline-block;
    width: .5rem;
    color: var(--solar-sun);
    animation: sdashBlink 900ms steps(2, start) infinite;
}
.sdash-ai-skeleton {
    display: grid;
    gap: .55rem;
    width: min(34rem, 92%);
}
.sdash-ai-skeleton span {
    height: .8rem;
    border-radius: 999px;
    background: linear-gradient(90deg, var(--solar-surface-muted), color-mix(in srgb, var(--solar-sun) 20%, var(--solar-surface-muted)), var(--solar-surface-muted));
    background-size: 220% 100%;
    animation: sdashShimmer 1.1s ease-in-out infinite;
}
.sdash-ai-skeleton span:nth-child(2) { width: 86%; }
.sdash-ai-skeleton span:nth-child(3) { width: 64%; }
.sdash-ai-composer {
    display: grid;
    gap: .75rem;
    align-items: center;
}
@media (min-width: 760px) {
    .sdash-ai-composer {
        grid-template-columns: minmax(14rem, 18rem) 1fr auto auto;
    }
}
.sdash-ai-error {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    flex-wrap: wrap;
    padding: .8rem .95rem;
    border-radius: 10px;
    border: 1px solid color-mix(in srgb, var(--solar-danger) 30%, transparent);
    background: var(--solar-danger-bg);
    color: var(--solar-danger);
    font-size: .82rem;
}
@keyframes sdashFadeSlide {
    from { opacity: 0; transform: translateY(6px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes sdashBlink {
    50% { opacity: 0; }
}
@keyframes sdashShimmer {
    to { background-position: -220% 0; }
}

/* ── Table modal ────────────────────────────────────────────── */
.sdash-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 50;
    background: rgba(0,0,0,.5);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    backdrop-filter: blur(4px);
}
.sdash-modal {
    background: var(--solar-surface-strong);
    border: 1px solid var(--solar-border-strong);
    border-radius: var(--sdash-radius);
    width: 100%;
    max-width: 72rem;
    max-height: 88vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 40px 80px -20px rgba(0,0,0,.5);
}
.dark .sdash-modal { background: var(--solar-surface-elevated); }
.sdash-modal__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.125rem 1.5rem;
    border-bottom: 1px solid var(--solar-border);
    flex-shrink: 0;
}
.sdash-modal__title { font-size: 1rem; font-weight: 700; color: var(--solar-text); }
.sdash-modal__body  { overflow: auto; flex: 1; }

/* ── Data table ─────────────────────────────────────────────── */
.sdash-table { width: 100%; border-collapse: collapse; font-size: .8rem; }
.sdash-table th {
    padding: .65rem 1rem;
    text-align: left;
    font-size: .68rem;
    font-weight: 700;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--solar-text-muted);
    background: var(--solar-surface-muted);
    border-bottom: 1px solid var(--solar-border);
    position: sticky;
    top: 0;
    z-index: 1;
}
.sdash-table td {
    padding: .55rem 1rem;
    border-bottom: 1px solid var(--solar-border);
    color: var(--solar-text);
    vertical-align: middle;
}
.sdash-table tr:last-child td { border-bottom: none; }
.sdash-table tr:hover td { background: var(--solar-surface-muted); }

/* ── Filter input ───────────────────────────────────────────── */
.sdash-input {
    padding: .4rem .75rem;
    border-radius: 7px;
    border: 1px solid var(--solar-border-strong);
    background: var(--solar-surface-muted);
    color: var(--solar-text);
    font-size: .8rem;
}
.sdash-input:focus { outline: 2px solid var(--solar-sun); outline-offset: 1px; }

/* ── Misc ───────────────────────────────────────────────────── */
.sdash-empty {
    padding: 2rem;
    text-align: center;
    color: var(--solar-text-muted);
    font-size: .85rem;
}
.sdash-divider { height: 1px; background: var(--solar-border); margin: 0 1.5rem; }
</style>

<script type="application/json" id="solar-dashboard-scales-{{ $solarProject->id }}">@json($timeScales['scales'] ?? [])</script>
<script>
    window.solarDashboard = window.solarDashboard || function solarDashboard(element) {
        const configElement = document.getElementById(element.dataset.scaleConfig || '');
        const scales = configElement ? JSON.parse(configElement.textContent || '{}') : {};

        return {
            showModal: false,
            filter: '',
            activeScale: element.dataset.activeScale || 'monthly',
            scales,
            get scale() {
                return this.scales[this.activeScale] ?? null;
            },
        };
    };
</script>

<div
    class="sdash"
    data-scale-config="solar-dashboard-scales-{{ $solarProject->id }}"
    data-active-scale="{{ $activeScaleKey }}"
    x-data="solarDashboard($el)"
>

    {{-- ── Alerts ──────────────────────────────────────────── --}}
    @if (session('status'))
        <div class="sdash-alert sdash-alert--success" role="alert">
            <span>✓</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif
    @if ($errors->any())
        <div class="sdash-alert sdash-alert--danger" role="alert">
            <span>⚠</span>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <div class="sdash-card">
        <div class="sdash-section-head">
            <div>
                <h2 class="sdash-section-head__title">Estimacion de costo de instalacion</h2>
                <p class="sdash-section-head__sub">Precio historico usado al guardar esta cotizacion.</p>
            </div>
            <span class="sdash-badge sdash-badge--warn">COP</span>
        </div>
        <div class="sdash-kpis">
            <div class="sdash-kpi">
                <span class="sdash-kpi__label">Municipio</span>
                <span class="sdash-kpi__value">{{ $solarProject->municipality?->name ?? 'Pendiente' }}</span>
                <span class="sdash-kpi__sub">{{ $solarProject->municipality?->zone ?? $solarProject->location_name }}</span>
            </div>
            <div class="sdash-kpi">
                <span class="sdash-kpi__label">Tipo de ubicacion</span>
                <span class="sdash-kpi__value">{{ $locationTypeLabels[$solarProject->location_type] ?? 'Pendiente' }}</span>
                <span class="sdash-kpi__sub">Factor logistico aplicado</span>
            </div>
            <div class="sdash-kpi">
                <span class="sdash-kpi__label">Potencia requerida</span>
                <span class="sdash-kpi__value">{{ $solarProject->required_power_kw !== null ? $fmt($solarProject->required_power_kw) . ' kW' : 'Pendiente' }}</span>
                <span class="sdash-kpi__sub">Sistema dimensionado para cotizacion</span>
            </div>
            <div class="sdash-kpi">
                <span class="sdash-kpi__label">Precio base por kW</span>
                <span class="sdash-kpi__value">{{ $solarProject->base_price_per_kw !== null ? $fmtCop($solarProject->base_price_per_kw) : 'Pendiente' }}</span>
                <span class="sdash-kpi__sub">Promedio municipal</span>
            </div>
            <div class="sdash-kpi">
                <span class="sdash-kpi__label">Precio final por kW</span>
                <span class="sdash-kpi__value">{{ $solarProject->final_price_per_kw_used !== null ? $fmtCop($solarProject->final_price_per_kw_used) : 'Pendiente' }}</span>
                <span class="sdash-kpi__sub">Factor: {{ $solarProject->logistic_factor_used !== null ? number_format((float) $solarProject->logistic_factor_used, 2, ',', '.') : 'N/A' }}</span>
            </div>
            <div class="sdash-kpi">
                <span class="sdash-kpi__label">Costo estimado</span>
                <span class="sdash-kpi__value sdash-kpi__value--accent">{{ $solarProject->estimated_installation_cost !== null ? $fmtCop($solarProject->estimated_installation_cost) : 'Pendiente' }}</span>
                <span class="sdash-kpi__sub">Snapshot de la cotizacion</span>
            </div>
        </div>
        <p style="font-size:.78rem;color:var(--solar-text-muted);padding:0 1.5rem 1.25rem;line-height:1.55;">
            Este valor corresponde a una estimacion preliminar calculada con base en la potencia requerida, el precio promedio por kW instalado y el factor logistico asociado a la ubicacion seleccionada. El valor final puede variar segun visita tecnica, tipo de techo, distancia, transporte, baterias, estructura, protecciones electricas, certificacion RETIE y condiciones particulares del sitio.
        </p>
    </div>

    {{-- ── 1. Project Header ───────────────────────────────── --}}
    <div class="sdash-card">
        <div class="sdash-hero-stage">
            <section class="solar-live-dashboard sdash-hero-live solar-hero-scene-{{ $heroScene }}" style="--scene-color: {{ $scene['color'] }}; --chart-color: {{ $scene['chart'] }};">
                <header class="solar-live-header">
                    <div>
                        <div class="solar-live-kicker">Condicion solar en tiempo real</div>
                        <div class="solar-live-meta">
                            {{ ($radiationMeasuredAt ?? $solarProject->updated_at)?->format('d M Y - H:i') }}
                            COT
                            @if ($numPanels) - {{ $numPanels }} paneles @endif
                            @if ($installedKwp) - {{ $installedLabel }} @endif
                            - {{ $sourceBadge['label'] }}
                        </div>
                    </div>
                    <div class="solar-live-controls" aria-label="Nivel de radiacion">
                        <span class="solar-live-control {{ $heroScene === 'high' ? 'is-active' : '' }}">Alta</span>
                        <span class="solar-live-control {{ $heroScene === 'medium' ? 'is-active' : '' }}">Media</span>
                        <span class="solar-live-control {{ $heroScene === 'low' ? 'is-active' : '' }}">Baja</span>
                        <span class="solar-live-control is-auto {{ $heroScene === 'unknown' ? 'is-active' : '' }}">Auto</span>
                    </div>
                </header>

                <div class="solar-live-panel">
                    <aside class="solar-live-stats">
                        <div class="solar-live-status">
                            <div class="solar-live-lights" aria-hidden="true">
                                <span class="{{ $heroScene === 'high' ? 'is-current' : '' }}"></span>
                                <span class="{{ $heroScene === 'medium' ? 'is-current' : '' }}"></span>
                                <span class="{{ $heroScene === 'low' || $heroScene === 'unknown' ? 'is-current' : '' }}"></span>
                            </div>
                            <div>
                                <h1 style="color: {{ $scene['color'] }}">{{ $scene['label'] }}</h1>
                                <p>{{ $scene['sublabel'] }}</p>
                            </div>
                        </div>

                        <div class="solar-live-reading">
                            <p>Irradiancia solar</p>
                            <strong>{{ $avgRadiation !== null ? number_format($avgRadiation, 0, ',', '.') : 'N/A' }}</strong>
                            <span>W/m2</span>
                        </div>

                        <div class="solar-live-kpis">
                            <div>
                                <p>Produccion</p>
                                <strong>{{ $heroProduction !== null ? $fmt($heroProduction, 1) : 'N/A' }}<span>kW</span></strong>
                            </div>
                            <div>
                                <p>Nivel solar</p>
                                <strong>{{ $solarLevel !== null ? $solarLevel : 'N/A' }}<span>%</span></strong>
                            </div>
                            <div>
                                <p>Estado</p>
                                <strong class="solar-live-state">{{ $scene['state'] }}</strong>
                            </div>
                        </div>

                        <div class="solar-live-efficiency">
                            <div>
                                <span>Eficiencia del sistema</span>
                                <strong>{{ $scene['efficiency'] !== null ? $scene['efficiency'] . '%' : 'N/A' }}</strong>
                            </div>
                            <i style="--efficiency: {{ $scene['efficiency'] ?? 0 }}%; --scene-color: {{ $scene['color'] }}"></i>
                        </div>
                    </aside>

                    <section class="solar-live-sky solar-hero-scene-{{ $heroScene }}">
                        <div class="solar-hero-sky"></div>
                        <div class="solar-hero-sun-glow"></div>
                        <div class="solar-hero-sun-core"></div>
                        <span class="solar-hero-cloud solar-hero-cloud-a"></span>
                        <span class="solar-hero-cloud solar-hero-cloud-b"></span>
                        <span class="solar-hero-cloud solar-hero-cloud-c"></span>
                        <div class="solar-hero-particles" aria-hidden="true">
                            @for ($particle = 0; $particle < 10; $particle++)
                                <span></span>
                            @endfor
                        </div>
                        <div class="solar-hero-rain" aria-hidden="true">
                            @for ($drop = 0; $drop < 14; $drop++)
                                <span></span>
                            @endfor
                        </div>

                        <div class="solar-live-weather-pill">
                            <span style="background: {{ $scene['color'] }}; box-shadow: 0 0 12px {{ $scene['color'] }}"></span>
                            {{ $heroStatusLabel }}
                        </div>

                        <div class="solar-live-trend">
                            <p>Tendencia solar - 6h - 18h</p>
                            <div class="solar-live-curve">
                                @foreach ($heroTrendWeights as $point)
                                <span style="--point: {{ $point }}%"></span>
                                @endforeach
                            </div>
                        </div>
                    </section>
                </div>

                <footer class="solar-live-footer">
                    <span>Actualizacion - cada 30 s</span>
                    <span>Solar Dashboard v2.4 - 2026</span>
                </footer>
            </section>
        </div>

        <div class="sdash-header">
            {{-- Left: info --}}
            <div>
                <div class="sdash-header__kicker">
                    <span class="sdash-header__kicker-dot"></span>
                    Dashboard solar operativo
                </div>
                <h1 class="sdash-header__title">{{ $solarProject->name }}</h1>
                <p class="sdash-header__meta">
                    {{ $solarProject->location_name }}
                    &nbsp;·&nbsp; Actualizado {{ $solarProject->updated_at?->format('d/m/Y H:i') }}
                    @if ($numPanels) &nbsp;·&nbsp; {{ $numPanels }} paneles @endif
                    @if ($installedKwp) &nbsp;·&nbsp; {{ $installedLabel }} @endif
                </p>
                <p class="sdash-header__desc">{{ $solarProject->description ?: 'Sin descripcion del proyecto.' }}</p>
                <div class="sdash-badges">
                    <span class="sdash-badge {{ $badgeClass($coverageTone) }}">
                        Cobertura {{ $coverageLabel }}
                    </span>
                    <span class="sdash-badge {{ $badgeClass($riskTone) }}">
                        Riesgo {{ $riskTone === 'danger' ? 'Alto' : ($riskTone === 'warn' ? 'Moderado' : 'Bajo') }}
                    </span>
                    <span class="sdash-badge {{ $badgeClass(($dashboard['executiveSummary']['enabled'] ?? false) ? 'success' : 'warn') }}">
                        IA {{ ($dashboard['executiveSummary']['enabled'] ?? false) ? 'Activa' : 'Pendiente' }}
                    </span>
                    <span class="sdash-badge">
                        {{ $solarProject->start_date?->format('d/m/Y') }} — {{ $solarProject->end_date?->format('d/m/Y') }}
                    </span>
                </div>
            </div>

            {{-- Right: actions --}}
            <div class="sdash-actions">
                {{-- Primary: auto-calculate --}}
                <form method="POST" action="{{ route('solar-projects.calculate', $solarProject) }}">
                    @csrf
                    <button class="sdash-btn sdash-btn--primary" type="submit" title="Usa la mejor fuente disponible: Ambient → Estacion → NASA">
                        ⚡ Calcular
                    </button>
                </form>

                {{-- Explicit sources --}}
                <form method="POST" action="{{ route('solar-projects.calculate-ambient-weather', $solarProject) }}">
                    @csrf
                    <button class="sdash-btn sdash-btn--ghost" type="submit" title="Usar solo datos de Ambient Weather">
                        📡 Ambient
                    </button>
                </form>
                <form method="POST" action="{{ route('solar-projects.calculate-weather-station', $solarProject) }}">
                    @csrf
                    <button class="sdash-btn sdash-btn--ghost" type="submit" title="Usar solo datos del centro meteorologico">
                        🌡 Estacion
                    </button>
                </form>
                <form method="POST" action="{{ route('solar-projects.calculate-nasa', $solarProject) }}">
                    @csrf
                    <button class="sdash-btn sdash-btn--ghost" type="submit" title="Forzar calculo solo con NASA POWER">
                        ☄️ Calcular NASA
                    </button>
                </form>

                <div class="sdash-btn--divider"></div>

                <a href="{{ route('solar-projects.edit', $solarProject) }}" class="sdash-btn sdash-btn--ghost">✏️ Editar</a>

                <form method="POST" action="{{ route('solar-projects.destroy', $solarProject) }}" onsubmit="return confirm('¿Eliminar este proyecto?');">
                    @csrf @method('DELETE')
                    <button class="sdash-btn sdash-btn--danger" type="submit">🗑</button>
                </form>

                <a href="{{ route('solar-projects.index') }}" class="sdash-btn sdash-btn--ghost">← Volver</a>
            </div>
        </div>

        {{-- Condition indicator strip --}}
        <div class="sdash-condition">
            <div class="sdash-condition__summary">
                <div class="sdash-condition__orb sdash-condition__orb--{{ $heroScene }}">
                    {{ $scene['icon'] }}
                </div>
                <div class="sdash-condition__info">
                    <p class="sdash-condition__status">{{ $scene['label'] }}</p>
                    <p class="sdash-condition__detail">
                        {{ $scene['status'] }}
                        @if ($avgRadiation !== null)
                            &nbsp;·&nbsp; {{ number_format($avgRadiation, 0, ',', '.') }} W/m²
                            <span style="font-size:.68rem;color:var(--solar-text-muted);margin-left:.35rem;">
                                ({{ $sourceBadge['label'] }})
                            </span>
                        @else
                            &nbsp;·&nbsp; Sin lecturas de radiacion
                        @endif
                        &nbsp;·&nbsp; Eficiencia estimada: {{ $scene['efficiency'] }}%
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- ── 2. KPI Strip ────────────────────────────────────── --}}
    <div class="sdash-card">
        <div class="sdash-kpis">
            {{-- Cobertura --}}
            <div class="sdash-kpi">
                <span class="sdash-kpi__icon">⚡</span>
                <span class="sdash-kpi__value sdash-kpi__value--{{ $coverageTone }}">{{ $coverageLabel }}</span>
                <span class="sdash-kpi__label">Cobertura solar</span>
                <span class="sdash-kpi__sub">
                    {{ $coverage !== null && $coverage >= 100 ? 'Autoconsumo total' : ($coverage !== null ? 'Dependencia de red' : 'Sin calculo') }}
                </span>
            </div>

            {{-- Generacion anual --}}
            <div class="sdash-kpi">
                <span class="sdash-kpi__icon">🔆</span>
                <span class="sdash-kpi__value sdash-kpi__value--accent">
                    {{ $annualGen !== null ? $fmt($annualGen / 1000, 1) : '—' }}
                </span>
                <span class="sdash-kpi__label">Generacion anual (MWh)</span>
                <span class="sdash-kpi__sub">
                    @if ($monthlyGen !== null) {{ $fmt($monthlyGen) }} kWh/mes @else Sin datos @endif
                </span>
            </div>

            {{-- Ahorro anual --}}
            <div class="sdash-kpi">
                <span class="sdash-kpi__icon">💰</span>
                <span class="sdash-kpi__value sdash-kpi__value--success">
                    {{ $annualSavings !== null ? '$ ' . number_format($annualSavings / 1e6, 1, ',', '.') . 'M' : '—' }}
                </span>
                <span class="sdash-kpi__label">Ahorro anual (COP)</span>
                <span class="sdash-kpi__sub">
                    @if ($tariffValue !== null) Tarifa: ${{ number_format((float)$tariffValue, 0, ',', '.') }}/kWh @endif
                </span>
            </div>

            {{-- Capacidad --}}
            <div class="sdash-kpi">
                <span class="sdash-kpi__icon">🔋</span>
                <span class="sdash-kpi__value">{{ $installedLabel }}</span>
                <span class="sdash-kpi__label">Capacidad instalada</span>
                <span class="sdash-kpi__sub">{{ $usableAreaLabel }} utilizable</span>
            </div>

            {{-- Radiacion promedio --}}
            <div class="sdash-kpi">
                <span class="sdash-kpi__icon">☀️</span>
                <span class="sdash-kpi__value sdash-kpi__value--accent">
                    {{ $avgRadiation !== null ? number_format($avgRadiation, 0, ',', '.') : '—' }}
                </span>
                <span class="sdash-kpi__label">Radiacion promedio (W/m²)</span>
                <span class="sdash-kpi__sub">
                    Fuente: {{ $sourceBadge['label'] }}
                </span>
            </div>

            {{-- UV maximo --}}
            <div class="sdash-kpi">
                <span class="sdash-kpi__icon">🌡</span>
                <span class="sdash-kpi__value">
                    {{ $latestUvIndex !== null ? $fmt($latestUvIndex, 1) : '—' }}
                </span>
                <span class="sdash-kpi__label">Indice UV max</span>
                <span class="sdash-kpi__sub">
                    @if ($latestUvIndex !== null)
                        {{ $latestUvIndex >= 8 ? 'Muy alto' : ($latestUvIndex >= 6 ? 'Alto' : ($latestUvIndex >= 3 ? 'Moderado' : 'Bajo')) }}
                    @else Sin datos @endif
                </span>
            </div>

            {{-- Recuperacion de inversion --}}
            <div class="sdash-kpi">
                <span class="sdash-kpi__icon">📈</span>
                <span class="sdash-kpi__value sdash-kpi__value--success">
                    {{ $paybackYears !== null ? $fmt($paybackYears, 1) . ' anos' : 'N/A' }}
                </span>
                <span class="sdash-kpi__label">Recuperacion de inversion</span>
                <span class="sdash-kpi__sub">
                    {{ $installationCost !== null ? 'Costo: ' . $fmtCop($installationCost) : 'Sin costo calculado' }}
                </span>
            </div>
        </div>
    </div>

    <div class="sdash-card" x-data="{ investmentScale: 'monthly' }">
        <div class="sdash-section-head">
            <div>
                <h2 class="sdash-section-head__title">Recuperacion de la inversion</h2>
                <p class="sdash-section-head__sub">Costo total de instalacion / Ahorro anual estimado</p>
            </div>
            <div class="sdash-scale-tabs">
                <button
                    type="button"
                    class="sdash-scale-tab"
                    :class="{ 'is-active': investmentScale === 'monthly' }"
                    @click="investmentScale = 'monthly'"
                >Mensual</button>
                <button
                    type="button"
                    class="sdash-scale-tab"
                    :class="{ 'is-active': investmentScale === 'annual' }"
                    @click="investmentScale = 'annual'"
                >Anual</button>
            </div>
        </div>
        <div class="sdash-kpis" style="padding-top:.5rem;">
            <div class="sdash-kpi">
                <span class="sdash-kpi__label">Capacidad instalada estimada</span>
                <span class="sdash-kpi__value">{{ $installedKwp !== null ? $fmt($installedKwp, 2) . ' kWp' : '—' }}</span>
                <span class="sdash-kpi__sub">Paneles estimados: {{ $numPanels ?? '—' }}</span>
            </div>
            <div class="sdash-kpi">
                <span class="sdash-kpi__label" x-text="investmentScale === 'monthly' ? 'Generacion mensual estimada' : 'Generacion anual estimada'"></span>
                <span class="sdash-kpi__value" x-text="investmentScale === 'monthly' ? '{{ $monthlyGen !== null ? $fmt($monthlyGen, 1) . ' kWh' : '—' }}' : '{{ $annualGen !== null ? $fmt($annualGen, 2) . ' kWh' : '—' }}'"></span>
                <span class="sdash-kpi__sub" x-text="investmentScale === 'monthly' ? 'Equivalente anual: {{ $annualGen !== null ? $fmt($annualGen, 2) . ' kWh' : '—' }}' : 'Equivalente mensual: {{ $monthlyGen !== null ? $fmt($monthlyGen, 1) . ' kWh' : '—' }}'"></span>
            </div>
            <div class="sdash-kpi">
                <span class="sdash-kpi__label">Cobertura estimada</span>
                <span class="sdash-kpi__value">{{ $coverageLabel }}</span>
                <span class="sdash-kpi__sub" x-text="investmentScale === 'monthly' ? 'Balance mensual: {{ $monthlyBalanceEstimated !== null ? $fmt($monthlyBalanceEstimated, 1) . ' kWh' : '—' }}' : 'Balance anual: {{ $annualBalanceEstimated !== null ? $fmt($annualBalanceEstimated, 2) . ' kWh' : '—' }}'"></span>
            </div>
            <div class="sdash-kpi">
                <span class="sdash-kpi__label">Inversion estimada</span>
                <span class="sdash-kpi__value">{{ $investmentBaseCost !== null ? $fmtCop($investmentBaseCost) : '—' }}</span>
                <span class="sdash-kpi__sub">Base: 5.000.000 COP/kWp</span>
            </div>
            <div class="sdash-kpi">
                <span class="sdash-kpi__label" x-text="investmentScale === 'monthly' ? 'Ahorro mensual estimado' : 'Ahorro anual estimado'"></span>
                <span class="sdash-kpi__value" x-text="investmentScale === 'monthly' ? '{{ $monthlySavingsEstimated !== null ? $fmtCop($monthlySavingsEstimated) : '—' }}' : '{{ $annualSavings !== null ? $fmtCop($annualSavings) : '—' }}'"></span>
                <span class="sdash-kpi__sub" x-text="investmentScale === 'monthly' ? 'Equivalente anual: {{ $annualSavings !== null ? $fmtCop($annualSavings) : '—' }}' : 'Equivalente mensual: {{ $monthlySavingsEstimated !== null ? $fmtCop($monthlySavingsEstimated) : '—' }}'"></span>
            </div>
            <div class="sdash-kpi">
                <span class="sdash-kpi__label">Retorno de inversion</span>
                <span class="sdash-kpi__value sdash-kpi__value--success">{{ $paybackYears !== null ? $fmt($paybackYears, 2) . ' anos' : 'N/A' }}</span>
                <span class="sdash-kpi__sub">{{ $paybackStatusText }}</span>
            </div>
        </div>
        <p style="font-size:.78rem;color:var(--solar-text-muted);padding:0 1.5rem 1.25rem;">
            {{ $ambientContextText }}
        </p>
    </div>

    {{-- ── 3. Charts + Operational summary ─────────────────── --}}
    <div class="sdash-grid-2 sdash-grid-2--wide">

        {{-- Charts card --}}
        <div class="sdash-card">
            <div class="sdash-section-head">
                <div>
                    <h2 class="sdash-section-head__title">Generacion vs Consumo</h2>
                    <p class="sdash-section-head__sub" x-text="scale?.chart?.rangeLabel ?? 'Periodo activo'"></p>
                </div>
                <div class="sdash-scale-tabs" data-dashboard-scale-selector>
                    @foreach (['daily' => 'Diario', 'monthly' => 'Mensual', 'annual' => 'Anual'] as $sk => $sl)
                        <button
                            type="button"
                            class="sdash-scale-tab {{ $sk === $activeScaleKey ? 'is-active' : '' }}"
                            data-scale-button="{{ $sk }}"
                            @click="activeScale = '{{ $sk }}'"
                        >{{ $sl }}</button>
                    @endforeach
                </div>
            </div>
            <div class="sdash-chart-wrap">
                <div class="sdash-chart-canvas sdash-chart-canvas--tall">
                    <canvas id="solar-executive-chart" role="img" aria-label="Grafica ejecutiva"></canvas>
                </div>
            </div>

            @if (($weatherStationStats['total'] ?? 0) > 0)
                <div class="sdash-divider"></div>
                <div class="sdash-section-head" style="border-bottom:none;padding-top:.875rem;padding-bottom:.5rem;">
                    <div>
                        <h2 class="sdash-section-head__title" style="font-size:.9rem;">Radiacion local — ultimas lecturas</h2>
                        <p class="sdash-section-head__sub">Centro meteorologico</p>
                    </div>
                </div>
                <div class="sdash-chart-wrap" style="padding-top:0">
                    <div class="sdash-chart-canvas">
                        <canvas id="weather-station-radiation-chart" role="img" aria-label="Radiacion local"></canvas>
                    </div>
                </div>
            @endif
        </div>

        {{-- Right column: operational + coverage --}}
        <div style="display:flex;flex-direction:column;gap:var(--sdash-gap);">

            {{-- Coverage donut --}}
            <div class="sdash-card">
                <div class="sdash-section-head">
                    <h2 class="sdash-section-head__title">Cobertura solar</h2>
                    <span class="sdash-badge {{ $badgeClass($coverageTone) }}">{{ $coverageLabel }}</span>
                </div>
                <div class="sdash-chart-wrap">
                    <div class="sdash-chart-canvas">
                        <canvas id="solar-coverage-chart" role="img" aria-label="Cobertura"></canvas>
                    </div>
                </div>
                <div class="sdash-stat-row">
                    <div class="sdash-stat-cell">
                        <div class="sdash-stat-cell__label">Consumo mensual</div>
                        <div class="sdash-stat-cell__value">{{ $fmtKwh($solarProject->monthlyConsumption()) }}</div>
                    </div>
                    <div class="sdash-stat-cell">
                        <div class="sdash-stat-cell__label">Generacion mensual</div>
                        <div class="sdash-stat-cell__value">{{ $monthlyGen !== null ? $fmtKwh($monthlyGen) : '—' }}</div>
                    </div>
                </div>
            </div>

            {{-- Operational state --}}
            <div class="sdash-card">
                <div class="sdash-section-head">
                    <h2 class="sdash-section-head__title">Estado operativo</h2>
                    <span class="sdash-badge" x-text="scale?.rangeLabel ?? 'Periodo'"></span>
                </div>
                <div class="sdash-rec-grid" style="grid-template-columns:1fr;padding:.875rem 1.25rem;">
                    <div class="sdash-rec">
                        <p class="sdash-rec__label">Recomendacion principal</p>
                        <p class="sdash-rec__text" x-text="scale?.primaryRecommendation ?? '{{ $activeScale['primaryRecommendation'] ?? 'Sin recomendacion.' }}'"></p>
                    </div>
                    <div class="sdash-rec">
                        <p class="sdash-rec__label">Estado del sistema</p>
                        <p class="sdash-rec__text" x-text="scale?.stateTitle ?? '{{ $activeScale['stateTitle'] ?? 'Estado pendiente.' }}'"></p>
                    </div>
                    <div class="sdash-rec" style="border-left:3px solid {{ $riskTone === 'danger' ? 'var(--solar-danger)' : ($riskTone === 'warn' ? 'var(--solar-warning)' : 'var(--solar-success)') }};">
                        <p class="sdash-rec__label">Riesgo operativo</p>
                        <p class="sdash-rec__text" x-text="scale?.risk ?? '{{ $activeScale['risk'] ?? 'Sin riesgo critico.' }}'"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── 4. KPIs por escala temporal ──────────────────────── --}}
    <div class="sdash-card">
        <div class="sdash-section-head">
            <div>
                <h2 class="sdash-section-head__title">Indicadores de rendimiento</h2>
                <p class="sdash-section-head__sub" x-text="scale?.summary ?? '{{ $activeScale['summary'] ?? '' }}'"></p>
            </div>
        </div>
        <div class="sdash-kpis" data-scale-kpis>
            <template x-for="kpi in (scale?.kpis ?? [])" :key="kpi.label">
                <div class="sdash-kpi">
                    <span class="sdash-kpi__value" x-text="kpi.value ?? '—'"></span>
                    <span class="sdash-kpi__label" x-text="kpi.label"></span>
                    <span class="sdash-kpi__sub" x-text="kpi.description ?? ''"></span>
                </div>
            </template>
            @if (empty($activeScale['kpis']))
                <p class="sdash-empty" style="grid-column:1/-1;">Sin indicadores disponibles. Ejecuta un calculo primero.</p>
            @endif
        </div>
    </div>

    <script>
        window.solarAiRecommendations = window.solarAiRecommendations || function solarAiRecommendations(config) {
            if (config instanceof HTMLElement) {
                const configElement = document.getElementById(config.dataset.aiConfig || '');
                const parsedConfig = configElement ? JSON.parse(configElement.textContent || '{}') : {};

                config = {
                    ...parsedConfig,
                    endpoint: config.dataset.aiEndpoint || parsedConfig.endpoint,
                    csrfToken: config.dataset.aiCsrf || parsedConfig.csrfToken,
                    initialFocus: config.dataset.aiFocus || parsedConfig.initialFocus || 'savings',
                    provider: config.dataset.aiProvider || parsedConfig.provider || 'IA',
                    generated: config.dataset.aiGenerated === '1' || parsedConfig.generated === true,
                };
            }

            if (typeof config === 'string') {
                const configElement = document.getElementById(config);
                config = configElement ? JSON.parse(configElement.textContent || '{}') : {};
            }

            return {
                endpoint: config.endpoint,
                csrfToken: config.csrfToken,
                focus: config.initialFocus || 'savings',
                focusOptions: config.focusOptions || {},
                provider: config.provider || 'IA',
                state: 'idle',
                errorMessage: '',
                messages: [],
                abortController: null,
                streamTimer: null,
                get isBusy() {
                    return ['loading', 'streaming'].includes(this.state);
                },
                get stateLabel() {
                    return {
                        idle: 'Listo',
                        loading: 'Analizando datos',
                        streaming: 'Escribiendo',
                        done: 'Completado',
                        error: 'Revisar error',
                    }[this.state] || 'Listo';
                },
                get composerHint() {
                    const selectedOption = document.getElementById('ai-focus')?.selectedOptions?.[0]?.textContent;
                    const label = this.focusOptions[this.focus] || selectedOption || 'el enfoque seleccionado';
                    return `Generar enfoque ${label}. Se cancela cualquier solicitud activa antes de iniciar otra.`;
                },
                init() {
                    if (config.generated && config.initialMessage) {
                        this.messages.push({
                            id: this.createId(),
                            role: 'assistant',
                            content: config.initialMessage,
                            streaming: false,
                        });
                        this.state = 'done';
                    }

                    if (config.generated && config.initialError) {
                        this.errorMessage = config.initialError;
                        this.state = 'error';
                    }
                },
                createId() {
                    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
                },
                scrollToBottom() {
                    this.$nextTick(() => {
                        if (this.$refs.chat) {
                            this.$refs.chat.scrollTop = this.$refs.chat.scrollHeight;
                        }
                    });
                },
                clearHistory() {
                    if (this.isBusy) {
                        this.stop();
                    }
                    this.messages = [];
                    this.errorMessage = '';
                    this.state = 'idle';
                },
                stop() {
                    if (this.abortController) {
                        this.abortController.abort();
                    }
                    if (this.streamTimer) {
                        clearInterval(this.streamTimer);
                        this.streamTimer = null;
                    }
                    const streamingMessage = this.messages.find((message) => message.streaming);
                    if (streamingMessage) {
                        streamingMessage.streaming = false;
                    }
                    this.state = this.messages.length > 0 ? 'done' : 'idle';
                },
                async generate(isRegeneration = false) {
                    if (this.isBusy) {
                        this.stop();
                    }

                    this.errorMessage = '';
                    this.state = 'loading';
                    this.abortController = new AbortController();

                    this.messages.push({
                        id: this.createId(),
                        role: 'user',
                        content: `${isRegeneration ? 'Regenerar' : 'Generar'} enfoque ${this.focusOptions[this.focus] || document.getElementById('ai-focus')?.selectedOptions?.[0]?.textContent || this.focus}`,
                        streaming: false,
                    });
                    this.scrollToBottom();

                    try {
                        const response = await fetch(this.endpoint, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({ ai_focus: this.focus }),
                            signal: this.abortController.signal,
                        });

                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            throw new Error(payload.message || 'No fue posible generar la recomendacion.');
                        }

                        this.provider = String(payload.source || this.provider).toUpperCase();
                        this.typewrite(this.buildAssistantText(payload));
                    } catch (error) {
                        if (error.name === 'AbortError') {
                            return;
                        }

                        this.errorMessage = error.message || 'Ocurrio un error inesperado al generar la recomendacion.';
                        this.state = 'error';
                    } finally {
                        this.abortController = null;
                    }
                },
                buildAssistantText(payload) {
                    const parts = [];

                    if (payload.focus_label) {
                        parts.push(`Enfoque: ${payload.focus_label}`);
                    }

                    if (payload.message) {
                        parts.push(payload.message);
                    }

                    if (Array.isArray(payload.alerts) && payload.alerts.length > 0) {
                        parts.push(`Alertas a vigilar:\n${payload.alerts.slice(0, 3).map((alert) => `- ${alert}`).join('\n')}`);
                    }

                    if (payload.error) {
                        parts.push(`Nota de disponibilidad: ${payload.error}`);
                    }

                    return parts.filter(Boolean).join('\n\n').trim() || 'La IA no devolvio contenido para mostrar.';
                },
                typewrite(fullText) {
                    fullText = String(fullText || '').trim() || 'No se genero contenido utilizable. Reintenta o cambia el enfoque.';

                    const messageIndex = this.messages.push({
                        id: this.createId(),
                        role: 'assistant',
                        content: '',
                        streaming: true,
                    }) - 1;
                    let index = 0;
                    const step = Math.max(4, Math.ceil(fullText.length / 120));

                    this.state = 'streaming';
                    this.scrollToBottom();

                    this.streamTimer = setInterval(() => {
                        index = Math.min(fullText.length, index + step);
                        this.messages[messageIndex].content = fullText.slice(0, index);
                        this.scrollToBottom();

                        if (index >= fullText.length) {
                            clearInterval(this.streamTimer);
                            this.streamTimer = null;
                            this.messages[messageIndex].streaming = false;
                            this.state = 'done';
                        }
                    }, 26);
                },
            };
        };

        window.solarAiPrediction = window.solarAiPrediction || function solarAiPrediction(config) {
            if (config instanceof HTMLElement) {
                const configElement = document.getElementById(config.dataset.predictionConfig || '');
                const parsedConfig = configElement ? JSON.parse(configElement.textContent || '{}') : {};

                config = {
                    ...parsedConfig,
                    endpoint: config.dataset.predictionEndpoint || parsedConfig.endpoint,
                    csrfToken: config.dataset.predictionCsrf || parsedConfig.csrfToken,
                    provider: config.dataset.predictionProvider || parsedConfig.provider || 'CLAUDE',
                };
            }

            return {
                endpoint: config.endpoint,
                csrfToken: config.csrfToken,
                provider: config.provider || 'CLAUDE',
                state: 'idle',
                errorMessage: '',
                result: null,
                abortController: null,
                get isBusy() {
                    return this.state === 'loading';
                },
                get stateLabel() {
                    return {
                        idle: 'Pendiente',
                        loading: 'Generando con IA',
                        done: 'Completado',
                        error: 'Revisar error',
                    }[this.state] || 'Pendiente';
                },
                async generate() {
                    if (this.abortController) {
                        this.abortController.abort();
                    }

                    this.errorMessage = '';
                    this.state = 'loading';
                    this.abortController = new AbortController();

                    try {
                        const response = await fetch(this.endpoint, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({ horizon: 'next_week' }),
                            signal: this.abortController.signal,
                        });

                        const payload = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            throw new Error(payload.message || 'No fue posible generar la prediccion IA.');
                        }

                        this.provider = String(payload.source || this.provider).toUpperCase();
                        this.result = payload;
                        this.state = 'done';
                    } catch (error) {
                        if (error.name === 'AbortError') {
                            return;
                        }

                        this.errorMessage = error.message || 'Ocurrio un error inesperado al generar la prediccion.';
                        this.state = 'error';
                    } finally {
                        this.abortController = null;
                    }
                },
            };
        };
    </script>

    <script type="application/json" id="solar-ai-config-{{ $solarProject->id }}">@json($solarAiConfig)</script>
    <script type="application/json" id="solar-ai-prediction-config-{{ $solarProject->id }}">@json($solarAiPredictionConfig)</script>

    {{-- ── 5. IA — Recomendaciones ──────────────────────────── --}}
    <div
        class="sdash-card sdash-ai-panel"
        data-ai-config="solar-ai-config-{{ $solarProject->id }}"
        data-ai-endpoint="{{ route('solar-projects.ai-recommendations', ['solarProject' => $solarProject->id]) }}"
        data-ai-csrf="{{ csrf_token() }}"
        data-ai-focus="{{ $aiFocus ?: 'savings' }}"
        data-ai-provider="{{ strtoupper((string) config('services.openai_recommendations.provider', 'openai')) }}"
        data-ai-generated="{{ $generateAiRecommendations ? '1' : '0' }}"
        x-data="solarAiRecommendations($el)"
        x-init="init()"
    >
        <div class="sdash-section-head">
            <div>
                <h2 class="sdash-section-head__title">Recomendaciones con IA</h2>
                <p class="sdash-section-head__sub">Chat operativo con recomendaciones basadas en datos del proyecto.</p>
            </div>
            <div class="sdash-ai-meta">
                <span class="sdash-badge" x-text="`Fuente ${provider}`"></span>
                <span class="sdash-badge sdash-badge--warn" x-text="`Enfoque ${focusOptions[focus] ?? 'General'}`"></span>
                <span class="sdash-badge" x-text="stateLabel"></span>
            </div>
        </div>

        <div class="sdash-ai-shell">
            <div class="sdash-ai-toolbar">
                <div style="color:var(--solar-text-muted);font-size:.82rem;line-height:1.45;">
                    Prioriza diagnostico, accion concreta e impacto esperado. No recarga la pagina.
                </div>
                <button
                    type="button"
                    class="sdash-btn sdash-btn--ghost"
                    x-show="messages.length > 0"
                    @click="clearHistory()"
                >
                    Limpiar historial
                </button>
            </div>

            <div
                class="sdash-ai-chat"
                x-ref="chat"
                aria-live="polite"
                aria-relevant="additions text"
            >
                <template x-if="messages.length === 0 && state === 'idle'">
                    <p class="sdash-ai-empty">
                        Selecciona un enfoque y genera una recomendacion. El resultado aparecera aqui como conversacion local.
                    </p>
                </template>

                <template x-for="message in messages" :key="message.id">
                    <article
                        class="sdash-ai-message"
                        :class="message.role === 'user' ? 'sdash-ai-message--user' : 'sdash-ai-message--assistant'"
                    >
                        <span class="sdash-ai-message__label" x-text="message.role === 'user' ? 'Usuario' : 'Asistente IA'"></span>
                        <div class="sdash-ai-bubble">
                            <span x-text="message.content"></span><span x-show="message.streaming" class="sdash-ai-cursor">|</span>
                        </div>
                    </article>
                </template>

                <template x-if="state === 'loading'">
                    <div class="sdash-ai-skeleton" aria-label="Generando recomendacion">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </template>
            </div>

            <template x-if="errorMessage">
                <div class="sdash-ai-error" role="alert">
                    <span x-text="errorMessage"></span>
                    <button type="button" class="sdash-btn sdash-btn--danger" @click="generate(true)">Reintentar</button>
                </div>
            </template>

            <div class="sdash-ai-composer">
                <label class="sr-only" for="ai-focus">Enfoque de recomendacion</label>
                <select
                    id="ai-focus"
                    x-model="focus"
                    class="sdash-ai-select"
                    :disabled="isBusy"
                    @keydown.enter.prevent="generate(false)"
                >
                    @foreach ($aiFocusOptions as $focusKey => $focusLabel)
                        <option value="{{ $focusKey }}">{{ $focusLabel }}</option>
                    @endforeach
                </select>

                <div style="color:var(--solar-text-muted);font-size:.78rem;line-height:1.4;" x-text="composerHint"></div>

                <button
                    type="button"
                    class="sdash-btn sdash-btn--ghost"
                    x-show="isBusy"
                    @click="stop()"
                >
                    Detener generacion
                </button>

                <button
                    type="button"
                    class="sdash-btn sdash-btn--primary"
                    :disabled="state === 'loading'"
                    @click="generate(messages.length > 0)"
                    x-text="messages.length > 0 ? 'Regenerar' : 'Generar IA'"
                ></button>
            </div>
        </div>
    </div>

    {{-- ── 6. Predicciones ──────────────────────────────────── --}}
    <div
        class="sdash-card sdash-prediction-panel"
        data-prediction-config="solar-ai-prediction-config-{{ $solarProject->id }}"
        data-prediction-endpoint="{{ route('solar-projects.ai-prediction', ['solarProject' => $solarProject->id]) }}"
        data-prediction-csrf="{{ csrf_token() }}"
        data-prediction-provider="CLAUDE"
        x-data="solarAiPrediction($el)"
    >
        <div class="sdash-section-head sdash-prediction-head">
            <div>
                <h2 class="sdash-section-head__title">Prediccion proxima semana</h2>
                <p class="sdash-section-head__sub">
                    Basado en {{ $futurePredictions['data_window']['sample_count'] ?? 0 }} registros de los ultimos
                    {{ $futurePredictions['data_window']['days'] ?? 0 }} dias · {{ $futurePredictions['data_window']['source'] ?? 'historico disponible' }}
                </p>
            </div>
            <div class="sdash-prediction-actions">
                <div class="sdash-ai-meta">
                    <span class="sdash-badge" x-text="`Fuente ${provider}`"></span>
                    <span class="sdash-badge" x-text="stateLabel"></span>
                </div>
                <button
                    type="button"
                    class="sdash-btn sdash-btn--primary"
                    :disabled="isBusy"
                    @click="generate()"
                    x-text="result ? 'Regenerar prediccion IA' : 'Generar prediccion IA'"
                ></button>
            </div>
        </div>

        <div class="sdash-prediction-body">
            <template x-if="errorMessage">
                <div class="sdash-ai-error" role="alert">
                    <span x-text="errorMessage"></span>
                </div>
            </template>
            <template x-if="state === 'loading'">
                <div class="sdash-ai-skeleton" aria-label="Generando prediccion IA">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </template>
            <template x-if="result">
                <article class="sdash-prediction-result">
                    <header class="sdash-prediction-foot">
                        <p class="sdash-prediction-title" x-text="result.title || 'Prediccion IA proxima semana'"></p>
                        <span class="sdash-badge sdash-badge--warn" x-text="`Confianza ${result.confidence || 'media'}`"></span>
                    </header>

                    <div class="sdash-prediction-grid">
                        <section class="sdash-prediction-section sdash-prediction-section--wide">
                            <p class="sdash-prediction-label">Lectura operacional</p>
                            <p class="sdash-prediction-text" x-text="result.prediction"></p>
                        </section>
                        <section class="sdash-prediction-section">
                            <p class="sdash-prediction-label">Temperatura</p>
                            <p class="sdash-prediction-text" x-text="result.temperature_outlook"></p>
                        </section>
                        <section class="sdash-prediction-section">
                            <p class="sdash-prediction-label">Ventana solar</p>
                            <p class="sdash-prediction-text" x-text="result.solar_window"></p>
                        </section>
                        <section class="sdash-prediction-section sdash-prediction-section--wide" x-show="Array.isArray(result.actions) && result.actions.length > 0">
                            <p class="sdash-prediction-label">Acciones recomendadas</p>
                            <ul class="sdash-prediction-actions-list">
                                <template x-for="action in result.actions" :key="action">
                                    <li x-text="action"></li>
                                </template>
                            </ul>
                        </section>
                    </div>

                    <footer class="sdash-prediction-foot">
                        <p class="sdash-prediction-note">
                            Base enviada a Claude: temperatura 7 dias
                            {{ isset($futurePredictions['temperature']['last_7_avg_c']) ? number_format((float) $futurePredictions['temperature']['last_7_avg_c'], 2, ',', '.') . ' °C' : 'N/D' }},
                            semana previa
                            {{ isset($futurePredictions['temperature']['previous_7_avg_c']) ? number_format((float) $futurePredictions['temperature']['previous_7_avg_c'], 2, ',', '.') . ' °C' : 'N/D' }},
                            ventana historica
                            {{ isset($futurePredictions['radiation_window']['start_hour'], $futurePredictions['radiation_window']['end_hour']) ? sprintf('%02d:00-%02d:59', (int) $futurePredictions['radiation_window']['start_hour'], (int) $futurePredictions['radiation_window']['end_hour']) : 'N/D' }}.
                        </p>
                        <template x-if="result.error">
                            <p class="sdash-prediction-note" x-text="result.error"></p>
                        </template>
                    </footer>
                </article>
            </template>
            <template x-if="!result && state !== 'loading'">
                <div class="sdash-prediction-result">
                    <p class="sdash-prediction-title">Prediccion IA pendiente</p>
                    <p class="sdash-prediction-note">
                        Base lista para Claude: temperatura 7 dias
                        {{ isset($futurePredictions['temperature']['last_7_avg_c']) ? number_format((float) $futurePredictions['temperature']['last_7_avg_c'], 2, ',', '.') . ' °C' : 'N/D' }},
                        semana previa
                        {{ isset($futurePredictions['temperature']['previous_7_avg_c']) ? number_format((float) $futurePredictions['temperature']['previous_7_avg_c'], 2, ',', '.') . ' °C' : 'N/D' }},
                        ventana historica
                        {{ isset($futurePredictions['radiation_window']['start_hour'], $futurePredictions['radiation_window']['end_hour']) ? sprintf('%02d:00-%02d:59', (int) $futurePredictions['radiation_window']['start_hour'], (int) $futurePredictions['radiation_window']['end_hour']) : 'N/D' }}.
                    </p>
                </div>
            </template>
        </div>
    </div>

    {{-- ── 7. Explorador de datos ───────────────────────────── --}}
    <div class="sdash-card">
        <div class="sdash-section-head">
            <div>
                <h2 class="sdash-section-head__title">Explorador de lecturas</h2>
                <p class="sdash-section-head__sub">Fuente de analisis: {{ $analysisSourceLabel }} · {{ count($tableRows) }} lecturas disponibles</p>
            </div>
            <button type="button" class="sdash-btn sdash-btn--ghost" @click="showModal = true">
                📋 Ver tabla completa
            </button>
        </div>
        @if (count($tableRows) === 0)
            <p class="sdash-empty">Sin lecturas del centro meteorologico registradas para este proyecto.</p>
        @else
            <div style="overflow-x:auto;max-height:220px;">
                <table class="sdash-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Fuente</th>
                            <th>Radiacion</th>
                            <th>Temp °C</th>
                            <th>Humedad %</th>
                            <th>Lluvia (mm)</th>
                            <th>Viento (m/s)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_slice($tableRows, 0, 5) as $row)
                            <tr>
                                <td>{{ $row['fecha'] }}</td>
                                <td>{{ $row['fuente'] ?? 'N/A' }}</td>
                                <td>{{ $row['radiacion'] !== null ? $row['radiacion'] . ' W/m²' : 'N/A' }}</td>
                                <td>{{ $row['temperature'] ?? 'N/A' }}</td>
                                <td>{{ $row['humidity'] ?? 'N/A' }}</td>
                                <td>{{ $row['rain'] ?? 'N/A' }}</td>
                                <td>{{ $row['wind'] ?? 'N/A' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($tableRows) > 5)
                <p style="font-size:.74rem;color:var(--solar-text-muted);padding:.5rem 1.25rem .75rem;border-top:1px solid var(--solar-border);">
                    Mostrando 5 de {{ count($tableRows) }} registros. Abre la tabla completa para filtrar y explorar.
                </p>
            @endif
        @endif
    </div>

    {{-- ── Modal: tabla completa ───────────────────────────── --}}
    <div
        x-show="showModal"
        x-cloak
        class="sdash-modal-overlay"
        @keydown.escape.window="showModal = false"
        @click.self="showModal = false"
    >
        <div class="sdash-modal" @click.stop>
            <div class="sdash-modal__head">
                <div>
                    <h3 class="sdash-modal__title">Lecturas usadas en el analisis ({{ $analysisSourceLabel }})</h3>
                    <p style="font-size:.75rem;color:var(--solar-text-muted);margin:.15rem 0 0;">
                        {{ count($tableRows) }} registros
                    </p>
                </div>
                <div style="display:flex;align-items:center;gap:.5rem;">
                    <button type="button" class="sdash-btn sdash-btn--ghost" @click="showModal = false">✕ Cerrar</button>
                </div>
            </div>
            <div class="sdash-modal__body">
                @if (count($tableRows) === 0)
                    <p class="sdash-empty">Sin datos disponibles.</p>
                @else
                    <table class="sdash-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Fuente</th>
                                <th>Radiacion (W/m²)</th>
                                <th>Temp (°C)</th>
                                <th>Humedad (%)</th>
                                <th>Lluvia (mm)</th>
                                <th>Viento (m/s)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tableRows as $row)
                                <tr>
                                    <td>{{ $row['fecha'] }}</td>
                                    <td>{{ $row['fuente'] ?? 'N/A' }}</td>
                                    <td>{{ $row['radiacion'] ?? 'N/A' }}</td>
                                    <td>{{ $row['temperature'] ?? 'N/A' }}</td>
                                    <td>{{ $row['humidity'] ?? 'N/A' }}</td>
                                    <td>{{ $row['rain'] ?? 'N/A' }}</td>
                                    <td>{{ $row['wind'] ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Script data for charts ──────────────────────────── --}}
    <script type="application/json" id="solar-timescale-chart-data">@json($timeScales)</script>
    <script type="application/json" id="weather-station-chart-data">@json($weatherStationChartData ?? ['labels' => [], 'radiation' => []])</script>

</div>
</x-layouts::app>
