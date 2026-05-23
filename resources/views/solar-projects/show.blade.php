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

    $formatNumber = fn ($value, int $decimals = 2) => number_format((float) $value, $decimals, ',', '.');
    $formatKwh = fn ($value) => $formatNumber($value).' kWh';
    $formatKwp = fn ($value) => $formatNumber($value).' kWp';
    $formatMoney = fn ($value) => '$ '.number_format((float) $value, 0, ',', '.').' COP';
    $formatCoordinate = fn ($value) => number_format((float) $value, 4, '.', '');

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

        <div class="grid min-w-0 max-w-full gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(0,0.95fr)]">
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

                        <div class="flex flex-wrap gap-2" data-dashboard-scale-selector>
                            @foreach (['daily' => 'Diario', 'monthly' => 'Mensual', 'annual' => 'Anual'] as $scaleKey => $scaleLabel)
                                <button
                                    type="button"
                                    class="{{ $scaleKey === $activeScaleKey ? 'solar-button' : 'solar-button-ghost' }}"
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
                        <div class="mt-4 grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3" data-scale-kpis>
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
                    <section class="solar-card w-full min-w-0 max-w-full">
                        <div>
                            <p class="solar-kicker">Analitica visual</p>
                            <h2 class="text-2xl text-[color:var(--solar-text)]">Graficas del periodo</h2>
                            <p class="solar-subtitle mt-2" data-scale-chart-range>
                                {{ $activeScale['chart']['rangeLabel'] ?? 'Sin datos graficos disponibles.' }}
                            </p>
                        </div>

                        <div class="mt-4 grid min-w-0 gap-4 xl:grid-cols-2">
                            <div class="solar-chart-panel min-w-0">
                                <h3 class="text-sm font-semibold text-[color:var(--solar-text)]" data-scale-chart-generation-title>
                                    {{ $activeScale['chart']['generationTitle'] ?? 'Generacion' }}
                                </h3>
                                <div class="solar-chart-canvas mt-4 h-56 sm:h-64 lg:h-72">
                                    <canvas id="solar-generation-chart" aria-label="Grafica de generacion solar" role="img"></canvas>
                                </div>
                            </div>

                            <div class="solar-chart-panel min-w-0">
                                <h3 class="text-sm font-semibold text-[color:var(--solar-text)]" data-scale-chart-comparison-title>
                                    {{ $activeScale['chart']['comparisonTitle'] ?? 'Consumo vs generacion' }}
                                </h3>
                                <div class="solar-chart-canvas mt-4 h-56 sm:h-64 lg:h-72">
                                    <canvas id="solar-consumption-generation-chart" aria-label="Grafica de consumo vs generacion" role="img"></canvas>
                                </div>
                            </div>

                            <div class="solar-chart-panel min-w-0">
                                <h3 class="text-sm font-semibold text-[color:var(--solar-text)]" data-scale-chart-savings-title>
                                    {{ $activeScale['chart']['savingsTitle'] ?? 'Ahorro' }}
                                </h3>
                                <div class="solar-chart-canvas mt-4 h-56 sm:h-64 lg:h-72">
                                    <canvas id="solar-savings-chart" aria-label="Grafica de ahorro" role="img"></canvas>
                                </div>
                            </div>

                            <div class="solar-chart-panel min-w-0">
                                <h3 class="text-sm font-semibold text-[color:var(--solar-text)]" data-scale-chart-coverage-title>
                                    {{ $activeScale['chart']['coverageTitle'] ?? 'Cobertura' }}
                                </h3>
                                <div class="solar-chart-canvas mt-4 h-56 sm:h-64 lg:h-72">
                                    <canvas id="solar-coverage-chart" aria-label="Grafica de cobertura" role="img"></canvas>
                                </div>
                            </div>
                        </div>

                        <script type="application/json" id="solar-timescale-chart-data">@json($timeScales)</script>
                    </section>

                    <section class="solar-card w-full min-w-0 max-w-full">
                        <div class="grid gap-4 lg:grid-cols-2">
                            <div class="min-w-0">
                                <p class="solar-kicker">Insights del periodo</p>
                                <h2 class="text-2xl text-[color:var(--solar-text)]">Analisis operativo</h2>
                                <p class="solar-subtitle mt-2">El dashboard prioriza comportamiento reciente y decisiones de corto plazo.</p>

                                <div class="mt-4 space-y-3" data-scale-insights>
                                    @foreach ($activeScale['insights'] ?? [] as $insight)
                                        <div class="rounded-2xl border border-[rgba(129,88,44,0.12)] bg-white/70 p-4 dark:border-zinc-700/70 dark:bg-zinc-950/40">
                                            <p class="text-sm font-semibold text-[color:var(--solar-text)]">{{ $insight['title'] }}</p>
                                            <p class="mt-2 text-sm leading-6 text-[color:var(--solar-text-muted)]">{{ $insight['message'] }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="min-w-0">
                                <p class="solar-kicker">Recomendaciones inteligentes</p>
                                <h2 class="text-2xl text-[color:var(--solar-text)]">Acciones sugeridas</h2>
                                <p class="solar-subtitle mt-2">Las recomendaciones usan contexto del periodo en vez de depender solo del acumulado anual.</p>

                                <div class="mt-4 space-y-3" data-scale-recommendations>
                                    @foreach ($activeScale['recommendations'] ?? [] as $recommendation)
                                        <div class="rounded-2xl border border-[rgba(129,88,44,0.12)] bg-white/70 p-4 dark:border-zinc-700/70 dark:bg-zinc-950/40">
                                            <p class="text-sm font-semibold text-[color:var(--solar-text)]">{{ ucfirst($recommendation['type'] ?? 'recomendacion') }}</p>
                                            <p class="mt-2 text-sm leading-6 text-[color:var(--solar-text-muted)]">{{ $recommendation['message'] }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="solar-card w-full min-w-0 max-w-full">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="solar-kicker">Desglose temporal</p>
                                <h2 class="text-2xl text-[color:var(--solar-text)]" data-scale-table-title>
                                    {{ $activeScale['table']['title'] ?? 'Resultados del periodo' }}
                                </h2>
                                <p class="solar-subtitle mt-2" data-scale-table-subtitle>
                                    {{ $activeScale['table']['subtitle'] ?? 'Sin detalle disponible.' }}
                                </p>
                            </div>
                        </div>

                        <div class="solar-table-shell mt-4 min-w-0 max-w-full">
                            <div class="solar-table-scroll">
                                <table class="solar-table solar-table-wide" style="--solar-table-min: 760px;">
                                    <thead data-scale-table-head>
                                        <tr>
                                            @foreach ($activeScale['table']['headers'] ?? [] as $header)
                                                <th class="px-3 py-2">{{ $header }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody data-scale-table-body>
                                        @foreach ($activeScale['table']['rows'] ?? [] as $row)
                                            <tr>
                                                <td class="px-3 py-2 font-medium">{{ $row['period'] }}</td>
                                                <td class="px-3 py-2">{{ $formatNumber($row['radiation']) }} kWh/m2/dia</td>
                                                <td class="px-3 py-2">{{ $formatKwh($row['generation']) }}</td>
                                                <td class="px-3 py-2">{{ $formatKwh($row['consumption']) }}</td>
                                                <td class="px-3 py-2">{{ $formatNumber($row['coverage']) }}%</td>
                                                <td class="px-3 py-2">{{ $formatMoney($row['savings']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot class="border-t border-[rgba(129,88,44,0.12)] text-sm font-semibold text-[color:var(--solar-text)]" data-scale-table-foot>
                                        <tr>
                                            <td class="px-3 py-3" colspan="2">{{ $activeScale['table']['footer']['label'] ?? 'Total' }}</td>
                                            <td class="px-3 py-3">{{ isset($activeScale['table']['footer']['generation']) ? $formatKwh($activeScale['table']['footer']['generation']) : 'Pendiente' }}</td>
                                            <td class="px-3 py-3">{{ isset($activeScale['table']['footer']['consumption']) ? $formatKwh($activeScale['table']['footer']['consumption']) : 'Pendiente' }}</td>
                                            <td class="px-3 py-3"></td>
                                            <td class="px-3 py-3">{{ isset($activeScale['table']['footer']['savings']) ? $formatMoney($activeScale['table']['footer']['savings']) : 'Pendiente' }}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </section>
                @else
                    <div class="solar-alert solar-alert-warning">
                        {{ $calculationResult ? 'Los calculos generales existen, pero aun no hay suficientes datos temporales para activar el dashboard dinamico.' : 'Ejecuta los calculos solares para visualizar el dashboard dinamico del proyecto.' }}
                    </div>
                @endif

                @if ($hasWeatherStationData)
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

                        <div class="mt-4 grid min-w-0 gap-4 lg:grid-cols-2">
                            <div class="solar-subcard">
                                <p class="solar-metric-label">Ultima medicion</p>
                                <p class="mt-2 text-sm font-semibold text-[color:var(--solar-text)]">
                                    {{ $latestWeatherStationReading?->measured_at?->format('Y-m-d H:i') ?? 'Sin fecha disponible' }}
                                </p>
                                <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div class="solar-subcard">
                                        <dt class="solar-metric-label">Radiacion promedio</dt>
                                        <dd class="mt-1 text-sm font-semibold text-[color:var(--solar-text)]">{{ $formatNumber($weatherStationStats['averageRadiation'] ?? 0, 3) }}</dd>
                                    </div>
                                    <div class="solar-subcard">
                                        <dt class="solar-metric-label">Indice UV maximo</dt>
                                        <dd class="mt-1 text-sm font-semibold text-[color:var(--solar-text)]">{{ $formatNumber($weatherStationStats['maxUvIndex'] ?? 0, 2) }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <div class="solar-subcard">
                                <p class="solar-metric-label">Seguimiento operativo</p>
                                <p class="mt-2 text-sm font-semibold text-[color:var(--solar-text)]">
                                    Las lecturas locales permiten contrastar condiciones recientes con la escala temporal activa del dashboard.
                                </p>
                                <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div class="solar-subcard">
                                        <dt class="solar-metric-label">Centro meteorologico</dt>
                                        <dd class="mt-1 text-sm font-semibold text-[color:var(--solar-text)]">{{ number_format($weatherStationStats['total'] ?? 0, 0, ',', '.') }} lecturas</dd>
                                    </div>
                                    <div class="solar-subcard">
                                        <dt class="solar-metric-label">Datos NASA</dt>
                                        <dd class="mt-1 text-sm font-semibold text-[color:var(--solar-text)]">{{ number_format($solarProject->weather_data_count, 0, ',', '.') }} registros</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <div class="solar-chart-panel mt-4">
                            <div class="solar-chart-canvas h-56 min-w-0 max-w-full sm:h-64 lg:h-72">
                                <canvas id="weather-station-radiation-chart" aria-label="Radiacion diaria del centro meteorologico" role="img"></canvas>
                            </div>
                        </div>

                        <details class="mt-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <summary class="cursor-pointer text-sm font-semibold text-zinc-950 dark:text-zinc-50">Ver ultimas lecturas meteorologicas</summary>
                            <div class="mt-4 min-w-0 max-w-full overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                                <div class="solar-table-scroll">
                                    <table class="solar-table solar-table-wide divide-y divide-zinc-200 text-sm dark:divide-zinc-700" style="--solar-table-min: 640px;">
                                        <thead class="bg-zinc-50 dark:bg-zinc-950/60">
                                            <tr class="text-left text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                                <th class="px-3 py-2">Fecha</th>
                                                <th class="px-3 py-2">Radiacion</th>
                                                <th class="px-3 py-2">UVA</th>
                                                <th class="px-3 py-2">UVB</th>
                                                <th class="px-3 py-2">IUV</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                            @foreach ($recentWeatherStationReadings as $reading)
                                                <tr class="text-zinc-700 dark:text-zinc-200">
                                                    <td class="px-3 py-2 font-medium">{{ $reading->measured_at->format('Y-m-d H:i') }}</td>
                                                    <td class="px-3 py-2">{{ $reading->radiationValue() !== null ? $formatNumber($reading->radiationValue(), 3) : 'N/A' }}</td>
                                                    <td class="px-3 py-2">{{ $reading->uva !== null ? $formatNumber($reading->uva, 3) : 'N/A' }}</td>
                                                    <td class="px-3 py-2">{{ $reading->uvb !== null ? $formatNumber($reading->uvb, 3) : 'N/A' }}</td>
                                                    <td class="px-3 py-2">{{ $reading->uv_index !== null ? $formatNumber($reading->uv_index, 3) : 'N/A' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </details>

                        <script type="application/json" id="weather-station-chart-data">@json($weatherStationChartData)</script>
                    </section>
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
        </div>
    </div>
</x-layouts::app>
