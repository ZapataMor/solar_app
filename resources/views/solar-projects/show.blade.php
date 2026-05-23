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
    <div class="w-full min-w-0 max-w-full space-y-6 overflow-x-hidden">
        <div class="w-full min-w-0 max-w-full rounded-2xl border border-zinc-200 bg-white p-4 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0 max-w-3xl">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-600 dark:text-amber-400">Dashboard Solar</p>
                    <h1 class="mt-2 text-2xl font-semibold text-zinc-950 sm:text-3xl dark:text-zinc-50">{{ $solarProject->name }}</h1>
                    <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                        {{ $solarProject->location_name }} · {{ $solarProject->start_date->format('Y-m-d') }} al {{ $solarProject->end_date->format('Y-m-d') }}
                    </p>
                    <p class="mt-4 max-w-2xl text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                        {{ $solarProject->description ?: 'Proyecto sin descripcion registrada.' }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('solar-projects.edit', $solarProject) }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">
                        Editar proyecto
                    </a>
                    <a href="{{ route('solar-projects.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">
                        Volver al listado
                    </a>
                </div>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/60">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Periodo</p>
                    <p class="mt-2 text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ $solarProject->start_date->format('Y-m-d') }} al {{ $solarProject->end_date->format('Y-m-d') }}</p>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/60">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Consumo base</p>
                    <p class="mt-2 text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ $formatKwh($solarProject->annual_consumption_kwh) }}</p>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/60">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Datos NASA</p>
                    <p class="mt-2 text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ number_format($solarProject->weather_data_count, 0, ',', '.') }} registros</p>
                </div>

                <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/60">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Centro meteorologico</p>
                    <p class="mt-2 text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ number_format($weatherStationStats['total'] ?? 0, 0, ',', '.') }} lecturas</p>
                </div>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->has('weather_data'))
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200">
                {{ $errors->first('weather_data') }}
            </div>
        @endif

        @if ($errors->has('weather_station'))
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200">
                {{ $errors->first('weather_station') }}
            </div>
        @endif

        @if ($errors->has('solar_calculation'))
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200">
                {{ $errors->first('solar_calculation') }}
            </div>
        @endif

        <div class="grid min-w-0 max-w-full gap-6 xl:grid-cols-[minmax(0,1.45fr)_minmax(0,0.95fr)]">
            <section class="min-w-0 space-y-6">
                <div class="w-full min-w-0 max-w-full rounded-2xl border border-zinc-200 bg-white p-4 sm:p-6 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 max-w-3xl">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-amber-600 dark:text-amber-400">Resumen ejecutivo principal</p>
                            <h2 class="mt-2 text-xl font-semibold text-zinc-950 dark:text-zinc-50 sm:text-2xl">Lectura general del proyecto</h2>
                            <p class="mt-3 text-sm leading-6 text-zinc-700 dark:text-zinc-200">
                                {{ $canonicalExecutiveSummary }}
                            </p>
                        </div>

                        <div class="rounded-full border border-zinc-200 px-3 py-1 text-xs font-medium text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">
                            {{ $executiveSummary['enabled'] ? 'Resumen IA opcional' : 'Resumen consolidado por reglas' }}
                        </div>
                    </div>

                    <div class="mt-6 grid gap-4 lg:grid-cols-3">
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-950/60">
                            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Estado energetico general</p>
                            <p class="mt-2 text-lg font-semibold {{ $coverageTone }}">
                                {{ $dashboard['state']['title'] ?? 'Estado pendiente' }}
                            </p>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $canonicalStateSummary }}</p>
                        </div>

                        <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900/60 dark:bg-red-950/30">
                            <p class="text-xs font-medium uppercase tracking-wide text-red-600 dark:text-red-300">Riesgo principal</p>
                            <p class="mt-2 text-sm font-semibold text-red-950 dark:text-red-100">{{ $canonicalRisk }}</p>
                            <p class="mt-2 text-sm text-red-800 dark:text-red-200">Es el punto que mas puede limitar cobertura, ahorro o continuidad operativa.</p>
                        </div>

                        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 dark:border-sky-900/60 dark:bg-sky-950/30">
                            <p class="text-xs font-medium uppercase tracking-wide text-sky-700 dark:text-sky-300">Recomendacion principal</p>
                            <p class="mt-2 text-sm font-semibold text-sky-950 dark:text-sky-100">{{ $canonicalRecommendation }}</p>
                            <p class="mt-2 text-sm text-sky-800 dark:text-sky-200">Accion prioritaria para mejorar el resultado operativo del sistema.</p>
                        </div>
                    </div>

                    @if ($supportingSignals->isNotEmpty())
                        <div class="mt-5 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Senales de soporte</p>
                            <ul class="mt-3 space-y-2 text-sm text-zinc-600 dark:text-zinc-300">
                                @foreach ($supportingSignals as $signal)
                                    <li>{{ $signal }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <div class="w-full min-w-0 max-w-full rounded-2xl border border-zinc-200 bg-white p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold text-zinc-950 dark:text-zinc-50">Indicadores clave</h2>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Metrica ejecutiva del proyecto y del dimensionamiento.</p>
                        </div>
                    </div>

                    <div class="mt-4 grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <div class="min-w-0 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Cobertura estimada</p>
                            <p class="mt-2 text-2xl font-semibold {{ $coverageTone }}">
                                {{ $calculationResult ? $formatPercent($calculationResult->coverage_percentage) : 'Pendiente' }}
                            </p>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Porcentaje del consumo anual cubierto por la generacion estimada.</p>
                        </div>
                        <div class="min-w-0 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Ahorro anual</p>
                            <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-zinc-50">
                                {{ $calculationResult ? $formatMoney($calculationResult->estimated_annual_savings_cop) : 'Pendiente' }}
                            </p>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Impacto economico anual estimado.</p>
                        </div>
                        <div class="min-w-0 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Generacion anual</p>
                            <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-zinc-50">
                                {{ $calculationResult ? $formatKwh($calculationResult->estimated_annual_generation_kwh) : 'Pendiente' }}
                            </p>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Energia solar proyectada para el ano.</p>
                        </div>
                        <div class="min-w-0 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Balance anual</p>
                            <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-zinc-50">
                                {{ $calculationResult ? $formatKwh($energyDifference) : 'Pendiente' }}
                            </p>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Generacion estimada menos consumo anual.</p>
                        </div>
                        <div class="min-w-0 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Capacidad instalada</p>
                            <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-zinc-50">
                                {{ $calculationResult ? $formatKwp($calculationResult->installed_capacity_kwp) : 'Pendiente' }}
                            </p>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ $calculationResult ? number_format($calculationResult->number_of_panels, 0, ',', '.') . ' paneles' : 'Sin calculo disponible' }}
                            </p>
                        </div>
                        <div class="min-w-0 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Consumo anual</p>
                            <p class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-zinc-50">
                                {{ $calculationResult ? $formatKwh($calculationResult->annual_consumption_kwh) : $formatKwh($solarProject->annual_consumption_kwh) }}
                            </p>
                            <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">Base de demanda usada para el analisis.</p>
                        </div>
                    </div>

                    @unless ($hasWeatherData)
                        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                            Aun no hay datos NASA POWER almacenados. Cargalos desde Datos APIs antes de ejecutar la simulacion con NASA.
                        </div>
                    @endunless
                </div>

                @if ($monthlyResults->isNotEmpty())
                    <section class="w-full min-w-0 max-w-full rounded-2xl border border-zinc-200 bg-white p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <div>
                            <h2 class="text-base font-semibold text-zinc-950 dark:text-zinc-50">Graficos de resultados</h2>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Comparacion mensual de generacion, consumo, ahorro y cobertura.</p>
                        </div>

                        <div class="mt-4 grid min-w-0 gap-4 xl:grid-cols-2">
                            <div class="min-w-0 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                <h3 class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">Generacion mensual estimada</h3>
                                <div class="mt-4 h-56 sm:h-64 lg:h-72">
                                    <canvas id="solar-generation-chart" aria-label="Generacion mensual estimada" role="img"></canvas>
                                </div>
                            </div>

                            <div class="min-w-0 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                <h3 class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">Consumo vs generacion</h3>
                                <div class="mt-4 h-56 sm:h-64 lg:h-72">
                                    <canvas id="solar-consumption-generation-chart" aria-label="Consumo vs generacion" role="img"></canvas>
                                </div>
                            </div>

                            <div class="min-w-0 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                <h3 class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">Ahorro mensual estimado</h3>
                                <div class="mt-4 h-56 sm:h-64 lg:h-72">
                                    <canvas id="solar-savings-chart" aria-label="Ahorro mensual estimado" role="img"></canvas>
                                </div>
                            </div>

                            <div class="min-w-0 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                <h3 class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">Cobertura energetica mensual</h3>
                                <div class="mt-4 h-56 sm:h-64 lg:h-72">
                                    <canvas id="solar-coverage-chart" aria-label="Cobertura energetica mensual" role="img"></canvas>
                                </div>
                            </div>
                        </div>

                        <script type="application/json" id="solar-monthly-chart-data">@json($chartData)</script>
                    </section>

                    <section class="w-full min-w-0 max-w-full rounded-2xl border border-zinc-200 bg-white p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 class="text-base font-semibold text-zinc-950 dark:text-zinc-50">Resultados mensuales</h2>
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Detalle completo de produccion, cobertura y ahorro por mes.</p>
                            </div>
                        </div>

                        <div class="mt-4 min-w-0 max-w-full overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                            <div class="min-w-0 max-w-full overflow-x-auto">
                            <table class="min-w-[760px] divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                                <thead class="bg-zinc-50 dark:bg-zinc-950/60">
                                    <tr class="text-left text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                        <th class="px-3 py-2">Mes</th>
                                        <th class="px-3 py-2">Dias</th>
                                        <th class="px-3 py-2">Radiacion diaria</th>
                                        <th class="px-3 py-2">Generacion</th>
                                        <th class="px-3 py-2">Consumo</th>
                                        <th class="px-3 py-2">Cobertura</th>
                                        <th class="px-3 py-2">Ahorro</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                    @foreach ($monthlyResults as $monthlyResult)
                                        <tr class="text-zinc-700 dark:text-zinc-200">
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
                                <tfoot class="border-t border-zinc-200 text-sm font-semibold text-zinc-950 dark:border-zinc-700 dark:text-zinc-50">
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
                        <section class="w-full min-w-0 max-w-full rounded-2xl border border-zinc-200 bg-white p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-900">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <h2 class="text-base font-semibold text-zinc-950 dark:text-zinc-50">Detalle meteorologico</h2>
                                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Se muestra solo porque aporta contexto a riesgo, radiacion o seguimiento operativo.</p>
                                </div>
                                <div class="rounded-full bg-zinc-100 px-3 py-1 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ number_format($weatherStationStats['total'] ?? 0, 0, ',', '.') }} lecturas
                                </div>
                            </div>

                            <div class="mt-4 grid min-w-0 gap-4 lg:grid-cols-2">
                                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Lectura actual</p>
                                    <p class="mt-2 text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ $latestCurrentAnalysis }}</p>
                                    <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                                        <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                                            <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Ultima medicion</dt>
                                            <dd class="mt-1 text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ $latestWeatherStationReading?->measured_at?->format('Y-m-d H:i') ?? 'Sin fecha' }}</dd>
                                        </div>
                                        <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                                            <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Radiacion promedio</dt>
                                            <dd class="mt-1 text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ $formatNumber($weatherStationStats['averageRadiation'] ?? 0, 3) }}</dd>
                                        </div>
                                    </dl>
                                </div>

                                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Tendencia historica</p>
                                    <p class="mt-2 text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ $latestHistoricalAnalysis }}</p>
                                    <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                                        <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                                            <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Indice UV maximo</dt>
                                            <dd class="mt-1 text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ $formatNumber($weatherStationStats['maxUvIndex'] ?? 0, 2) }}</dd>
                                        </div>
                                        <div class="rounded-lg bg-zinc-50 p-3 dark:bg-zinc-950/60">
                                            <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Centro meteorologico</dt>
                                            <dd class="mt-1 text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ number_format($weatherStationStats['total'] ?? 0, 0, ',', '.') }} lecturas</dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>

                            <div class="mt-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                                <div class="h-56 min-w-0 max-w-full sm:h-64 lg:h-72 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-950/60">
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
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                        {{ $calculationResult ? 'Los calculos generales existen, pero aun no hay resultados mensuales registrados.' : 'Ejecuta los calculos solares para visualizar los resultados y graficos del proyecto.' }}
                    </div>
                @endif
            </section>

            <aside class="min-w-0 space-y-6">
                <section class="w-full min-w-0 max-w-full rounded-2xl border border-zinc-200 bg-white p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div>
                        <h2 class="text-base font-semibold text-zinc-950 dark:text-zinc-50">Acciones</h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Flujo operativo recomendado para actualizar el proyecto.</p>
                    </div>

                    <div class="mt-4 space-y-3">
                        <form method="POST" action="{{ route('solar-projects.calculate-weather-station', $solarProject) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-xl bg-amber-500 px-4 py-3 text-sm font-medium text-white hover:bg-amber-600 dark:bg-amber-400 dark:text-zinc-950 dark:hover:bg-amber-300">
                                Ejecutar datos con estacion
                            </button>
                        </form>

                        <form method="POST" action="{{ route('solar-projects.calculate', $solarProject) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-xl bg-zinc-900 px-4 py-3 text-sm font-medium text-white hover:bg-zinc-700 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200">
                                Ejecutar datos con NASA
                            </button>
                        </form>

                        <a href="{{ route('api-data.index') }}" class="block w-full rounded-xl border border-zinc-300 px-4 py-3 text-center text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">
                            Ir a Datos APIs
                        </a>
                    </div>
                </section>

                <section class="w-full min-w-0 max-w-full rounded-2xl border border-zinc-200 bg-white p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-900">
                    <div>
                        <h2 class="text-base font-semibold text-zinc-950 dark:text-zinc-50">Contexto del proyecto</h2>
                        <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Base tecnica y operativa usada para interpretar los resultados.</p>
                    </div>

                    <dl class="mt-4 space-y-3">
                        <div class="flex items-center justify-between gap-3 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-950/60">
                            <dt class="text-sm text-zinc-600 dark:text-zinc-400">Ubicacion</dt>
                            <dd class="text-right text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ $solarProject->location_name }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-950/60">
                            <dt class="text-sm text-zinc-600 dark:text-zinc-400">Coordenadas</dt>
                            <dd class="text-right text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ $formatCoordinate($solarProject->latitude) }}, {{ $formatCoordinate($solarProject->longitude) }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-950/60">
                            <dt class="text-sm text-zinc-600 dark:text-zinc-400">Tarifa energetica</dt>
                            <dd class="text-right text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ $formatMoney($solarProject->energy_rate_cop_kwh) }}/kWh</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-950/60">
                            <dt class="text-sm text-zinc-600 dark:text-zinc-400">Datos NASA</dt>
                            <dd class="text-right text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ number_format($solarProject->weather_data_count, 0, ',', '.') }} registros</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-950/60">
                            <dt class="text-sm text-zinc-600 dark:text-zinc-400">Centro meteorologico</dt>
                            <dd class="text-right text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ number_format($weatherStationStats['total'] ?? 0, 0, ',', '.') }} lecturas</dd>
                        </div>
                    </dl>

                    @if ($technicalParameter)
                        <dl class="mt-4 space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <div class="flex items-center justify-between gap-3 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-950/60">
                                <dt class="text-sm text-zinc-600 dark:text-zinc-400">Area disponible</dt>
                                <dd class="text-right text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ $formatNumber($technicalParameter->available_area_m2) }} m2</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-950/60">
                                <dt class="text-sm text-zinc-600 dark:text-zinc-400">Area utilizable</dt>
                                <dd class="text-right text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ $formatPercent($technicalParameter->usable_area_percentage) }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-950/60">
                                <dt class="text-sm text-zinc-600 dark:text-zinc-400">Potencia por panel</dt>
                                <dd class="text-right text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ $formatNumber($technicalParameter->panel_power_w) }} W</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-950/60">
                                <dt class="text-sm text-zinc-600 dark:text-zinc-400">Area del panel</dt>
                                <dd class="text-right text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ $formatNumber($technicalParameter->panel_area_m2) }} m2</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-950/60">
                                <dt class="text-sm text-zinc-600 dark:text-zinc-400">Performance ratio</dt>
                                <dd class="text-right text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ $formatNumber($technicalParameter->performance_ratio, 3) }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-950/60">
                                <dt class="text-sm text-zinc-600 dark:text-zinc-400">Perdidas del sistema</dt>
                                <dd class="text-right text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ $formatPercent($technicalParameter->system_losses_percentage) }}</dd>
                            </div>
                        </dl>
                    @else
                        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                            Este proyecto aun no tiene parametros tecnicos registrados.
                        </div>
                    @endif
                </section>

                @if ($monthlyResults->isNotEmpty())
                    <section class="w-full min-w-0 max-w-full rounded-2xl border border-zinc-200 bg-white p-4 sm:p-5 dark:border-zinc-700 dark:bg-zinc-900">
                        <div>
                            <h2 class="text-base font-semibold text-zinc-950 dark:text-zinc-50">Mejores y peores meses</h2>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Resumen rapido para detectar estacionalidad.</p>
                        </div>

                        <dl class="mt-4 space-y-3 text-sm">
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
