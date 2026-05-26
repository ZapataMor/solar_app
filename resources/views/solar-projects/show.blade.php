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
    $weatherStationStats    = $weatherStationStats ?? [];
    $recentWeatherStationReadings = $recentWeatherStationReadings ?? collect();

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
        'high'   => ['label' => 'Radiacion excelente',  'status' => 'Despejado',            'color' => 'var(--solar-success)',  'icon' => '☀️',  'efficiency' => 94],
        'medium' => ['label' => 'Radiacion moderada',   'status' => 'Parcialmente nublado', 'color' => 'var(--solar-warning)', 'icon' => '⛅', 'efficiency' => 58],
        'low'    => ['label' => 'Radiacion baja',        'status' => 'Nublado',              'color' => 'var(--solar-danger)',   'icon' => '☁️',  'efficiency' => 24],
    ];
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

    $installedLabel   = $installedKwp ? $fmt($installedKwp) . ' kWp' : 'Pendiente';
    $usableAreaLabel  = $calculationResult?->usable_area_m2
        ? $fmt($calculationResult->usable_area_m2) . ' m²'
        : ($technicalParameter?->available_area_m2 ? $fmt($technicalParameter->available_area_m2) . ' m²' : 'Pendiente');

    $tableRows = $recentWeatherStationReadings->sortByDesc('measured_at')->values()->map(fn ($r) => [
        'fecha'       => $r->measured_at?->format('Y-m-d H:i') ?? 'N/A',
        'radiacion'   => $r->radiationValue() !== null ? number_format((float) $r->radiationValue(), 2, '.', '') : null,
        'uva'         => $r->uva !== null ? number_format((float) $r->uva, 3, '.', '') : null,
        'uvb'         => $r->uvb !== null ? number_format((float) $r->uvb, 3, '.', '') : null,
        'iuv'         => $r->uv_index !== null ? number_format((float) $r->uv_index, 3, '.', '') : null,
        'temperature' => $r->temperature !== null ? number_format((float) $r->temperature, 2, '.', '') : null,
        'humidity'    => $r->humidity !== null ? number_format((float) $r->humidity, 2, '.', '') : null,
        'co2'         => $r->co2,
        'pm25'        => $r->pm25 !== null ? number_format((float) $r->pm25, 2, '.', '') : null,
        'pm10'        => $r->pm10 !== null ? number_format((float) $r->pm10, 2, '.', '') : null,
    ])->all();

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
.sdash-hero-stage .solar-live-panel,
.sdash-hero-stage .solar-live-sky {
    min-height: clamp(320px, 40vh, 460px);
}
.sdash-hero-stage .solar-live-curve span {
    background: linear-gradient(180deg, color-mix(in srgb, var(--chart-color, #ff9f0a) 90%, white 10%), color-mix(in srgb, var(--chart-color, #ff9f0a) 12%, transparent));
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

<div
    class="sdash"
    x-data="{
        showModal: false,
        filter: '',
        activeScale: '{{ $activeScaleKey }}',
        scales: @json($timeScales['scales'] ?? []),
        get scale() { return this.scales[this.activeScale] ?? null; },
    }"
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
                <form method="POST" action="{{ route('solar-projects.fetch-weather-data', $solarProject) }}">
                    @csrf
                    <button class="sdash-btn sdash-btn--ghost" type="submit" title="Descargar datos NASA POWER">
                        🛰 NASA
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
        </div>
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

    {{-- ── 5. IA — Recomendaciones ──────────────────────────── --}}
    <div class="sdash-card">
        <div class="sdash-section-head">
            <div>
                <h2 class="sdash-section-head__title">Recomendaciones con IA</h2>
                <p class="sdash-section-head__sub">Enfoque: {{ $aiFocusOptions[$aiFocus] ?? 'General' }}</p>
            </div>
            <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                <span class="sdash-badge">
                    Fuente {{ strtoupper((string) ($dashboard['executiveSummary']['source'] ?? 'ia')) }}
                </span>
                <form method="GET" action="{{ route('solar-projects.show', $solarProject) }}" style="display:flex;align-items:center;gap:.4rem;flex-wrap:wrap;">
                    <input type="hidden" name="generate_ai" value="1" />
                    <select name="ai_focus" class="sdash-ai-select">
                        @foreach ($aiFocusOptions as $fk => $fl)
                            <option value="{{ $fk }}" @selected($aiFocus === $fk)>{{ $fl }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="sdash-btn sdash-btn--primary" style="font-size:.75rem;padding:.35rem .8rem;">
                        {{ $generateAiRecommendations ? '↺ Regenerar' : '✦ Generar IA' }}
                    </button>
                </form>
            </div>
        </div>

        <div class="sdash-rec-grid" style="padding-top:1rem;">
            <div class="sdash-rec sdash-rec--ai">
                <p class="sdash-rec__label">Resumen ejecutivo</p>
                <p class="sdash-rec__text">{{ $dashboard['executiveSummary']['text'] ?? 'Sin resumen disponible. Genera recomendaciones con IA.' }}</p>
            </div>
            <div class="sdash-rec sdash-rec--ai">
                <p class="sdash-rec__label">Recomendacion inteligente del dia</p>
                <p class="sdash-rec__text">{{ $dashboard['executiveSummary']['dailyRecommendation'] ?? 'Sin recomendacion disponible.' }}</p>
                @if (!empty($dashboard['executiveSummary']['error']))
                    <p style="font-size:.72rem;color:var(--solar-danger);margin-top:.5rem;">
                        {{ $dashboard['executiveSummary']['error'] }}
                    </p>
                @endif
            </div>
            @if ($aiSelectedPackItem)
                <div class="sdash-rec sdash-rec--ai" style="border-color:color-mix(in srgb,var(--solar-sun) 50%,transparent);background:var(--solar-warning-bg);">
                    <p class="sdash-rec__label">{{ $aiSelectedPackItem['title'] ?? ($aiFocusOptions[$aiFocus] ?? 'Enfoque') }}</p>
                    <p class="sdash-rec__text">{{ $aiSelectedPackItem['message'] ?? 'Sin recomendacion para este enfoque.' }}</p>
                </div>
            @endif
        </div>
    </div>

    {{-- ── 6. Predicciones ──────────────────────────────────── --}}
    <div class="sdash-card">
        <div class="sdash-section-head">
            <h2 class="sdash-section-head__title">Prediccion proxima semana</h2>
            <span class="sdash-badge">Basado en historico</span>
        </div>
        <div class="sdash-rec-grid" style="padding-top:1rem;">
            <div class="sdash-rec">
                <p class="sdash-rec__label">Tendencia de temperatura</p>
                <p class="sdash-rec__text">{{ $futurePredictions['temperature']['message'] ?? 'Sin prediccion termica disponible.' }}</p>
                @if (isset($futurePredictions['temperature']['projected_next_week_c']))
                    <p style="font-size:.78rem;color:var(--solar-sun);margin-top:.5rem;">
                        Proyeccion: {{ number_format((float) $futurePredictions['temperature']['projected_next_week_c'], 2, ',', '.') }} °C
                        (Δ {{ number_format((float) ($futurePredictions['temperature']['delta_c'] ?? 0), 2, ',', '.') }} °C semanal)
                    </p>
                @endif
            </div>
            <div class="sdash-rec">
                <p class="sdash-rec__label">Ventana solar recomendada</p>
                <p class="sdash-rec__text">{{ $futurePredictions['radiation_window']['message'] ?? 'Sin ventana solar identificada.' }}</p>
            </div>
        </div>
    </div>

    {{-- ── 7. Explorador de datos ───────────────────────────── --}}
    <div class="sdash-card">
        <div class="sdash-section-head">
            <div>
                <h2 class="sdash-section-head__title">Explorador de lecturas</h2>
                <p class="sdash-section-head__sub">Registros del centro meteorologico · {{ count($tableRows) }} ultimas lecturas cargadas</p>
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
                            <th>Radiacion</th>
                            <th>Temp °C</th>
                            <th>Humedad %</th>
                            <th>IUV</th>
                            <th>UVA</th>
                            <th>UVB</th>
                            <th>CO₂</th>
                            <th>PM2.5</th>
                            <th>PM10</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (array_slice($tableRows, 0, 5) as $row)
                            <tr>
                                <td>{{ $row['fecha'] }}</td>
                                <td>{{ $row['radiacion'] !== null ? $row['radiacion'] . ' W/m²' : 'N/A' }}</td>
                                <td>{{ $row['temperature'] ?? 'N/A' }}</td>
                                <td>{{ $row['humidity'] ?? 'N/A' }}</td>
                                <td>{{ $row['iuv'] ?? 'N/A' }}</td>
                                <td>{{ $row['uva'] ?? 'N/A' }}</td>
                                <td>{{ $row['uvb'] ?? 'N/A' }}</td>
                                <td>{{ $row['co2'] ?? 'N/A' }}</td>
                                <td>{{ $row['pm25'] ?? 'N/A' }}</td>
                                <td>{{ $row['pm10'] ?? 'N/A' }}</td>
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
                    <h3 class="sdash-modal__title">Lecturas del centro meteorologico</h3>
                    <p style="font-size:.75rem;color:var(--solar-text-muted);margin:.15rem 0 0;">
                        {{ count($tableRows) }} registros · Filtra por fecha
                    </p>
                </div>
                <div style="display:flex;align-items:center;gap:.5rem;">
                    <input
                        type="text"
                        class="sdash-input"
                        placeholder="Filtrar por fecha…"
                        x-model="filter"
                    />
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
                                <th>Radiacion (W/m²)</th>
                                <th>Temp (°C)</th>
                                <th>Humedad (%)</th>
                                <th>IUV</th>
                                <th>UVA</th>
                                <th>UVB</th>
                                <th>CO₂</th>
                                <th>PM2.5</th>
                                <th>PM10</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template
                                x-for="row in {{ json_encode($tableRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}.filter(r => !filter || (r.fecha || '').toLowerCase().includes(filter.toLowerCase()))"
                                :key="row.fecha + '-' + (row.co2 ?? 'na')"
                            >
                                <tr>
                                    <td x-text="row.fecha"></td>
                                    <td x-text="row.radiacion ?? 'N/A'"></td>
                                    <td x-text="row.temperature ?? 'N/A'"></td>
                                    <td x-text="row.humidity ?? 'N/A'"></td>
                                    <td x-text="row.iuv ?? 'N/A'"></td>
                                    <td x-text="row.uva ?? 'N/A'"></td>
                                    <td x-text="row.uvb ?? 'N/A'"></td>
                                    <td x-text="row.co2 ?? 'N/A'"></td>
                                    <td x-text="row.pm25 ?? 'N/A'"></td>
                                    <td x-text="row.pm10 ?? 'N/A'"></td>
                                </tr>
            </template>
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
