@php
    $technicalParameter = $solarProject->technicalParameter;
    $calculationResult = $solarProject->calculationResult;
    $monthlyResults = $solarProject->monthlyResults;
    $hasWeatherData = $solarProject->weather_data_count > 0;
    $dashboard = $dashboard ?? [
        'state' => ['summary' => null],
        'insights' => [
            'energy' => ['summary' => null, 'items' => [], 'monthly' => [], 'highlights' => []],
            'weather' => ['currentSummary' => null, 'historicalSummary' => null, 'current' => [], 'historical' => [], 'readingCount' => 0],
        ],
        'recommendations' => ['summary' => null, 'items' => [], 'groups' => ['actions' => [], 'alerts' => [], 'risks' => [], 'opportunities' => []]],
        'executiveSummary' => ['text' => null, 'dailyRecommendation' => null, 'alerts' => [], 'enabled' => false, 'error' => null, 'source' => 'rule_based'],
        'widgets' => ['executive_summary' => 'Sin resumen ejecutivo disponible.', 'widgets' => []],
    ];
    $energyAnalysis = $energyAnalysis ?? ['insights' => [], 'monthlyInterpretations' => [], 'monthlyHighlights' => []];
    $weatherStationStats = $weatherStationStats ?? [];
    $recentWeatherStationReadings = $recentWeatherStationReadings ?? collect();
    $weatherAnalysis = $weatherAnalysis ?? ['current' => [], 'historical' => []];
    $hasWeatherStationData = ($weatherStationStats['total'] ?? 0) > 0;
    $latestWeatherStationReading = $weatherStationStats['latest'] ?? null;
    $latestRecommendation = $dashboard['recommendations']['summary'] ?? 'Sin recomendaciones automaticas disponibles.';
    $latestCurrentAnalysis = $dashboard['insights']['weather']['currentSummary'] ?? 'Sin alertas actuales relevantes.';
    $latestHistoricalAnalysis = $dashboard['insights']['weather']['historicalSummary'] ?? 'Sin tendencias historicas destacadas.';
    $coverageInterpretation = $dashboard['state']['summary'] ?? ($coverageInterpretation ?? null);
    $executiveSummary = $dashboard['executiveSummary'] ?? ['text' => null, 'dailyRecommendation' => null, 'alerts' => [], 'enabled' => false, 'error' => null, 'source' => 'rule_based'];
    $recommendationGroups = $dashboard['recommendations']['groups'] ?? ['actions' => [], 'alerts' => [], 'risks' => [], 'opportunities' => []];
    $energyInsights = $dashboard['insights']['energy']['items'] ?? ($energyAnalysis['insights'] ?? []);
    $monthlyEnergyInsights = $dashboard['insights']['energy']['monthly'] ?? ($energyAnalysis['monthlyInterpretations'] ?? []);
    $weatherCurrentInsights = $dashboard['insights']['weather']['current'] ?? ($weatherAnalysis['current'] ?? []);
    $weatherHistoricalInsights = $dashboard['insights']['weather']['historical'] ?? ($weatherAnalysis['historical'] ?? []);
    $normalizeText = function ($value) {
        if (! is_string($value)) {
            return null;
        }

        $value = preg_replace('/\s+/', ' ', trim($value));

        return filled($value) ? $value : null;
    };
    $canonicalExecutiveSummary = $normalizeText($executiveSummary['text'] ?? null)
        ?? $normalizeText($dashboard['widgets']['executive_summary'] ?? null)
        ?? 'Sin resumen ejecutivo disponible.';
    $canonicalStateSummary = $normalizeText($coverageInterpretation)
        ?? 'Ejecuta el calculo para conocer el estado energetico general.';
    $canonicalRecommendation = $normalizeText($executiveSummary['dailyRecommendation'] ?? null)
        ?? $normalizeText($latestRecommendation)
        ?? 'Sin recomendacion principal disponible.';
    $riskCandidates = collect($recommendationGroups['risks'] ?? [])
        ->merge($recommendationGroups['alerts'] ?? [])
        ->merge(
            collect($energyInsights)
                ->filter(fn (array $item) => in_array($item['level'] ?? 'info', ['warning', 'error'], true))
                ->map(fn (array $item) => [
                    'message' => $item['message'] ?? null,
                    'priority' => ($item['level'] ?? 'warning') === 'error' ? 'alta' : 'media',
                ])
                ->all()
        )
        ->merge(
            collect($weatherCurrentInsights)
                ->merge($weatherHistoricalInsights)
                ->filter(fn (array $item) => in_array($item['type'] ?? 'info', ['warning', 'error'], true))
                ->map(fn (array $item) => [
                    'message' => $item['message'] ?? null,
                    'priority' => ($item['type'] ?? 'warning') === 'error' ? 'alta' : 'media',
                ])
                ->all()
        )
        ->filter(fn ($item) => is_array($item) && filled($normalizeText($item['message'] ?? null)))
        ->map(fn (array $item) => [
            'message' => $normalizeText($item['message'] ?? null),
            'priority' => $item['priority'] ?? 'media',
        ])
        ->unique('message')
        ->sortByDesc(fn (array $item) => $item['priority'] === 'alta' ? 2 : ($item['priority'] === 'media' ? 1 : 0))
        ->values();
    $canonicalRisk = $riskCandidates->first(function (array $item) use ($canonicalStateSummary, $canonicalRecommendation) {
        return $item['message'] !== $canonicalStateSummary && $item['message'] !== $canonicalRecommendation;
    })['message']
        ?? $normalizeText($latestCurrentAnalysis)
        ?? 'No se identifican riesgos criticos en este momento.';
    $supportingSignals = collect($energyInsights)
        ->merge($monthlyEnergyInsights)
        ->merge($recommendationGroups['opportunities'] ?? [])
        ->map(fn (array $item) => $normalizeText($item['message'] ?? null))
        ->filter()
        ->reject(fn (string $message) => in_array($message, [$canonicalExecutiveSummary, $canonicalStateSummary, $canonicalRecommendation, $canonicalRisk], true))
        ->unique()
        ->take(3)
        ->values();
    $weatherSupportsValue = $hasWeatherStationData && (
        $riskCandidates->contains(fn (array $item) => str_contains(strtolower($item['message']), 'radiacion'))
        || collect($weatherCurrentInsights)->isNotEmpty()
        || collect($weatherHistoricalInsights)->isNotEmpty()
    );

    $formatNumber = fn ($value, int $decimals = 2) => number_format((float) $value, $decimals, ',', '.');
    $formatKwh = fn ($value) => $formatNumber($value) . ' kWh';
    $formatKwp = fn ($value) => $formatNumber($value) . ' kWp';
    $formatPercent = fn ($value) => $formatNumber($value) . '%';
    $formatMoney = fn ($value) => '$ ' . number_format((float) $value, 0, ',', '.') . ' COP';
    $formatCoordinate = fn ($value) => number_format((float) $value, 4, '.', '');
    $energyDifference = $calculationResult
        ? (float) $calculationResult->estimated_annual_generation_kwh - (float) $calculationResult->annual_consumption_kwh
        : null;
    $coverageTone = $calculationResult
        ? ((float) $calculationResult->coverage_percentage >= 100
            ? 'text-emerald-600 dark:text-emerald-400'
            : ((float) $calculationResult->coverage_percentage >= 70
                ? 'text-sky-600 dark:text-sky-400'
                : 'text-amber-600 dark:text-amber-400'))
        : 'text-zinc-500 dark:text-zinc-400';
@endphp

<x-layouts::app :title="$solarProject->name">
    <div class="solar-page w-full min-w-0 max-w-full overflow-x-hidden">
        <div class="solar-hero w-full min-w-0 max-w-full">
            <div class="solar-page-header">
                <div class="min-w-0 max-w-3xl">
                    <p class="solar-kicker">Dashboard solar</p>
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
                    <p class="solar-metric-label">Consumo base</p>
                    <p class="solar-metric-value text-2xl">{{ $formatKwh($solarProject->annual_consumption_kwh) }}</p>
                    <p class="solar-metric-copy">Demanda anual estimada del escenario.</p>
                </div>

                <div class="solar-metric-card">
                    <p class="solar-metric-label">Datos NASA</p>
                    <p class="solar-metric-value text-2xl">{{ number_format($solarProject->weather_data_count, 0, ',', '.') }}</p>
                    <p class="solar-metric-copy">Registros climaticos satelitales disponibles.</p>
                </div>

                <div class="solar-metric-card">
                    <p class="solar-metric-label">Estacion local</p>
                    <p class="solar-metric-value text-2xl">{{ number_format($weatherStationStats['total'] ?? 0, 0, ',', '.') }}</p>
                    <p class="solar-metric-copy">Lecturas locales para contexto real de Riohacha.</p>
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
                            <p class="solar-kicker">Resumen ejecutivo principal</p>
                            <h2 class="mt-2 text-3xl text-[color:var(--solar-text)]">Lectura general del proyecto</h2>
                            <p class="mt-3 text-sm leading-6 text-[color:var(--solar-text)]">
                                {{ $canonicalExecutiveSummary }}
                            </p>
                        </div>

                        <div class="solar-pill">
                            {{ $executiveSummary['enabled'] ? 'Resumen IA opcional' : 'Resumen consolidado por reglas' }}
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 lg:grid-cols-3">
                        <div class="solar-subcard">
                            <p class="solar-metric-label">Estado energetico general</p>
                            <p class="mt-2 text-lg font-semibold {{ $coverageTone }}">
                                {{ $dashboard['state']['title'] ?? 'Estado pendiente' }}
                            </p>
                            <p class="mt-2 text-sm text-[color:var(--solar-text-muted)]">{{ $canonicalStateSummary }}</p>
                        </div>

                        <div class="solar-subcard solar-subcard-danger">
                            <p class="solar-metric-label solar-subcard-title-danger">Riesgo principal</p>
                            <p class="solar-subcard-emphasis mt-2 text-sm font-semibold">{{ $canonicalRisk }}</p>
                            <p class="solar-subcard-copy mt-2 text-sm">Es el punto que mas puede limitar cobertura, ahorro o continuidad operativa.</p>
                        </div>

                        <div class="solar-subcard solar-subcard-success">
                            <p class="solar-metric-label solar-subcard-title-success">Recomendacion principal</p>
                            <p class="solar-subcard-emphasis mt-2 text-sm font-semibold">{{ $canonicalRecommendation }}</p>
                            <p class="solar-subcard-copy mt-2 text-sm">Accion prioritaria para mejorar el resultado operativo del sistema.</p>
                        </div>
                    </div>

                    @if ($supportingSignals->isNotEmpty())
                        <div class="solar-subcard mt-5">
                            <p class="solar-metric-label">Senales de soporte</p>
                            <ul class="mt-3 space-y-2 text-sm text-[color:var(--solar-text-muted)]">
                                @foreach ($supportingSignals as $signal)
                                    <li>{{ $signal }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <div class="solar-card w-full min-w-0 max-w-full">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="solar-kicker">KPIs del sistema</p>
                            <h2 class="text-2xl text-[color:var(--solar-text)]">Indicadores clave</h2>
                            <p class="solar-subtitle mt-2">Metrica ejecutiva del proyecto y del dimensionamiento con mayor contraste visual.</p>
                        </div>
                    </div>

                    <div class="mt-4 grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <div class="solar-metric-card min-w-0">
                            <p class="solar-metric-label">Cobertura estimada</p>
                            <p class="solar-metric-value {{ $coverageTone }}">
                                {{ $calculationResult ? $formatPercent($calculationResult->coverage_percentage) : 'Pendiente' }}
                            </p>
                            <p class="solar-metric-copy">Porcentaje del consumo anual cubierto por la generacion estimada.</p>
                        </div>
                        <div class="solar-metric-card min-w-0">
                            <p class="solar-metric-label">Ahorro anual</p>
                            <p class="solar-metric-value text-[color:var(--solar-text)]">
                                {{ $calculationResult ? $formatMoney($calculationResult->estimated_annual_savings_cop) : 'Pendiente' }}
                            </p>
                            <p class="solar-metric-copy">Impacto economico anual estimado.</p>
                        </div>
                        <div class="solar-metric-card min-w-0">
                            <p class="solar-metric-label">Generacion anual</p>
                            <p class="solar-metric-value text-[color:var(--solar-text)]">
                                {{ $calculationResult ? $formatKwh($calculationResult->estimated_annual_generation_kwh) : 'Pendiente' }}
                            </p>
                            <p class="solar-metric-copy">Energia solar proyectada para el ano.</p>
                        </div>
                        <div class="solar-metric-card min-w-0">
                            <p class="solar-metric-label">Balance anual</p>
                            <p class="solar-metric-value text-[color:var(--solar-text)]">
                                {{ $calculationResult ? $formatKwh($energyDifference) : 'Pendiente' }}
                            </p>
                            <p class="solar-metric-copy">Generacion estimada menos consumo anual.</p>
                        </div>
                        <div class="solar-metric-card min-w-0">
                            <p class="solar-metric-label">Capacidad instalada</p>
                            <p class="solar-metric-value text-[color:var(--solar-text)]">
                                {{ $calculationResult ? $formatKwp($calculationResult->installed_capacity_kwp) : 'Pendiente' }}
                            </p>
                            <p class="solar-metric-copy">
                                {{ $calculationResult ? number_format($calculationResult->number_of_panels, 0, ',', '.') . ' paneles' : 'Sin calculo disponible' }}
                            </p>
                        </div>
                        <div class="solar-metric-card min-w-0">
                            <p class="solar-metric-label">Consumo anual</p>
                            <p class="solar-metric-value text-[color:var(--solar-text)]">
                                {{ $calculationResult ? $formatKwh($calculationResult->annual_consumption_kwh) : $formatKwh($solarProject->annual_consumption_kwh) }}
                            </p>
                            <p class="solar-metric-copy">Base de demanda usada para el analisis.</p>
                        </div>
                    </div>

                    @unless ($hasWeatherData)
                        <div class="solar-alert solar-alert-warning mt-4">
                            Aun no hay datos NASA POWER almacenados. Cargalos desde Datos APIs antes de ejecutar la simulacion con NASA.
                        </div>
                    @endunless
                </div>

                @if ($monthlyResults->isNotEmpty())
                    <section class="solar-card w-full min-w-0 max-w-full">
                        <div>
                            <p class="solar-kicker">Analitica visual</p>
                            <h2 class="text-2xl text-[color:var(--solar-text)]">Graficos de resultados</h2>
                            <p class="solar-subtitle mt-2">Comparacion mensual de generacion, consumo, ahorro y cobertura.</p>
                        </div>

                        <div class="mt-4 grid min-w-0 gap-4 xl:grid-cols-2">
                            <div class="solar-chart-panel min-w-0">
                                <h3 class="text-sm font-semibold text-[color:var(--solar-text)]">Generacion mensual estimada</h3>
                                <div class="solar-chart-canvas mt-4 h-56 sm:h-64 lg:h-72">
                                    <canvas id="solar-generation-chart" aria-label="Generacion mensual estimada" role="img"></canvas>
                                </div>
                            </div>

                            <div class="solar-chart-panel min-w-0">
                                <h3 class="text-sm font-semibold text-[color:var(--solar-text)]">Consumo vs generacion</h3>
                                <div class="solar-chart-canvas mt-4 h-56 sm:h-64 lg:h-72">
                                    <canvas id="solar-consumption-generation-chart" aria-label="Consumo vs generacion" role="img"></canvas>
                                </div>
                            </div>

                            <div class="solar-chart-panel min-w-0">
                                <h3 class="text-sm font-semibold text-[color:var(--solar-text)]">Ahorro mensual estimado</h3>
                                <div class="solar-chart-canvas mt-4 h-56 sm:h-64 lg:h-72">
                                    <canvas id="solar-savings-chart" aria-label="Ahorro mensual estimado" role="img"></canvas>
                                </div>
                            </div>

                            <div class="solar-chart-panel min-w-0">
                                <h3 class="text-sm font-semibold text-[color:var(--solar-text)]">Cobertura energetica mensual</h3>
                                <div class="solar-chart-canvas mt-4 h-56 sm:h-64 lg:h-72">
                                    <canvas id="solar-coverage-chart" aria-label="Cobertura energetica mensual" role="img"></canvas>
                                </div>
                            </div>
                        </div>

                        <script type="application/json" id="solar-monthly-chart-data">@json($chartData)</script>
                    </section>

                    <section class="solar-card w-full min-w-0 max-w-full">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p class="solar-kicker">Desglose mensual</p>
                                <h2 class="text-2xl text-[color:var(--solar-text)]">Resultados mensuales</h2>
                                <p class="solar-subtitle mt-2">Detalle completo de produccion, cobertura y ahorro por mes.</p>
                            </div>
                        </div>

                        <div class="solar-table-shell mt-4 min-w-0 max-w-full">
                            <div class="min-w-0 max-w-full overflow-x-auto">
                            <table class="solar-table min-w-[760px]">
                                <thead>
                                    <tr>
                                        <th class="px-3 py-2">Mes</th>
                                        <th class="px-3 py-2">Dias</th>
                                        <th class="px-3 py-2">Radiacion diaria</th>
                                        <th class="px-3 py-2">Generacion</th>
                                        <th class="px-3 py-2">Consumo</th>
                                        <th class="px-3 py-2">Cobertura</th>
                                        <th class="px-3 py-2">Ahorro</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($monthlyResults as $monthlyResult)
                                        <tr>
                                            <td class="px-3 py-2 font-medium">{{ ucfirst($monthlyResult->month_name) }}</td>
                                            <td class="px-3 py-2">{{ $monthlyResult->days_in_month }}</td>
                                            <td class="px-3 py-2">{{ $formatNumber($monthlyResult->average_daily_solar_radiation) }} kWh/m2/dia</td>
                                            <td class="px-3 py-2">{{ $formatKwh($monthlyResult->estimated_generation_kwh) }}</td>
                                            <td class="px-3 py-2">{{ $formatKwh($monthlyResult->estimated_consumption_kwh) }}</td>
                                            <td class="px-3 py-2">{{ $formatPercent($monthlyResult->coverage_percentage) }}</td>
                                            <td class="px-3 py-2">{{ $formatMoney($monthlyResult->estimated_savings_cop) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="border-t border-[rgba(129,88,44,0.12)] text-sm font-semibold text-[color:var(--solar-text)]">
                                    <tr>
                                        <td class="px-3 py-3" colspan="3">Totales anuales</td>
                                        <td class="px-3 py-3">{{ $formatKwh($monthlyTotals['generation']) }}</td>
                                        <td class="px-3 py-3">{{ $formatKwh($monthlyTotals['consumption']) }}</td>
                                        <td class="px-3 py-3"></td>
                                        <td class="px-3 py-3">{{ $formatMoney($monthlyTotals['savings']) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                            </div>
                        </div>
                    </section>

                    @if ($weatherSupportsValue)
                        <section class="solar-card w-full min-w-0 max-w-full">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p class="solar-kicker">Clima y radiacion</p>
                                    <h2 class="text-2xl text-[color:var(--solar-text)]">Detalle meteorologico</h2>
                                    <p class="solar-subtitle mt-2">Se muestra solo porque aporta contexto a riesgo, radiacion o seguimiento operativo.</p>
                                </div>
                                <div class="solar-pill">
                                    {{ number_format($weatherStationStats['total'] ?? 0, 0, ',', '.') }} lecturas
                                </div>
                            </div>

                            <div class="mt-4 grid min-w-0 gap-4 lg:grid-cols-2">
                                <div class="solar-subcard">
                                    <p class="solar-metric-label">Lectura actual</p>
                                    <p class="mt-2 text-sm font-semibold text-[color:var(--solar-text)]">{{ $latestCurrentAnalysis }}</p>
                                    <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                                        <div class="solar-subcard">
                                            <dt class="solar-metric-label">Ultima medicion</dt>
                                            <dd class="mt-1 text-sm font-semibold text-[color:var(--solar-text)]">{{ $latestWeatherStationReading?->measured_at?->format('Y-m-d H:i') ?? 'Sin fecha' }}</dd>
                                        </div>
                                        <div class="solar-subcard">
                                            <dt class="solar-metric-label">Radiacion promedio</dt>
                                            <dd class="mt-1 text-sm font-semibold text-[color:var(--solar-text)]">{{ $formatNumber($weatherStationStats['averageRadiation'] ?? 0, 3) }}</dd>
                                        </div>
                                    </dl>
                                </div>

                                <div class="solar-subcard">
                                    <p class="solar-metric-label">Tendencia historica</p>
                                    <p class="mt-2 text-sm font-semibold text-[color:var(--solar-text)]">{{ $latestHistoricalAnalysis }}</p>
                                    <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                                        <div class="solar-subcard">
                                            <dt class="solar-metric-label">Indice UV maximo</dt>
                                            <dd class="mt-1 text-sm font-semibold text-[color:var(--solar-text)]">{{ $formatNumber($weatherStationStats['maxUvIndex'] ?? 0, 2) }}</dd>
                                        </div>
                                        <div class="solar-subcard">
                                            <dt class="solar-metric-label">Centro meteorologico</dt>
                                            <dd class="mt-1 text-sm font-semibold text-[color:var(--solar-text)]">{{ number_format($weatherStationStats['total'] ?? 0, 0, ',', '.') }} lecturas</dd>
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
                                    <div class="min-w-0 max-w-full overflow-x-auto">
                                        <table class="min-w-[640px] divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
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
                @else
                    <div class="solar-alert solar-alert-warning">
                        {{ $calculationResult ? 'Los calculos generales existen, pero aun no hay resultados mensuales registrados.' : 'Ejecuta los calculos solares para visualizar los resultados y graficos del proyecto.' }}
                    </div>
                @endif
            </section>

            <aside class="min-w-0 space-y-6">
                <section class="solar-card w-full min-w-0 max-w-full">
                    <div>
                        <p class="solar-kicker">Workflow</p>
                        <h2 class="text-2xl text-[color:var(--solar-text)]">Acciones</h2>
                        <p class="solar-subtitle mt-2">Flujo operativo recomendado para actualizar el proyecto.</p>
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
                        <p class="solar-subtitle mt-2">Base tecnica y operativa usada para interpretar los resultados.</p>
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
                            <dt>Datos NASA</dt>
                            <dd>{{ number_format($solarProject->weather_data_count, 0, ',', '.') }} registros</dd>
                        </div>
                        <div class="solar-data-row">
                            <dt>Centro meteorologico</dt>
                            <dd>{{ number_format($weatherStationStats['total'] ?? 0, 0, ',', '.') }} lecturas</dd>
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
                                <dd>{{ $formatPercent($technicalParameter->usable_area_percentage) }}</dd>
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
                                <dd>{{ $formatPercent($technicalParameter->system_losses_percentage) }}</dd>
                            </div>
                        </dl>
                    @else
                        <div class="solar-alert solar-alert-warning mt-4">
                            Este proyecto aun no tiene parametros tecnicos registrados.
                        </div>
                    @endif
                </section>

                @if ($monthlyResults->isNotEmpty())
                    <section class="solar-card w-full min-w-0 max-w-full">
                        <div>
                            <p class="solar-kicker">Estacionalidad</p>
                            <h2 class="text-2xl text-[color:var(--solar-text)]">Mejores y peores meses</h2>
                            <p class="solar-subtitle mt-2">Resumen rapido para detectar estacionalidad.</p>
                        </div>

                        <dl class="solar-data-list mt-4 text-sm">
                            <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                                <dt class="text-zinc-500 dark:text-zinc-400">Mayor generacion estimada</dt>
                                <dd class="mt-1 font-semibold text-zinc-950 dark:text-zinc-50">{{ ucfirst($monthlyHighlights['highestGeneration']->month_name) }} · {{ $formatKwh($monthlyHighlights['highestGeneration']->estimated_generation_kwh) }}</dd>
                            </div>
                            <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                                <dt class="text-zinc-500 dark:text-zinc-400">Menor generacion estimada</dt>
                                <dd class="mt-1 font-semibold text-zinc-950 dark:text-zinc-50">{{ ucfirst($monthlyHighlights['lowestGeneration']->month_name) }} · {{ $formatKwh($monthlyHighlights['lowestGeneration']->estimated_generation_kwh) }}</dd>
                            </div>
                            <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                                <dt class="text-zinc-500 dark:text-zinc-400">Mayor ahorro estimado</dt>
                                <dd class="mt-1 font-semibold text-zinc-950 dark:text-zinc-50">{{ ucfirst($monthlyHighlights['highestSavings']->month_name) }} · {{ $formatMoney($monthlyHighlights['highestSavings']->estimated_savings_cop) }}</dd>
                            </div>
                            <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                                <dt class="text-zinc-500 dark:text-zinc-400">Menor cobertura energetica</dt>
                                <dd class="mt-1 font-semibold text-zinc-950 dark:text-zinc-50">{{ ucfirst($monthlyHighlights['lowestCoverage']->month_name) }} · {{ $formatPercent($monthlyHighlights['lowestCoverage']->coverage_percentage) }}</dd>
                            </div>
                        </dl>
                    </section>
                @endif
            </aside>
        </div>
    </div>
</x-layouts::app>
