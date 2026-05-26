@php
    $technicalParameter = $solarProject->technicalParameter;
    $calculationResult = $solarProject->calculationResult;
    $timeScales = $timeScales ?? ['defaultScale' => 'monthly', 'scales' => [], 'activeScale' => null];
    $activeScaleKey = $timeScales['defaultScale'] ?? 'monthly';
    $activeScale = $timeScales['activeScale'] ?? ($timeScales['scales'][$activeScaleKey] ?? null);
    $dashboard = $dashboard ?? ['executiveSummary' => ['enabled' => false]];
    $futurePredictions = $dashboard['futurePredictions'] ?? [];
    $generateAiRecommendations = (bool) ($generateAiRecommendations ?? false);
    $aiFocus = (string) ($aiFocus ?? 'savings');
    $aiFocusOptions = [
        'savings' => 'Ahorro economico',
        'load_shift' => 'Traslado de cargas',
        'risk' => 'Riesgo operativo',
        'maintenance' => 'Mantenimiento',
        'climate' => 'Adaptacion climatica',
    ];
    $aiSelectedPackItem = collect($dashboard['executiveSummary']['recommendationPack'] ?? [])
        ->firstWhere('key', $aiFocus);
    $weatherStationStats = $weatherStationStats ?? [];
    $recentWeatherStationReadings = $recentWeatherStationReadings ?? collect();

    $coverage = $calculationResult ? (float) $calculationResult->coverage_percentage : null;
    $annualSavings = $calculationResult ? (float) $calculationResult->estimated_annual_savings_cop : null;

    $coverageTone = $coverage === null ? 'warn' : ($coverage >= 100 ? 'success' : ($coverage >= 70 ? 'warn' : 'danger'));
    $coverageLabel = $coverage === null ? 'Pendiente' : number_format($coverage, 1, ',', '.') . '%';

    $riskText = (string) ($activeScale['risk'] ?? 'Sin riesgo critico detectado.');
    $riskTone = str_contains(strtolower($riskText), 'crit') ? 'danger' : (filled($riskText) ? 'warn' : 'success');
    $averageRadiation = isset($weatherStationStats['averageRadiation']) ? (float) $weatherStationStats['averageRadiation'] : null;
    $latestUvIndex = isset($weatherStationStats['maxUvIndex']) ? (float) $weatherStationStats['maxUvIndex'] : null;
    $heroScene = $averageRadiation !== null && $averageRadiation >= 550
        ? 'high'
        : (($averageRadiation !== null && $averageRadiation >= 250) ? 'medium' : 'low');
    $heroSceneLabel = match ($heroScene) {
        'high' => 'Radiacion excelente',
        'medium' => 'Radiacion moderada',
        default => 'Radiacion baja',
    };
    $heroSceneSubtitle = match ($heroScene) {
        'high' => 'Produccion optima para autoconsumo',
        'medium' => 'Produccion estable con variaciones',
        default => 'Produccion reducida y mayor dependencia de red',
    };
    $heroStatusLabel = match ($heroScene) {
        'high' => 'Despejado',
        'medium' => 'Parcialmente nublado',
        default => 'Nublado',
    };

    $formatNumber = fn ($value, int $decimals = 2) => number_format((float) $value, $decimals, ',', '.');
    $formatKwh = fn ($value) => $formatNumber($value) . ' kWh';
    $formatMoney = fn ($value) => '$ ' . number_format((float) $value, 0, ',', '.') . ' COP';
    $installedCapacityLabel = $calculationResult?->installed_capacity_kwp
        ? $formatNumber($calculationResult->installed_capacity_kwp) . ' kWp'
        : 'Pendiente';
    $usableAreaLabel = $calculationResult?->usable_area_m2
        ? $formatNumber($calculationResult->usable_area_m2) . ' m2'
        : ($technicalParameter?->available_area_m2 ? $formatNumber($technicalParameter->available_area_m2) . ' m2' : 'Pendiente');
    $tariffValue = $solarProject->energy_rate_cop_per_kwh ?? $solarProject->energy_rate_cop_kwh ?? null;
    $heroCloudiness = match ($heroScene) {
        'high' => 8,
        'medium' => 45,
        default => 88,
    };
    $heroEfficiency = match ($heroScene) {
        'high' => 94,
        'medium' => 58,
        default => 24,
    };
    $installedCapacityValue = $calculationResult?->installed_capacity_kwp ? (float) $calculationResult->installed_capacity_kwp : 13.2;
    $heroProduction = match ($heroScene) {
        'high' => $installedCapacityValue * 0.94,
        'medium' => $installedCapacityValue * 0.54,
        default => $installedCapacityValue * 0.2,
    };
    $heroTrendWeights = [5, 10, 22, 39, 58, 75, 88, 94, 91, 80, 62, 42, 22, 10, 5];

    $badgeClass = fn ($tone) => match ($tone) {
        'success' => 'solar-pill-success',
        'warn' => 'solar-pill-warn',
        'danger' => 'solar-pill-danger',
        default => '',
    };

    $tableRows = $recentWeatherStationReadings
        ->sortByDesc('measured_at')
        ->values()
        ->map(fn ($reading) => [
            'fecha' => $reading->measured_at?->format('Y-m-d H:i') ?? 'N/A',
            'radiacion' => $reading->radiationValue() !== null ? number_format((float) $reading->radiationValue(), 2, '.', '') : null,
            'uva' => $reading->uva !== null ? number_format((float) $reading->uva, 3, '.', '') : null,
            'uvb' => $reading->uvb !== null ? number_format((float) $reading->uvb, 3, '.', '') : null,
            'iuv' => $reading->uv_index !== null ? number_format((float) $reading->uv_index, 3, '.', '') : null,
            'temperature' => $reading->temperature !== null ? number_format((float) $reading->temperature, 2, '.', '') : null,
            'humidity' => $reading->humidity !== null ? number_format((float) $reading->humidity, 2, '.', '') : null,
            'co2' => $reading->co2,
            'pm25' => $reading->pm25 !== null ? number_format((float) $reading->pm25, 2, '.', '') : null,
            'pm10' => $reading->pm10 !== null ? number_format((float) $reading->pm10, 2, '.', '') : null,
        ])
        ->all();
@endphp

<x-layouts::app :title="$solarProject->name">
    <div class="solar-page" x-data="{ showDataModal: false, filter: '' }">
        @if (session('status'))
            <div class="solar-alert solar-alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="solar-alert solar-alert-danger">{{ $errors->first() }}</div>
        @endif

        <section class="solar-live-dashboard solar-hero-scene-{{ $heroScene }}">
            <header class="solar-live-header">
                <div>
                    <p class="solar-live-kicker">Condicion solar en tiempo real</p>
                    <p class="solar-live-meta">{{ $solarProject->updated_at?->format('d M Y - H:i') }} COT - {{ $calculationResult?->estimated_panels ?? 24 }} paneles - {{ $installedCapacityLabel }}</p>
                </div>

                <div class="solar-live-controls" aria-label="Estado solar actual">
                    <span class="solar-live-control {{ $heroScene === 'high' ? 'is-active' : '' }}">Alta</span>
                    <span class="solar-live-control {{ $heroScene === 'medium' ? 'is-active' : '' }}">Media</span>
                    <span class="solar-live-control {{ $heroScene === 'low' ? 'is-active' : '' }}">Baja</span>
                    <span class="solar-live-control is-auto">Auto</span>
                </div>
            </header>

            <div class="solar-live-panel">
                <aside class="solar-live-stats">
                    <div class="solar-live-status">
                        <div class="solar-live-lights" aria-hidden="true">
                            <span></span>
                            <span class="is-current"></span>
                            <span></span>
                        </div>
                        <div>
                            <h1>{{ $heroSceneLabel }}</h1>
                            <p>{{ $heroSceneSubtitle }}</p>
                        </div>
                    </div>

                    <div class="solar-live-reading">
                        <p>Irradiancia solar</p>
                        <strong>{{ $averageRadiation !== null ? $formatNumber($averageRadiation, 0) : 'N/A' }}</strong>
                        <span>W/m2</span>
                    </div>

                    <div class="solar-live-kpis">
                        <article>
                            <p>Produccion</p>
                            <strong>{{ $formatNumber($heroProduction, 1) }}<span>kW</span></strong>
                        </article>
                        <article>
                            <p>Nubosidad</p>
                            <strong>{{ $heroCloudiness }}<span>%</span></strong>
                        </article>
                        <article>
                            <p>Estado</p>
                            <strong class="solar-live-state">{{ $heroScene === 'high' ? 'Sistema estable' : ($heroScene === 'medium' ? 'Produccion variable' : 'Capacidad reducida') }}</strong>
                        </article>
                    </div>

                    <div class="solar-live-efficiency">
                        <div>
                            <span>Eficiencia del sistema</span>
                            <strong>{{ $heroEfficiency }}%</strong>
                        </div>
                        <i style="--efficiency: {{ $heroEfficiency }}%"></i>
                    </div>
                </aside>

                <section class="solar-live-sky">
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
                        <span></span>
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

        <section class="solar-project-context">
            <div class="solar-project-context__copy">
              
                <h1 class="solar-title">{{ $solarProject->name }}</h1>
                <p class="solar-subtitle">{{ $solarProject->location_name }}</p>
                <p class="solar-project-context__description">{{ $solarProject->description ?: 'Proyecto sin descripcion.' }}</p>

                <div class="solar-project-context__badges">
                    <span class="solar-pill {{ $badgeClass($coverageTone) }}">Cobertura {{ $coverageLabel }}</span>
                    <span class="solar-pill {{ $badgeClass($riskTone) }}">Riesgo {{ $riskTone === 'danger' ? 'Alto' : ($riskTone === 'warn' ? 'Moderado' : 'Bajo') }}</span>
                    <span class="solar-pill {{ $badgeClass(($dashboard['executiveSummary']['enabled'] ?? false) ? 'success' : 'warn') }}">
                        IA {{ ($dashboard['executiveSummary']['enabled'] ?? false) ? 'Activa' : 'Pendiente' }}
                    </span>
                </div>
            </div>

            <div class="solar-project-context__actions">
                <a href="{{ route('solar-projects.edit', $solarProject) }}" class="solar-button-secondary">Editar</a>
                <form method="POST" action="{{ route('solar-projects.fetch-weather-data', $solarProject) }}">@csrf<button class="solar-button-ghost" type="submit">Sincronizar NASA</button></form>
                <form method="POST" action="{{ route('solar-projects.calculate-weather-station', $solarProject) }}">@csrf<button class="solar-button" type="submit">Recalcular dashboard</button></form>
                <form method="POST" action="{{ route('solar-projects.destroy', $solarProject) }}" onsubmit="return confirm('¿Eliminar proyecto?');">@csrf @method('DELETE')<button class="solar-button-danger" type="submit">Eliminar</button></form>
                <a href="{{ route('solar-projects.index') }}" class="solar-button-ghost">Volver</a>
            </div>
        </section>

        <section class="solar-hero solar-hero-split solar-hero-scene-{{ $heroScene }}">
            <div class="solar-hero-top">
                <div class="solar-hero-copy">
                    {{-- <p class="solar-kicker">Dashboard solar operativo</p> --}}
                    <h1 class="solar-title">{{ $solarProject->name }}</h1>
                    <p class="solar-subtitle">{{ $solarProject->location_name }} · Actualizado {{ $solarProject->updated_at?->format('d/m/Y H:i') }}</p>
                    <p class="mt-3 text-sm text-[color:var(--solar-text-muted)]">{{ $solarProject->description ?: 'Proyecto sin descripcion.' }}</p>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="solar-pill {{ $badgeClass($coverageTone) }}">Cobertura {{ $coverageLabel }}</span>
                        <span class="solar-pill {{ $badgeClass($riskTone) }}">Riesgo {{ $riskTone === 'danger' ? 'Alto' : ($riskTone === 'warn' ? 'Moderado' : 'Bajo') }}</span>
                        <span class="solar-pill {{ $badgeClass(($dashboard['executiveSummary']['enabled'] ?? false) ? 'success' : 'warn') }}">
                            IA {{ ($dashboard['executiveSummary']['enabled'] ?? false) ? 'Activa' : 'Pendiente' }}
                        </span>
                    </div>
                </div>

                <div class="solar-hero-scene-card">
                    <div class="solar-hero-scene-viewport">
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
                        <div class="solar-hero-chart">
                            <div class="solar-hero-chart-grid">
                                @for ($bar = 0; $bar < 12; $bar++)
                                    <span style="--bar-height: {{ [12, 24, 38, 54, 72, 90, 100, 92, 76, 58, 34, 18][$bar] }}%"></span>
                                @endfor
                            </div>
                        </div>
                    </div>

                    <div class="solar-hero-scene-meta">
                        <div>
                            <p class="solar-hero-scene-label">{{ $heroSceneLabel }}</p>
                            <p class="solar-hero-scene-copy">{{ $heroSceneSubtitle }}</p>
                        </div>
                        <div class="solar-hero-scene-stat">
                            <span>{{ $heroStatusLabel }}</span>
                            <strong>{{ $averageRadiation !== null ? $formatNumber($averageRadiation, 0) : 'N/A' }} W/m2</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="solar-hero-bottom">
                <div class="solar-hero-bottom-grid">
                    <div class="solar-hero-metrics">
                        <article class="solar-metric-card"><p class="solar-metric-label">Consumo mensual</p><p class="solar-metric-value">{{ $formatKwh($solarProject->monthlyConsumption()) }}</p></article>
                        <article class="solar-metric-card"><p class="solar-metric-label">Tarifa energetica</p><p class="solar-metric-value">{{ $tariffValue !== null ? '$ '.number_format((float) $tariffValue, 0, ',', '.').' COP' : 'Pendiente' }}</p></article>
                        <article class="solar-metric-card"><p class="solar-metric-label">Capacidad instalada</p><p class="solar-metric-value">{{ $installedCapacityLabel }}</p></article>
                        <article class="solar-metric-card"><p class="solar-metric-label">Area utilizable</p><p class="solar-metric-value">{{ $usableAreaLabel }}</p></article>
                        <article class="solar-metric-card"><p class="solar-metric-label">Lecturas locales</p><p class="solar-metric-value">{{ number_format($weatherStationStats['total'] ?? 0, 0, ',', '.') }}</p></article>
                        <article class="solar-metric-card"><p class="solar-metric-label">Indice UV maximo</p><p class="solar-metric-value">{{ $latestUvIndex !== null ? $formatNumber($latestUvIndex, 1) : 'N/A' }}</p></article>
                    </div>

                    <div class="solar-hero-actions-panel">
                        <div>
                            <p class="solar-kicker">Acciones del contenedor</p>
                            <h2 class="text-2xl text-[color:var(--solar-text)]">Control rapido del proyecto</h2>
                            <p class="solar-subtitle mt-2">La parte superior muestra la animacion y aqui abajo se mantienen los datos y acciones operativas del dashboard.</p>
                        </div>

                        <div class="solar-hero-actions">
                            <a href="{{ route('solar-projects.edit', $solarProject) }}" class="solar-button-secondary">Editar</a>
                            <form method="POST" action="{{ route('solar-projects.fetch-weather-data', $solarProject) }}">@csrf<button class="solar-button-ghost" type="submit">Sincronizar NASA</button></form>
                            <form method="POST" action="{{ route('solar-projects.calculate-weather-station', $solarProject) }}">@csrf<button class="solar-button" type="submit">Recalcular dashboard</button></form>
                            <form method="POST" action="{{ route('solar-projects.destroy', $solarProject) }}" onsubmit="return confirm('¿Eliminar proyecto?');">@csrf @method('DELETE')<button class="solar-button-danger" type="submit">Eliminar</button></form>
                            <a href="{{ route('solar-projects.index') }}" class="solar-button-ghost">Volver</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="solar-card mt-6">
            <div class="solar-page-header">
                <div>
                    <p class="solar-kicker">Estado energetico actual</p>
                    <h2 class="text-2xl text-[color:var(--solar-text)]">Centro operativo</h2>
                    <p class="solar-subtitle mt-2" data-scale-summary>{{ $activeScale['summary'] ?? 'Sin lectura operativa disponible.' }}</p>
                </div>
                <span class="solar-pill" data-scale-range-label>{{ $activeScale['rangeLabel'] ?? 'Periodo activo' }}</span>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-3">
                <article class="solar-recommendation-card">
                    <div class="solar-recommendation-card-head"><p class="solar-recommendation-card-title">Recomendacion principal</p></div>
                    <p class="solar-recommendation-card-copy" data-scale-primary-recommendation>{{ $activeScale['primaryRecommendation'] ?? ($dashboard['executiveSummary']['dailyRecommendation'] ?? 'Sin recomendacion principal.') }}</p>
                </article>
                <article class="solar-recommendation-card">
                    <div class="solar-recommendation-card-head"><p class="solar-recommendation-card-title">Estado del sistema</p></div>
                    <p class="solar-recommendation-card-copy" data-scale-state-title>{{ $activeScale['stateTitle'] ?? 'Estado pendiente' }}</p>
                    <p class="solar-recommendation-card-copy mt-2">Dependencia de red: {{ $coverage !== null && $coverage < 100 ? 'Media/Alta' : 'Baja' }}</p>
                </article>
                <article class="solar-recommendation-card">
                    <div class="solar-recommendation-card-head"><p class="solar-recommendation-card-title">Riesgo operativo</p></div>
                    <p class="solar-recommendation-card-copy" data-scale-risk>{{ $activeScale['risk'] ?? 'Sin riesgo critico detectado.' }}</p>
                    <p class="solar-recommendation-card-copy mt-2">Radiacion promedio local: {{ isset($weatherStationStats['averageRadiation']) ? $formatNumber($weatherStationStats['averageRadiation']) : 'N/A' }}</p>
                </article>
            </div>
        </section>

        <section class="solar-card mt-6">
            <div class="solar-page-header">
                <div>
                    <p class="solar-kicker">Indicadores y tendencia</p>
                    <h2 class="text-2xl text-[color:var(--solar-text)]">KPIs + graficas temporales</h2>
                </div>
                <div class="solar-scale-tabs" data-dashboard-scale-selector>
                    @foreach (['daily' => 'Diario', 'monthly' => 'Mensual', 'annual' => 'Anual'] as $scaleKey => $scaleLabel)
                        <button type="button" class="solar-scale-tab {{ $scaleKey === $activeScaleKey ? 'is-active' : '' }}" data-scale-button="{{ $scaleKey }}">{{ $scaleLabel }}</button>
                    @endforeach
                </div>
            </div>

            <div class="solar-kpi-board mt-4" data-scale-kpis>
                @foreach (($activeScale['kpis'] ?? []) as $kpi)
                    <div class="solar-metric-card min-w-0">
                        <p class="solar-metric-label">{{ $kpi['label'] }}</p>
                        <p class="solar-metric-value">{{ $kpi['value'] ?? 'N/A' }}</p>
                        <p class="solar-metric-copy">{{ $kpi['description'] ?? '' }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 grid gap-4 xl:grid-cols-3">
                <div class="solar-chart-panel xl:col-span-2 min-w-0">
                    <div class="solar-report-panel-head"><h3>Generacion vs consumo vs ahorro</h3><p data-scale-chart-range>{{ $activeScale['chart']['rangeLabel'] ?? 'Periodo' }}</p></div>
                    <div class="solar-chart-canvas mt-3"><canvas id="solar-executive-chart" role="img" aria-label="Grafica ejecutiva"></canvas></div>
                </div>
                <div class="solar-chart-panel min-w-0">
                    <div class="solar-report-panel-head"><h3>Cobertura</h3></div>
                    <div class="solar-chart-canvas mt-3"><canvas id="solar-coverage-chart" role="img" aria-label="Grafica de cobertura"></canvas></div>
                </div>
            </div>

            @if (($weatherStationStats['total'] ?? 0) > 0)
                <div class="mt-4 solar-chart-panel min-w-0">
                    <div class="solar-report-panel-head"><h3>Tendencia de radiacion local</h3></div>
                    <div class="solar-weather-chart-canvas mt-3"><canvas id="weather-station-radiation-chart" role="img" aria-label="Radiacion local"></canvas></div>
                </div>
            @endif
        </section>

        <section class="solar-card solar-ai-zone mt-6">
            <div class="solar-page-header">
                <div>
                    <p class="solar-kicker">Recomendaciones IA</p>
                    <h2 class="text-2xl text-[color:var(--solar-text)]">Resumen ejecutivo inteligente</h2>
                    <p class="solar-subtitle mt-2">Enfoque activo: {{ $aiFocusOptions[$aiFocus] ?? 'General' }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="solar-pill solar-ai-pill">Fuente {{ strtoupper((string) ($dashboard['executiveSummary']['source'] ?? 'ia')) }}</span>
                    <form method="GET" action="{{ route('solar-projects.show', $solarProject) }}">
                        <input type="hidden" name="generate_ai" value="1" />
                        <select name="ai_focus" class="solar-ai-select">
                            @foreach ($aiFocusOptions as $focusKey => $focusLabel)
                                <option value="{{ $focusKey }}" @selected($aiFocus === $focusKey)>{{ $focusLabel }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="solar-button">{{ $generateAiRecommendations ? 'Regenerar recomendaciones IA' : 'Generar recomendaciones con IA' }}</button>
                    </form>
                </div>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <article class="solar-recommendation-card solar-ai-summary-card">
                    <div class="solar-recommendation-card-head"><p class="solar-recommendation-card-title">Resumen ejecutivo</p></div>
                    <p class="solar-recommendation-card-copy">{{ $dashboard['executiveSummary']['text'] ?? 'Sin resumen IA disponible.' }}</p>
                </article>
                <article class="solar-recommendation-card solar-ai-summary-card">
                    <div class="solar-recommendation-card-head"><p class="solar-recommendation-card-title">Recomendacion inteligente</p></div>
                    <p class="solar-recommendation-card-copy">{{ $dashboard['executiveSummary']['dailyRecommendation'] ?? 'Sin recomendacion IA disponible.' }}</p>
                    @if (! empty($dashboard['executiveSummary']['error'] ?? null))
                        <p class="mt-2 text-sm text-[color:var(--solar-danger)]">{{ $dashboard['executiveSummary']['error'] }}</p>
                    @endif
                </article>
            </div>

            <div class="mt-4">
                <article class="solar-recommendation-card solar-ai-focus-card">
                    <div class="solar-recommendation-card-head">
                        <p class="solar-recommendation-card-title">{{ $aiSelectedPackItem['title'] ?? ($aiFocusOptions[$aiFocus] ?? 'Recomendacion') }}</p>
                    </div>
                    <p class="solar-recommendation-card-copy">{{ $aiSelectedPackItem['message'] ?? 'Sin recomendacion disponible para el enfoque seleccionado.' }}</p>
                </article>
            </div>

        </section>

        <section class="solar-card mt-6">
            <div class="solar-page-header">
                <div>
                    <p class="solar-kicker">Prediccion operativa</p>
                    <h2 class="text-2xl text-[color:var(--solar-text)]">Escenario proxima semana</h2>
                </div>
                <span class="solar-pill">Basado en historico</span>
            </div>
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <article class="solar-recommendation-card">
                    <div class="solar-recommendation-card-head"><p class="solar-recommendation-card-title">Tendencia de temperatura</p></div>
                    <p class="solar-recommendation-card-copy">
                        {{ $futurePredictions['temperature']['message'] ?? 'Sin prediccion termica disponible.' }}
                    </p>
                    @if (isset($futurePredictions['temperature']['projected_next_week_c']) && $futurePredictions['temperature']['projected_next_week_c'] !== null)
                        <p class="solar-recommendation-card-copy mt-2">
                            Proyeccion: {{ number_format((float) $futurePredictions['temperature']['projected_next_week_c'], 2, ',', '.') }} C
                            (delta semanal: {{ number_format((float) ($futurePredictions['temperature']['delta_c'] ?? 0), 2, ',', '.') }} C)
                        </p>
                    @endif
                </article>
                <article class="solar-recommendation-card">
                    <div class="solar-recommendation-card-head"><p class="solar-recommendation-card-title">Ventana solar recomendada</p></div>
                    <p class="solar-recommendation-card-copy">
                        {{ $futurePredictions['radiation_window']['message'] ?? 'Sin ventana solar identificada.' }}
                    </p>
                </article>
            </div>
        </section>

        <section class="solar-card mt-6">
            <div class="solar-page-header">
                <div>
                    <p class="solar-kicker">Datos tecnicos</p>
                    <h2 class="text-2xl text-[color:var(--solar-text)]">Exploracion de registros</h2>
                    <p class="solar-subtitle mt-2">La tabla completa se muestra bajo demanda para mantener el dashboard limpio.</p>
                </div>
                <button type="button" class="solar-button-secondary" @click="showDataModal = true">Ver datos registrados</button>
            </div>
        </section>

        <div x-show="showDataModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @keydown.escape.window="showDataModal = false">
            <div class="solar-card w-full max-w-6xl" @click.stop>
                <div class="solar-page-header">
                    <div>
                        <p class="solar-kicker">Lecturas registradas</p>
                        <h3 class="text-2xl text-[color:var(--solar-text)]">Historico tecnico</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="text" x-model="filter" placeholder="Filtrar por fecha..." class="rounded-lg border border-[color:var(--solar-border)] bg-white/80 px-3 py-2 text-sm dark:bg-zinc-900" />
                        <button type="button" class="solar-button-ghost" @click="showDataModal = false">Cerrar</button>
                    </div>
                </div>

                <div class="solar-table-shell mt-4 max-h-[65vh] overflow-auto">
                    <table class="solar-table solar-table-fit text-sm">
                        <thead>
                            <tr>
                                <th>Fecha</th><th>Radiacion</th><th>UVA</th><th>UVB</th><th>IUV</th><th>Temp</th><th>Humedad</th><th>CO2</th><th>PM2.5</th><th>PM10</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="row in {{ json_encode($tableRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}.filter(r => !filter || (r.fecha || '').toLowerCase().includes(filter.toLowerCase()))" :key="row.fecha + '-' + (row.co2 ?? 'na')">
                                <tr>
                                    <td x-text="row.fecha"></td>
                                    <td x-text="row.radiacion ?? 'N/A'"></td>
                                    <td x-text="row.uva ?? 'N/A'"></td>
                                    <td x-text="row.uvb ?? 'N/A'"></td>
                                    <td x-text="row.iuv ?? 'N/A'"></td>
                                    <td x-text="row.temperature ?? 'N/A'"></td>
                                    <td x-text="row.humidity ?? 'N/A'"></td>
                                    <td x-text="row.co2 ?? 'N/A'"></td>
                                    <td x-text="row.pm25 ?? 'N/A'"></td>
                                    <td x-text="row.pm10 ?? 'N/A'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script type="application/json" id="solar-timescale-chart-data">@json($timeScales)</script>
        <script type="application/json" id="weather-station-chart-data">@json($weatherStationChartData ?? ['labels' => [], 'radiation' => []])</script>
    </div>
</x-layouts::app>
