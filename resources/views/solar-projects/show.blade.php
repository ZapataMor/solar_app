@php
    $technicalParameter = $solarProject->technicalParameter;
    $calculationResult = $solarProject->calculationResult;
    $timeScales = $timeScales ?? ['defaultScale' => 'monthly', 'scales' => [], 'activeScale' => null];
    $activeScaleKey = $timeScales['defaultScale'] ?? 'monthly';
    $activeScale = $timeScales['activeScale'] ?? ($timeScales['scales'][$activeScaleKey] ?? null);
    $dashboard = $dashboard ?? ['executiveSummary' => ['enabled' => false]];
    $weatherStationStats = $weatherStationStats ?? [];
    $recentWeatherStationReadings = $recentWeatherStationReadings ?? collect();

    $coverage = $calculationResult ? (float) $calculationResult->coverage_percentage : null;
    $annualSavings = $calculationResult ? (float) $calculationResult->estimated_annual_savings_cop : null;

    $coverageTone = $coverage === null ? 'warn' : ($coverage >= 100 ? 'success' : ($coverage >= 70 ? 'warn' : 'danger'));
    $coverageLabel = $coverage === null ? 'Pendiente' : number_format($coverage, 1, ',', '.') . '%';

    $riskText = (string) ($activeScale['risk'] ?? 'Sin riesgo critico detectado.');
    $riskTone = str_contains(strtolower($riskText), 'crit') ? 'danger' : (filled($riskText) ? 'warn' : 'success');

    $formatNumber = fn ($value, int $decimals = 2) => number_format((float) $value, $decimals, ',', '.');
    $formatKwh = fn ($value) => $formatNumber($value) . ' kWh';
    $formatMoney = fn ($value) => '$ ' . number_format((float) $value, 0, ',', '.') . ' COP';

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

        <section class="solar-hero">
            <div class="solar-page-header gap-4">
                <div class="max-w-4xl">
                    <p class="solar-kicker">Dashboard solar operativo</p>
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

                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('solar-projects.edit', $solarProject) }}" class="solar-button-secondary">Editar</a>
                    <form method="POST" action="{{ route('solar-projects.fetch-weather-data', $solarProject) }}">@csrf<button class="solar-button-ghost" type="submit">Sincronizar NASA</button></form>
                    <form method="POST" action="{{ route('solar-projects.calculate-weather-station', $solarProject) }}">@csrf<button class="solar-button" type="submit">Recalcular dashboard</button></form>
                    <form method="POST" action="{{ route('solar-projects.destroy', $solarProject) }}" onsubmit="return confirm('¿Eliminar proyecto?');">@csrf @method('DELETE')<button class="solar-button-danger" type="submit">Eliminar</button></form>
                    <a href="{{ route('solar-projects.index') }}" class="solar-button-ghost">Volver</a>
                </div>
            </div>
        </section>

        <section class="solar-card mt-6">
            <div class="solar-page-header">
                <div>
                    <p class="solar-kicker">Contexto del proyecto</p>
                    <h2 class="text-2xl text-[color:var(--solar-text)]">Resumen ejecutivo técnico</h2>
                </div>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div class="solar-metric-card"><p class="solar-metric-label">Consumo mensual</p><p class="solar-metric-value">{{ $formatKwh($solarProject->monthlyConsumption()) }}</p></div>
                <div class="solar-metric-card"><p class="solar-metric-label">Consumo anual</p><p class="solar-metric-value">{{ $formatKwh($solarProject->annualConsumption()) }}</p></div>
                <div class="solar-metric-card"><p class="solar-metric-label">Tarifa</p><p class="solar-metric-value">$ {{ number_format($solarProject->energy_rate_cop_per_kwh, 0, ',', '.') }}</p></div>
                <div class="solar-metric-card"><p class="solar-metric-label">Paneles</p><p class="solar-metric-value">{{ $calculationResult?->number_of_panels ?? 'N/A' }}</p></div>
                <div class="solar-metric-card"><p class="solar-metric-label">Capacidad</p><p class="solar-metric-value">{{ $calculationResult?->installed_capacity_kwp ? $formatNumber($calculationResult->installed_capacity_kwp) . ' kWp' : 'N/A' }}</p></div>
                <div class="solar-metric-card"><p class="solar-metric-label">Area util</p><p class="solar-metric-value">{{ $calculationResult?->usable_area_m2 ? $formatNumber($calculationResult->usable_area_m2) . ' m2' : ($technicalParameter?->available_area_m2 ? $formatNumber($technicalParameter->available_area_m2) . ' m2' : 'N/A') }}</p></div>
                <div class="solar-metric-card"><p class="solar-metric-label">Ubicacion</p><p class="solar-metric-value text-base">{{ $solarProject->location_name }}</p></div>
                <div class="solar-metric-card"><p class="solar-metric-label">Coordenadas</p><p class="solar-metric-value text-base">{{ number_format((float) $solarProject->latitude, 4) }}, {{ number_format((float) $solarProject->longitude, 4) }}</p></div>
                <div class="solar-metric-card"><p class="solar-metric-label">Fuente NASA</p><p class="solar-metric-value">{{ number_format($solarProject->weather_data_count, 0, ',', '.') }}</p></div>
                <div class="solar-metric-card"><p class="solar-metric-label">Lecturas locales</p><p class="solar-metric-value">{{ number_format($weatherStationStats['total'] ?? 0, 0, ',', '.') }}</p></div>
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

        <section class="solar-card mt-6">
            <div class="solar-page-header">
                <div>
                    <p class="solar-kicker">Recomendaciones IA</p>
                    <h2 class="text-2xl text-[color:var(--solar-text)]">Resumen ejecutivo inteligente</h2>
                </div>
                <span class="solar-pill">Fuente {{ strtoupper((string) ($dashboard['executiveSummary']['source'] ?? 'ia')) }}</span>
            </div>

            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                <article class="solar-recommendation-card">
                    <div class="solar-recommendation-card-head"><p class="solar-recommendation-card-title">Resumen ejecutivo</p></div>
                    <p class="solar-recommendation-card-copy">{{ $dashboard['executiveSummary']['text'] ?? 'Sin resumen IA disponible.' }}</p>
                </article>
                <article class="solar-recommendation-card">
                    <div class="solar-recommendation-card-head"><p class="solar-recommendation-card-title">Recomendacion inteligente</p></div>
                    <p class="solar-recommendation-card-copy">{{ $dashboard['executiveSummary']['dailyRecommendation'] ?? 'Sin recomendacion IA disponible.' }}</p>
                    @if (! empty($dashboard['executiveSummary']['error'] ?? null))
                        <p class="mt-2 text-sm text-[color:var(--solar-danger)]">{{ $dashboard['executiveSummary']['error'] }}</p>
                    @endif
                </article>
            </div>

            <div class="mt-4 grid gap-3 lg:grid-cols-3" data-scale-recommendations>
                @forelse ($activeScale['recommendations'] ?? [] as $recommendation)
                    <article class="solar-recommendation-card">
                        <div class="solar-recommendation-card-head">
                            <p class="solar-recommendation-card-title">{{ ucfirst($recommendation['type'] ?? 'accion') }}</p>
                            <span class="solar-pill">{{ $recommendation['priority'] ?? 'media' }}</span>
                        </div>
                        <p class="solar-recommendation-card-copy">{{ $recommendation['message'] ?? '' }}</p>
                    </article>
                @empty
                    <div class="solar-alert solar-alert-warning">Sin recomendaciones adicionales para la escala activa.</div>
                @endforelse
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
