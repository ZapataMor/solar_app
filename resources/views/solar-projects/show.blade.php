@php
    $technicalParameter = $solarProject->technicalParameter;
    $calculationResult = $solarProject->calculationResult;
    $timeScales = $timeScales ?? ['defaultScale' => 'monthly', 'scales' => [], 'activeScale' => null];
    $activeScaleKey = $timeScales['defaultScale'] ?? 'monthly';
    $activeScale = $timeScales['activeScale'] ?? ($timeScales['scales'][$activeScaleKey] ?? null);
    $weatherStationStats = $weatherStationStats ?? [];
    $recentWeatherStationReadings = $recentWeatherStationReadings ?? collect();
    $latestWeatherStationReading = $weatherStationStats['latest'] ?? null;
    $dashboard = $dashboard ?? ['executiveSummary' => ['enabled' => false]];
    $hasWeatherData = $solarProject->weather_data_count > 0;
    $hasWeatherStationData = ($weatherStationStats['total'] ?? 0) > 0;
    $hasTimeScaleCharts = collect($timeScales['scales'] ?? [])
        ->contains(fn ($scale) => ! empty($scale['chart']['labels'] ?? []));

    $formatNumber = fn ($value, int $decimals = 2) => number_format((float) $value, $decimals, ',', '.');
    $formatKwh = fn ($value) => $formatNumber($value).' kWh';
    $formatKwp = fn ($value) => $formatNumber($value).' kWp';
    $formatMoney = fn ($value) => '$ '.number_format((float) $value, 0, ',', '.').' COP';
    $formatCoordinate = fn ($value) => number_format((float) $value, 4, '.', '');
    $activeKpis = collect($activeScale['kpis'] ?? []);
    $generationKpi = $activeKpis->first(fn (array $kpi) => str_contains(strtolower($kpi['label'] ?? ''), 'generacion'));
    $savingsKpi = $activeKpis->first(fn (array $kpi) => str_contains(strtolower($kpi['label'] ?? ''), 'ahorro'));
    $coverageKpi = $activeKpis->first(fn (array $kpi) => str_contains(strtolower($kpi['label'] ?? ''), 'cobertura'));
    $alertTone = str_contains(strtolower($activeScale['risk'] ?? ''), 'crit')
        ? 'danger'
        : (filled($activeScale['risk'] ?? null) ? 'warn' : 'success');
    $alertLabel = match ($alertTone) {
        'danger' => 'Atencion requerida',
        'warn' => 'Revisar condiciones',
        default => 'Sin alerta critica',
    };

    $renderMetric = function (array $kpi) use ($formatKwh, $formatKwp, $formatMoney, $formatNumber) {
        $value = $kpi['value'] ?? null;

        return match ($kpi['type'] ?? 'text') {
            'money' => $value !== null ? $formatMoney($value) : 'Pendiente',
            'percent' => $value !== null ? $formatNumber($value).'%' : 'Pendiente',
            'kwp' => $value !== null ? $formatKwp($value) : 'Pendiente',
            'kwh' => $value !== null ? $formatKwh($value) : 'Pendiente',
            default => (string) ($value ?? 'Pendiente'),
        };
    };

    $weatherStationChartRows = $recentWeatherStationReadings
        ->sortBy('measured_at')
        ->take(30)
        ->map(fn ($reading) => [
            'recorded_at' => $reading->measured_at->format('Y-m-d H:i'),
            'radiation' => $reading->radiationValue(),
            'uva' => $reading->uva !== null ? (float) $reading->uva : null,
            'uvb' => $reading->uvb !== null ? (float) $reading->uvb : null,
            'uv_index' => $reading->uv_index !== null ? (float) $reading->uv_index : null,
        ])
        ->values()
        ->all();
    $latestWeatherChartPoint = collect($weatherStationChartRows)->last();
    $latestUvIndex = $latestWeatherChartPoint['uv_index'] ?? ($latestWeatherStationReading?->uv_index !== null ? (float) $latestWeatherStationReading->uv_index : null);
    $uvIndexPercent = $latestUvIndex !== null ? min(100, ($latestUvIndex / 11) * 100) : 0;
    $uvRisk = match (true) {
        $latestUvIndex === null => 'Sin dato',
        $latestUvIndex < 3 => 'Bajo',
        $latestUvIndex < 6 => 'Moderado',
        $latestUvIndex < 8 => 'Alto',
        $latestUvIndex < 11 => 'Muy alto',
        default => 'Extremo',
    };
    $tableHasDaysColumn = $activeScale ? count($activeScale['table']['headers'] ?? []) > 6 : false;
@endphp

<x-layouts::app :title="$solarProject->name">
    <div class="solar-page w-full min-w-0 max-w-full overflow-x-hidden">
        <div class="solar-hero w-full min-w-0 max-w-full">
            <div class="solar-page-header">
                <div class="min-w-0 max-w-3xl">
                    <p class="solar-kicker">Dashboard solar operativo</p>
                    <h1 class="solar-title">{{ $solarProject->name }}</h1>
                    <p class="solar-subtitle">
                        {{ $solarProject->location_name }} · {{ $solarProject->start_date->format('Y-m-d') }} al {{ $solarProject->end_date->format('Y-m-d') }}
                    </p>
                    <p class="mt-4 max-w-2xl text-sm leading-6 text-[color:var(--solar-text-muted)]">
                        {{ $solarProject->description ?: 'Proyecto sin descripcion registrada.' }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('solar-projects.edit', $solarProject) }}" class="solar-button-secondary">
                        Editar proyecto
                    </a>
                    <a href="{{ route('solar-projects.index') }}" class="solar-button-ghost">
                        Volver al listado
                    </a>
                </div>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="solar-metric-card">
                    <p class="solar-metric-label">Periodo</p>
                    <p class="solar-metric-value text-2xl">{{ $solarProject->start_date->format('Y-m-d') }}</p>
                    <p class="solar-metric-copy">Hasta {{ $solarProject->end_date->format('Y-m-d') }}</p>
                </div>

                <div class="solar-metric-card">
                    <p class="solar-metric-label">Consumo base mensual</p>
                    <p class="solar-metric-value text-2xl">{{ $formatKwh($solarProject->monthlyConsumption()) }}</p>
                    <p class="solar-metric-copy">Derivado: {{ $formatKwh($solarProject->dailyConsumption()) }} por dia y {{ $formatKwh($solarProject->annualConsumption()) }} por ano.</p>
                </div>

                <div class="solar-metric-card">
                    <p class="solar-metric-label">Datos NASA</p>
                    <p class="solar-metric-value text-2xl">{{ number_format($solarProject->weather_data_count, 0, ',', '.') }}</p>
                    <p class="solar-metric-copy">Registros climaticos disponibles para simulacion.</p>
                </div>

                <div class="solar-metric-card">
                    <p class="solar-metric-label">Estacion local</p>
                    <p class="solar-metric-value text-2xl">{{ number_format($weatherStationStats['total'] ?? 0, 0, ',', '.') }}</p>
                    <p class="solar-metric-copy">Lecturas recientes para contexto operativo real.</p>
                </div>
            </div>
        </div>

        @if (session('status'))
            <div class="solar-alert solar-alert-success">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->has('weather_data'))
            <div class="solar-alert solar-alert-danger">
                {{ $errors->first('weather_data') }}
            </div>
        @endif

        @if ($errors->has('weather_station'))
            <div class="solar-alert solar-alert-danger">
                {{ $errors->first('weather_station') }}
            </div>
        @endif

        @if ($errors->has('solar_calculation'))
            <div class="solar-alert solar-alert-danger">
                {{ $errors->first('solar_calculation') }}
            </div>
        @endif

        @if ($activeScale)
            <section class="solar-report w-full min-w-0 max-w-full">
                <div class="solar-report-topbar">
                    <div class="min-w-0">
                        <p class="solar-kicker">Power BI report</p>
                        <h2 class="solar-report-title">Lectura simple del sistema solar</h2>
                        <p class="solar-report-subtitle">
                            Vista ejecutiva para entender generacion, ahorro, cobertura y riesgos sin interpretar datos tecnicos.
                        </p>
                    </div>

                    <div class="solar-report-filter" data-scale-range-label>
                        {{ $activeScale['rangeLabel'] ?? 'Periodo activo' }}
                    </div>
                </div>

                <div class="solar-report-kpis">
                    <div class="solar-report-kpi">
                        <span class="solar-report-icon solar-report-icon-sun"></span>
                        <div>
                            <p class="solar-metric-label" data-report-generation-label>{{ $generationKpi['label'] ?? 'Generacion' }}</p>
                            <p class="solar-report-kpi-value" data-report-generation-value>{{ $generationKpi ? $renderMetric($generationKpi) : 'Pendiente' }}</p>
                            <p class="solar-report-kpi-copy">Energia producida por el sistema.</p>
                        </div>
                    </div>

                    <div class="solar-report-kpi">
                        <span class="solar-report-icon solar-report-icon-money"></span>
                        <div>
                            <p class="solar-metric-label" data-report-savings-label>{{ $savingsKpi['label'] ?? 'Ahorro' }}</p>
                            <p class="solar-report-kpi-value solar-report-kpi-success" data-report-savings-value>{{ $savingsKpi ? $renderMetric($savingsKpi) : 'Pendiente' }}</p>
                            <p class="solar-report-kpi-copy">Dinero que podria dejar de pagarse.</p>
                        </div>
                    </div>

                    <div class="solar-report-kpi">
                        <span class="solar-report-icon solar-report-icon-coverage"></span>
                        <div>
                            <p class="solar-metric-label" data-report-coverage-label>{{ $coverageKpi['label'] ?? 'Cobertura' }}</p>
                            <p class="solar-report-kpi-value" data-report-coverage-value>{{ $coverageKpi ? $renderMetric($coverageKpi) : 'Pendiente' }}</p>
                            <p class="solar-report-kpi-copy">Que tanto consumo cubre la energia solar.</p>
                        </div>
                    </div>

                    <div class="solar-report-kpi solar-report-kpi-{{ $alertTone }}" data-report-alert-kpi>
                        <span class="solar-report-icon solar-report-icon-alert"></span>
                        <div>
                            <p class="solar-metric-label">Estado alerta</p>
                            <p class="solar-report-kpi-value" data-report-alert-label>{{ $alertLabel }}</p>
                            <p class="solar-report-kpi-copy" data-scale-risk>{{ $activeScale['risk'] ?? 'No se identifican riesgos criticos.' }}</p>
                        </div>
                    </div>
                </div>

                <div class="solar-report-grid">
                    <div class="solar-report-panel solar-report-panel-main">
                        <div class="solar-report-panel-head">
                            <div>
                                <p class="solar-kicker">Rendimiento energetico</p>
                                <h3>Generacion, consumo y ahorro</h3>
                            </div>
                            <p data-scale-chart-range>{{ $activeScale['chart']['rangeLabel'] ?? 'Sin datos graficos disponibles.' }}</p>
                        </div>
                        <div class="solar-report-chart">
                            <canvas id="solar-executive-chart" aria-label="Reporte ejecutivo energetico" role="img"></canvas>
                        </div>
                    </div>

                    <aside class="solar-report-panel">
                        <div class="solar-report-panel-head">
                            <div>
                                <p class="solar-kicker">Lectura rapida</p>
                                <h3>Que mirar primero</h3>
                            </div>
                        </div>

                        <div class="solar-report-callouts" data-report-highlights>
                            @foreach ($activeScale['highlights'] ?? [] as $highlight)
                                <div>
                                    <span>{{ $highlight['label'] ?? '' }}</span>
                                    <strong>{{ $highlight['value'] ?? '' }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </aside>
                </div>
            </section>
        @endif

        <div class="grid min-w-0 max-w-full gap-6 xl:grid-cols-[minmax(0,1.8fr)_minmax(0,1fr)]">
            <section class="min-w-0 space-y-6">
                <div class="solar-card-strong w-full min-w-0 max-w-full">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 max-w-3xl">
                            <p class="solar-kicker">Selector temporal</p>
                            <h2 class="mt-2 text-3xl text-[color:var(--solar-text)]">Lectura dinamica del dashboard</h2>
                            <p class="mt-3 text-sm leading-6 text-[color:var(--solar-text)]" data-scale-summary>
                                {{ $activeScale['summary'] ?? 'Ejecuta los calculos solares para activar el dashboard operativo.' }}
                            </p>
                        </div>

                        <div class="solar-scale-tabs" data-dashboard-scale-selector>
                            @foreach (['daily' => 'Diario', 'monthly' => 'Mensual', 'annual' => 'Anual'] as $scaleKey => $scaleLabel)
                                <button
                                    type="button"
                                    class="solar-scale-tab {{ $scaleKey === $activeScaleKey ? 'is-active' : '' }}"
                                    data-scale-button="{{ $scaleKey }}"
                                >
                                    {{ $scaleLabel }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 lg:grid-cols-3">
                        <div class="solar-subcard">
                            <p class="solar-metric-label">Estado del periodo</p>
                            <p class="mt-2 text-lg font-semibold {{ $activeScale['stateTone'] ?? 'text-zinc-500 dark:text-zinc-400' }}" data-scale-state-title>
                                {{ $activeScale['stateTitle'] ?? 'Estado pendiente' }}
                            </p>
                            <p class="mt-2 text-sm text-[color:var(--solar-text-muted)]" data-scale-range-label>{{ $activeScale['rangeLabel'] ?? 'Sin periodo activo' }}</p>
                        </div>

                        <div class="solar-subcard solar-subcard-danger">
                            <p class="solar-metric-label solar-subcard-title-danger">Riesgo principal</p>
                            <p class="solar-subcard-emphasis mt-2 text-sm font-semibold" data-scale-risk>
                                {{ $activeScale['risk'] ?? 'No se identifican riesgos criticos en este momento.' }}
                            </p>
                            <p class="solar-subcard-copy mt-2 text-sm">Punto prioritario para el seguimiento operativo del periodo.</p>
                        </div>

                        <div class="solar-subcard solar-subcard-success">
                            <p class="solar-metric-label solar-subcard-title-success">Recomendacion principal</p>
                            <p class="solar-subcard-emphasis mt-2 text-sm font-semibold" data-scale-primary-recommendation>
                                {{ $activeScale['primaryRecommendation'] ?? 'Sin recomendacion principal disponible.' }}
                            </p>
                            <p class="solar-subcard-copy mt-2 text-sm">Accion sugerida para mejorar cobertura, ahorro o autoconsumo.</p>
                        </div>
                    </div>

                    <div class="mt-5 rounded-2xl border border-[rgba(129,88,44,0.12)] bg-white/70 p-4 dark:border-zinc-700/70 dark:bg-zinc-950/40">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="solar-metric-label">Narrativa activa</p>
                                <p class="mt-1 text-sm text-[color:var(--solar-text-muted)]">
                                    {{ $dashboard['executiveSummary']['enabled'] ? 'Resumen IA opcional enriquecido con reglas operativas.' : 'Narrativa consolidada por reglas y contexto temporal.' }}
                                </p>
                            </div>
                            <span class="solar-pill">{{ $dashboard['executiveSummary']['enabled'] ? 'IA disponible' : 'Modo reglas' }}</span>
                        </div>
                    </div>
                </div>

                <div class="solar-card w-full min-w-0 max-w-full">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="solar-kicker">KPIs del sistema</p>
                            <h2 class="text-2xl text-[color:var(--solar-text)]">Indicadores clave</h2>
                            <p class="solar-subtitle mt-2">Los KPIs cambian con la escala seleccionada para priorizar decision operativa.</p>
                        </div>
                    </div>

                    @if ($activeScale && ! empty($activeScale['kpis']))
                        <div class="solar-kpi-board mt-4" data-scale-kpis>
                            @foreach ($activeScale['kpis'] as $kpi)
                                <div class="solar-metric-card min-w-0">
                                    <p class="solar-metric-label">{{ $kpi['label'] }}</p>
                                    <p class="solar-metric-value {{ $kpi['tone'] ?? 'text-[color:var(--solar-text)]' }}">
                                        {{ $renderMetric($kpi) }}
                                    </p>
                                    <p class="solar-metric-copy">{{ $kpi['description'] }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="solar-alert solar-alert-warning mt-4">
                            Ejecuta los calculos solares para activar KPIs dinamicos del proyecto.
                        </div>
                    @endif

                    @unless ($hasWeatherData || $hasWeatherStationData)
                        <div class="solar-alert solar-alert-warning mt-4">
                            Aun no hay datos climaticos disponibles. Carga NASA POWER o la estacion para habilitar analitica temporal.
                        </div>
                    @endunless
                </div>

                @if ($activeScale)
                    <script type="application/json" id="solar-timescale-chart-data">@json($timeScales)</script>

                    @if ($hasTimeScaleCharts)
                    <section class="solar-card solar-results-workbench w-full min-w-0 max-w-full">
                        <div class="solar-results-workbench__head">
                            <div>
                                <p class="solar-kicker">Analitica visual</p>
                                <h2 class="text-2xl text-[color:var(--solar-text)]">Graficas y resultados del periodo</h2>
                                <p class="solar-subtitle mt-2" data-scale-chart-range>
                                    {{ $activeScale['chart']['rangeLabel'] ?? 'Sin datos graficos disponibles.' }}
                                </p>
                            </div>
                            <div class="solar-pill">
                                {{ $activeScale['table']['title'] ?? 'Resultados' }}
                            </div>
                        </div>

                        <div class="solar-results-workbench__viewport">
                            <div class="solar-results-workbench__grid">
                                <div class="solar-results-workbench__charts">
                                    <div class="solar-chart-panel solar-chart-panel-compact min-w-0">
                                        <h3 data-scale-chart-generation-title>
                                            {{ $activeScale['chart']['generationTitle'] ?? 'Generacion' }}
                                        </h3>
                                        <div class="solar-chart-canvas mt-3">
                                            <canvas id="solar-generation-chart" aria-label="Grafica de generacion solar" role="img"></canvas>
                                        </div>
                                    </div>

                                    <div class="solar-chart-panel solar-chart-panel-compact min-w-0">
                                        <h3 data-scale-chart-comparison-title>
                                            {{ $activeScale['chart']['comparisonTitle'] ?? 'Consumo vs generacion' }}
                                        </h3>
                                        <div class="solar-chart-canvas mt-3">
                                            <canvas id="solar-consumption-generation-chart" aria-label="Grafica de consumo vs generacion" role="img"></canvas>
                                        </div>
                                    </div>

                                    <div class="solar-chart-panel solar-chart-panel-compact min-w-0">
                                        <h3 data-scale-chart-savings-title>
                                            {{ $activeScale['chart']['savingsTitle'] ?? 'Ahorro' }}
                                        </h3>
                                        <div class="solar-chart-canvas mt-3">
                                            <canvas id="solar-savings-chart" aria-label="Grafica de ahorro" role="img"></canvas>
                                        </div>
                                    </div>

                                    <div class="solar-chart-panel solar-chart-panel-compact min-w-0">
                                        <h3 data-scale-chart-coverage-title>
                                            {{ $activeScale['chart']['coverageTitle'] ?? 'Cobertura' }}
                                        </h3>
                                        <div class="solar-chart-canvas mt-3">
                                            <canvas id="solar-coverage-chart" aria-label="Grafica de cobertura" role="img"></canvas>
                                        </div>
                                    </div>
                                </div>

                                <div class="solar-results-workbench__table min-w-0">
                                    <div class="solar-results-workbench__table-head">
                                        <div>
                                            <p class="solar-kicker">Desglose temporal</p>
                                            <h3 data-scale-table-title>
                                                {{ $activeScale['table']['title'] ?? 'Resultados del periodo' }}
                                            </h3>
                                        </div>
                                        <p data-scale-table-subtitle>
                                            {{ $activeScale['table']['subtitle'] ?? 'Sin detalle disponible.' }}
                                        </p>
                                    </div>

                                    <div class="solar-table-shell solar-table-shell-compact mt-4 min-w-0 max-w-full">
                                        <div class="solar-table-scroll">
                                            <table class="solar-table solar-table-fit">
                                                <thead data-scale-table-head>
                                                    <tr>
                                                        @foreach ($activeScale['table']['headers'] ?? [] as $header)
                                                            <th>{{ $header }}</th>
                                                        @endforeach
                                                    </tr>
                                                </thead>
                                                <tbody data-scale-table-body>
                                                    @foreach ($activeScale['table']['rows'] ?? [] as $row)
                                                        <tr>
                                                            <td class="solar-table-fit-period">{{ $row['period'] }}</td>
                                                            @if ($tableHasDaysColumn)
                                                                <td class="solar-table-fit-num">{{ $row['days'] ?? '—' }}</td>
                                                            @endif
                                                            <td class="solar-table-fit-num" title="{{ $formatNumber($row['radiation']) }} kWh/m²/día">{{ $formatNumber($row['radiation'], 1) }}</td>
                                                            <td class="solar-table-fit-num" title="{{ $formatKwh($row['generation']) }}">{{ $formatNumber($row['generation'], 0) }}</td>
                                                            <td class="solar-table-fit-num" title="{{ $formatKwh($row['consumption']) }}">{{ $formatNumber($row['consumption'], 0) }}</td>
                                                            <td class="solar-table-fit-num" title="{{ $formatNumber($row['coverage']) }}%">{{ $formatNumber($row['coverage'], 0) }}%</td>
                                                            <td class="solar-table-fit-num" title="{{ $formatMoney($row['savings']) }}">{{ $formatMoney($row['savings']) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot data-scale-table-foot>
                                                    <tr>
                                                        <td class="solar-table-fit-period" colspan="{{ $tableHasDaysColumn ? 3 : 2 }}">{{ $activeScale['table']['footer']['label'] ?? 'Total' }}</td>
                                                        <td class="solar-table-fit-num" title="{{ isset($activeScale['table']['footer']['generation']) ? $formatKwh($activeScale['table']['footer']['generation']) : 'Pendiente' }}">{{ isset($activeScale['table']['footer']['generation']) ? $formatNumber($activeScale['table']['footer']['generation'], 0) : '—' }}</td>
                                                        <td class="solar-table-fit-num" title="{{ isset($activeScale['table']['footer']['consumption']) ? $formatKwh($activeScale['table']['footer']['consumption']) : 'Pendiente' }}">{{ isset($activeScale['table']['footer']['consumption']) ? $formatNumber($activeScale['table']['footer']['consumption'], 0) : '—' }}</td>
                                                        <td class="solar-table-fit-num"></td>
                                                        <td class="solar-table-fit-num" title="{{ isset($activeScale['table']['footer']['savings']) ? $formatMoney($activeScale['table']['footer']['savings']) : 'Pendiente' }}">{{ isset($activeScale['table']['footer']['savings']) ? $formatMoney($activeScale['table']['footer']['savings']) : '—' }}</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </section>
                    @endif

                    {{-- Analysis panels moved below to span full width --}}
                @else
                    <div class="solar-alert solar-alert-warning">
                        {{ $calculationResult ? 'Los calculos generales existen, pero aun no hay suficientes datos temporales para activar el dashboard dinamico.' : 'Ejecuta los calculos solares para visualizar el dashboard dinamico del proyecto.' }}
                    </div>
                @endif

            </section>

            <aside class="min-w-0 space-y-6">
                <section class="solar-card w-full min-w-0 max-w-full">
                    <div>
                        <p class="solar-kicker">Workflow</p>
                        <h2 class="text-2xl text-[color:var(--solar-text)]">Acciones</h2>
                        <p class="solar-subtitle mt-2">Flujo recomendado para refrescar datos y recalcular el proyecto.</p>
                    </div>

                    <div class="mt-4 space-y-3">
                        <form method="POST" action="{{ route('solar-projects.calculate-weather-station', $solarProject) }}">
                            @csrf
                            <button type="submit" class="solar-button w-full">
                                Ejecutar datos con estacion
                            </button>
                        </form>

                        <form method="POST" action="{{ route('solar-projects.calculate', $solarProject) }}">
                            @csrf
                            <button type="submit" class="solar-button-secondary w-full">
                                Ejecutar datos con NASA
                            </button>
                        </form>

                        <a href="{{ route('api-data.index') }}" class="solar-button-ghost block w-full">
                            Ir a Datos APIs
                        </a>
                    </div>
                </section>

                <section class="solar-card w-full min-w-0 max-w-full">
                    <div>
                        <p class="solar-kicker">Contexto base</p>
                        <h2 class="text-2xl text-[color:var(--solar-text)]">Contexto del proyecto</h2>
                        <p class="solar-subtitle mt-2">Base tecnica y de consumo usada por las escalas diaria, mensual y anual.</p>
                    </div>

                    <dl class="solar-data-list mt-4">
                        <div class="solar-data-row">
                            <dt>Ubicacion</dt>
                            <dd>{{ $solarProject->location_name }}</dd>
                        </div>
                        <div class="solar-data-row">
                            <dt>Coordenadas</dt>
                            <dd>{{ $formatCoordinate($solarProject->latitude) }}, {{ $formatCoordinate($solarProject->longitude) }}</dd>
                        </div>
                        <div class="solar-data-row">
                            <dt>Tarifa energetica</dt>
                            <dd>{{ $formatMoney($solarProject->energy_rate_cop_kwh) }}/kWh</dd>
                        </div>
                        <div class="solar-data-row">
                            <dt>Consumo mensual</dt>
                            <dd>{{ $formatKwh($solarProject->monthlyConsumption()) }}</dd>
                        </div>
                        <div class="solar-data-row">
                            <dt>Consumo diario derivado</dt>
                            <dd>{{ $formatKwh($solarProject->dailyConsumption()) }}</dd>
                        </div>
                        <div class="solar-data-row">
                            <dt>Consumo anual derivado</dt>
                            <dd>{{ $formatKwh($solarProject->annualConsumption()) }}</dd>
                        </div>
                    </dl>

                    @if ($technicalParameter)
                        <dl class="solar-data-list mt-4 border-t border-[rgba(129,88,44,0.12)] pt-4">
                            <div class="solar-data-row">
                                <dt>Area disponible</dt>
                                <dd>{{ $formatNumber($technicalParameter->available_area_m2) }} m2</dd>
                            </div>
                            <div class="solar-data-row">
                                <dt>Area utilizable</dt>
                                <dd>{{ $formatNumber($technicalParameter->usable_area_percentage) }}%</dd>
                            </div>
                            <div class="solar-data-row">
                                <dt>Potencia por panel</dt>
                                <dd>{{ $formatNumber($technicalParameter->panel_power_w) }} W</dd>
                            </div>
                            <div class="solar-data-row">
                                <dt>Area del panel</dt>
                                <dd>{{ $formatNumber($technicalParameter->panel_area_m2) }} m2</dd>
                            </div>
                            <div class="solar-data-row">
                                <dt>Performance ratio</dt>
                                <dd>{{ $formatNumber($technicalParameter->performance_ratio, 3) }}</dd>
                            </div>
                            <div class="solar-data-row">
                                <dt>Perdidas del sistema</dt>
                                <dd>{{ $formatNumber($technicalParameter->system_losses_percentage) }}%</dd>
                            </div>
                        </dl>
                    @else
                        <div class="solar-alert solar-alert-warning mt-4">
                            Este proyecto aun no tiene parametros tecnicos registrados.
                        </div>
                    @endif
                </section>

                @if ($activeScale && ! empty($activeScale['highlights']))
                    <section class="solar-card w-full min-w-0 max-w-full">
                        <div>
                            <p class="solar-kicker">Puntos clave</p>
                            <h2 class="text-2xl text-[color:var(--solar-text)]">Resumen del periodo</h2>
                            <p class="solar-subtitle mt-2">Se actualiza automaticamente al cambiar la escala temporal.</p>
                        </div>

                        <dl class="solar-data-list mt-4 text-sm" data-scale-highlights>
                            @foreach ($activeScale['highlights'] as $highlight)
                                <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                                    <dt class="text-zinc-500 dark:text-zinc-400">{{ $highlight['label'] }}</dt>
                                    <dd class="mt-1 font-semibold text-zinc-950 dark:text-zinc-50">{{ $highlight['value'] }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>
                @endif
            </aside>

            {{-- Analysis panels: span full width below main and aside --}}
        @if ($activeScale)
            <div class="mt-6 xl:col-span-2">
                <section class="solar-card w-full min-w-0 max-w-full">
                    <div class="solar-analysis-board">
                        <div class="solar-analysis-panel">
                            <div>
                                <p class="solar-kicker">Insights del periodo</p>
                                <h2 class="text-xl text-[color:var(--solar-text)]">Analisis operativo</h2>
                                <p class="solar-subtitle mt-2">Lectura visual del comportamiento reciente y decisiones de corto plazo.</p>
                            </div>

                            <div class="solar-analysis-panel-list" data-scale-insights>
                                @forelse ($activeScale['insights'] ?? [] as $insight)
                                    @php
                                        $insightTone = match ($insight['level'] ?? 'info') {
                                            'success' => 'solar-insight-card-success',
                                            'warning' => 'solar-insight-card-warning',
                                            'danger' => 'solar-insight-card-danger',
                                            default => 'solar-insight-card-info',
                                        };
                                    @endphp
                                    <article class="solar-insight-card {{ $insightTone }}">
                                        <div class="solar-insight-card-head">
                                            <p class="solar-insight-card-title">{{ $insight['title'] }}</p>
                                            <span class="solar-pill">{{ ucfirst($insight['level'] ?? 'info') }}</span>
                                        </div>
                                        <p class="solar-insight-card-copy">{{ $insight['message'] }}</p>
                                    </article>
                                @empty
                                    <div class="solar-alert solar-alert-warning">Sin insights para la escala activa.</div>
                                @endforelse
                            </div>
                        </div>

                        <div class="solar-analysis-panel">
                            <div>
                                <p class="solar-kicker">Recomendaciones inteligentes</p>
                                <h2 class="text-xl text-[color:var(--solar-text)]">Acciones sugeridas</h2>
                                <p class="solar-subtitle mt-2">Priorizadas segun la escala temporal activa del dashboard.</p>
                            </div>

                            <div class="solar-analysis-panel-list" data-scale-recommendations>
                                @forelse ($activeScale['recommendations'] ?? [] as $recommendation)
                                    @php
                                        $priorityTone = match ($recommendation['priority'] ?? 'media') {
                                            'alta' => 'solar-pill-danger',
                                            'baja' => 'solar-pill',
                                            default => 'solar-pill-warn',
                                        };
                                        $typeLabel = match ($recommendation['type'] ?? 'recomendacion') {
                                            'risk' => 'Riesgo',
                                            'alert' => 'Alerta',
                                            'opportunity' => 'Oportunidad',
                                            default => 'Recomendacion',
                                        };
                                    @endphp
                                    <article class="solar-recommendation-card">
                                        <div class="solar-recommendation-card-head">
                                            <p class="solar-recommendation-card-title">{{ $typeLabel }}</p>
                                            <span class="solar-pill {{ $priorityTone }}">Prioridad {{ $recommendation['priority'] ?? 'media' }}</span>
                                        </div>
                                        <p class="solar-recommendation-card-copy">{{ $recommendation['message'] }}</p>
                                    </article>
                                @empty
                                    <div class="solar-alert solar-alert-warning">Sin recomendaciones para la escala activa.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </section>
</div>
        @endif

        @if ($hasWeatherStationData)
            <div class="mt-6 xl:col-span-2">
                <section class="solar-card w-full min-w-0 max-w-full">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="solar-kicker">Clima y radiacion</p>
                            <h2 class="text-2xl text-[color:var(--solar-text)]">Detalle meteorologico</h2>
                            <p class="solar-subtitle mt-2">Contexto en tiempo real para interpretar cobertura, ahorro y decisiones diarias.</p>
                        </div>
                        <div class="solar-pill">
                            {{ number_format($weatherStationStats['total'] ?? 0, 0, ',', '.') }} lecturas
                        </div>
                    </div>

                    <div class="solar-weather-board mt-4">
                        <div class="solar-weather-kpis">
                            <div class="solar-metric-card">
                                <p class="solar-metric-label">Ultima medicion</p>
                                <p class="solar-metric-value text-xl">{{ $latestWeatherStationReading?->measured_at?->format('d/m H:i') ?? 'N/A' }}</p>
                                <p class="solar-metric-copy">Lectura mas reciente del centro meteorologico.</p>
                            </div>
                            <div class="solar-metric-card">
                                <p class="solar-metric-label">Radiacion promedio</p>
                                <p class="solar-metric-value text-xl">{{ $formatNumber($weatherStationStats['averageRadiation'] ?? 0, 2) }}</p>
                                <p class="solar-metric-copy">Promedio de las lecturas locales disponibles.</p>
                            </div>
                            <div class="solar-metric-card">
                                <p class="solar-metric-label">UV maximo</p>
                                <p class="solar-metric-value text-xl">{{ $formatNumber($weatherStationStats['maxUvIndex'] ?? 0, 1) }}</p>
                                <p class="solar-metric-copy">Indice UV mas alto registrado.</p>
                            </div>
                            <div class="solar-metric-card">
                                <p class="solar-metric-label">Fuentes de datos</p>
                                <p class="solar-metric-value text-xl">{{ number_format($weatherStationStats['total'] ?? 0, 0, ',', '.') }} / {{ number_format($solarProject->weather_data_count, 0, ',', '.') }}</p>
                                <p class="solar-metric-copy">Estacion local vs registros NASA POWER.</p>
                            </div>
                        </div>

                        <div class="solar-weather-charts">
                            <div class="solar-weather-chart-panel">
                                <p class="solar-kicker">Tendencia diaria</p>
                                <h3>Radiacion observada</h3>
                                <div class="solar-weather-chart-canvas">
                                    <canvas id="weather-station-radiation-chart" aria-label="Radiacion diaria del centro meteorologico" role="img"></canvas>
                                </div>
                            </div>

                            <div class="solar-metric-card solar-weather-iuv-card">
                                <p class="solar-metric-label">IUV actual</p>
                                <p class="solar-metric-value" data-weather-station-iuv-value>{{ $latestUvIndex !== null ? $formatNumber($latestUvIndex, 2) : 'N/A' }}</p>
                                <p class="solar-metric-copy" data-weather-station-iuv-risk>{{ $uvRisk }}</p>
                                <div class="solar-weather-iuv-bar">
                                    <div data-weather-station-iuv-bar style="width: {{ $uvIndexPercent }}%"></div>
                                </div>
                            </div>
                        </div>

                        <div class="solar-weather-chart-panel">
                            <p class="solar-kicker">Lecturas recientes</p>
                            <h3>Radiacion, UVA, UVB e IUV</h3>
                            <div class="solar-weather-chart-canvas">
                                <canvas id="weather-station-realtime-chart" aria-label="Variables UV y radiacion recientes" role="img"></canvas>
                            </div>
                        </div>

                        <div class="solar-table-shell">
                            <div class="solar-table-scroll">
                                <table class="solar-table solar-table-fit">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Rad.</th>
                                            <th>UVA</th>
                                            <th>UVB</th>
                                            <th>IUV</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($recentWeatherStationReadings as $reading)
                                            <tr>
                                                <td class="solar-table-fit-period">{{ $reading->measured_at->format('d/m H:i') }}</td>
                                                <td class="solar-table-fit-num">{{ $reading->radiationValue() !== null ? $formatNumber($reading->radiationValue(), 2) : 'N/A' }}</td>
                                                <td class="solar-table-fit-num">{{ $reading->uva !== null ? $formatNumber($reading->uva, 2) : 'N/A' }}</td>
                                                <td class="solar-table-fit-num">{{ $reading->uvb !== null ? $formatNumber($reading->uvb, 2) : 'N/A' }}</td>
                                                <td class="solar-table-fit-num">{{ $reading->uv_index !== null ? $formatNumber($reading->uv_index, 2) : 'N/A' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <script type="application/json" id="weather-station-realtime-chart-data">@json($weatherStationChartRows)</script>
                        <script type="application/json" id="weather-station-chart-data">@json($weatherStationChartData)</script>
                    </div>
                </section>
            </div>
        @endif
        </div>
    </div>
</x-layouts::app>
