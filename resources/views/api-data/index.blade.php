@php
    $formatNumber = fn ($value, int $decimals = 2) => $value !== null ? number_format((float) $value, $decimals, ',', '.') : 'N/A';
    $formatNasaNumber = fn ($value, int $decimals = 2) => $value !== null ? number_format((float) $value, $decimals, ',', '.') : 'Dato no publicado por NASA';
    $formatDate = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i') : 'N/A';

    $totalRows = $ambientCount + $weatherStationCount + $nasaCount;

    $latestWeatherStationChartPoint = collect($weatherStationChartRows)->last();
    $latestUvIndex = $latestWeatherStationChartPoint['uv_index'] ?? null;
    $uvIndexPercent = $latestUvIndex !== null ? min(100, ((float) $latestUvIndex / 11) * 100) : 0;
    $uvRisk = match (true) {
        $latestUvIndex === null => 'Sin dato',
        $latestUvIndex < 3 => 'Bajo',
        $latestUvIndex < 6 => 'Moderado',
        $latestUvIndex < 8 => 'Alto',
        $latestUvIndex < 11 => 'Muy alto',
        default => 'Extremo',
    };

    $latestAmbientChartPoint = collect($ambientChartRows)->last();
    $latestAmbientUv = $latestAmbientChartPoint['uv_index'] ?? null;
    $ambientUvPercent = $latestAmbientUv !== null ? min(100, ((float) $latestAmbientUv / 11) * 100) : 0;
    $ambientUvRisk = match (true) {
        $latestAmbientUv === null => 'Sin dato',
        $latestAmbientUv < 3 => 'Bajo',
        $latestAmbientUv < 6 => 'Moderado',
        $latestAmbientUv < 8 => 'Alto',
        $latestAmbientUv < 11 => 'Muy alto',
        default => 'Extremo',
    };
@endphp

<x-layouts::app :title="__('Datos APIs')">
    <div class="solar-page">
        <section class="solar-hero">
            <div class="solar-page-header">
                <div>
                    <p class="solar-kicker">Data observatory</p>
                    <h1 class="solar-title">Datos climaticos y meteorologicos</h1>
                    <p class="solar-subtitle">Consolida la radiacion, temperatura y lecturas locales en una vista mas profesional, con mejor jerarquia para demo y analisis.</p>
                </div>
                <span class="solar-pill"><span data-api-data-total-count>{{ number_format($totalRows, 0, ',', '.') }}</span> registros visibles</span>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-4">
                <div class="solar-metric-card">
                    <p class="solar-metric-label">Total registros</p>
                    <p class="solar-metric-value" data-api-data-total-count>{{ number_format($totalRows, 0, ',', '.') }}</p>
                    <p class="solar-metric-copy">Base consolidada para decisiones de energia, radiacion y riesgo operativo.</p>
                </div>
                <div class="solar-metric-card" style="border-left: 3px solid var(--solar-sun, #fbbf24);">
                    <p class="solar-metric-label">Ambient Weather</p>
                    <p class="solar-metric-value">{{ number_format($ambientCount, 0, ',', '.') }}</p>
                    <p class="solar-metric-copy">Estacion IoT con datos de temperatura, radiacion y viento en tiempo real.</p>
                </div>
                <div class="solar-metric-card">
                    <p class="solar-metric-label">Estacion local</p>
                    <p class="solar-metric-value" data-weather-station-count data-count="{{ $weatherStationCount }}">{{ number_format($weatherStationCount, 0, ',', '.') }}</p>
                    <p class="solar-metric-copy">Lecturas de contexto real para Riohacha y seguimiento ambiental.</p>
                </div>
                <div class="solar-metric-card">
                    <p class="solar-metric-label">NASA POWER</p>
                    <p class="solar-metric-value" data-api-data-nasa-count data-count="{{ $nasaCount }}">{{ number_format($nasaCount, 0, ',', '.') }}</p>
                    <p class="solar-metric-copy">Fuente satelital para comparacion y cobertura historica.</p>
                </div>
            </div>
        </section>

        @if (session('status'))
            <div class="solar-alert solar-alert-success">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->has('ambient_data'))
            <div class="solar-alert solar-alert-danger">
                {{ $errors->first('ambient_data') }}
            </div>
        @endif

        @if ($errors->has('nasa_data'))
            <div class="solar-alert solar-alert-danger">
                {{ $errors->first('nasa_data') }}
            </div>
        @endif

        @if ($errors->has('weather_station'))
            <div class="solar-alert solar-alert-danger">
                {{ $errors->first('weather_station') }}
            </div>
        @endif

        {{-- ══════════════════════════════════════════════
             1. AMBIENT WEATHER
        ══════════════════════════════════════════════ --}}
        <section class="solar-card" data-api-pagination-section="ambient">
            <div class="solar-page-header solar-api-section-header">
                <div>
                    <p class="solar-kicker">Ambient Weather</p>
                    <h2 class="text-2xl text-[color:var(--solar-text)]">Estacion IoT — UniGuajiraPtG</h2>
                    <p class="solar-subtitle mt-2">Lecturas en tiempo real desde la estacion Ambient Weather conectada. Temperatura, radiacion solar, viento y lluvia con actualizacion automatica cada 5 minutos.</p>
                </div>
                <div class="solar-api-actions">
                    <span class="solar-pill solar-pill-warn">
                        {{ number_format($ambientCount, 0, ',', '.') }} registros
                    </span>
                    <form method="POST" action="{{ route('api-data.fetch-ambient-data') }}">
                        @csrf
                        <button type="submit" class="solar-button-secondary">Sincronizar Ambient Weather</button>
                    </form>
                </div>
            </div>

            <script id="ambient-realtime-chart-data" type="application/json">@json($ambientChartRows)</script>

            <div class="mt-6 grid gap-4 xl:grid-cols-[minmax(0,1fr)_260px]">
                <div class="solar-table-shell p-4">
                    <div class="h-[320px]">
                        <canvas id="ambient-realtime-chart" aria-label="Radiacion solar y temperatura Ambient Weather" role="img"></canvas>
                    </div>
                </div>

                <div class="solar-metric-card">
                    <p class="solar-metric-label">IUV actual (Ambient)</p>
                    <p class="solar-metric-value">{{ $latestAmbientUv !== null ? $formatNumber($latestAmbientUv, 2) : 'N/A' }}</p>
                    <p class="solar-metric-copy">{{ $ambientUvRisk }}</p>
                    <div class="mt-4 h-3 overflow-hidden rounded-full bg-[color:var(--solar-border)]">
                        <div class="h-full rounded-full bg-[color:var(--solar-sun)] transition-all" style="width: {{ $ambientUvPercent }}%"></div>
                    </div>
                </div>
            </div>

            <div class="solar-table-shell mt-6">
                <div class="overflow-x-auto">
                    <table class="solar-table min-w-[900px]">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Estacion (MAC)</th>
                                <th>Radiacion solar</th>
                                <th>Temp.</th>
                                <th>Humedad</th>
                                <th>Viento (km/h)</th>
                                <th>Dir. viento</th>
                                <th>Lluvia (mm)</th>
                                <th>IUV</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ambientRows as $row)
                                <tr>
                                    <td class="font-semibold text-[color:var(--solar-text)]">{{ $formatDate($row->recorded_at) }}</td>
                                    <td class="font-mono text-xs">{{ $row->mac_address ?? 'N/A' }}</td>
                                    <td>{{ $formatNumber($row->radiation, 2) }} <span class="text-xs text-[color:var(--solar-text-muted)]">W/m²</span></td>
                                    <td>{{ $formatNumber($row->temperature, 2) }} <span class="text-xs text-[color:var(--solar-text-muted)]">°C</span></td>
                                    <td>{{ $formatNumber($row->humidity, 2) }} <span class="text-xs text-[color:var(--solar-text-muted)]">%</span></td>
                                    <td>{{ $formatNumber($row->wind_speed, 2) }}</td>
                                    <td>{{ $row->wind_direction !== null ? $row->wind_direction . '°' : 'N/A' }}</td>
                                    <td>{{ $formatNumber($row->rainfall, 3) }}</td>
                                    <td>{{ $formatNumber($row->uv_index, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="py-10 text-center">
                                        Aun no hay lecturas registradas desde Ambient Weather. Pulsa "Sincronizar Ambient Weather" para importar.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="solar-pagination mt-5">
                {{ $ambientRows->links() }}
            </div>
        </section>

        {{-- ══════════════════════════════════════════════
             2. ESTACION METEOROLOGICA LOCAL
        ══════════════════════════════════════════════ --}}
        <section class="solar-card" data-api-pagination-section="weather-station" data-weather-station-sync data-sync-interval="15000">
            <div class="solar-page-header solar-api-section-header">
                <div>
                    <p class="solar-kicker">Estacion local</p>
                    <h2 class="text-2xl text-[color:var(--solar-text)]">Centro meteorologico</h2>
                    <p class="solar-subtitle mt-2">Lecturas locales con mas personalidad visual y mejor lectura de variables ambientales.</p>
                    <p class="mt-2 text-sm text-[color:var(--solar-text-muted)]" data-weather-station-status>
                        Actualizacion automatica activa.
                    </p>
                </div>
                <div class="solar-api-actions">
                    <span class="solar-pill solar-pill-warn" data-weather-station-count-pill>
                        {{ number_format($weatherStationCount, 0, ',', '.') }} registros
                    </span>
                    <form method="POST" action="{{ route('api-data.fetch-weather-station-data') }}" data-weather-station-fetch-form>
                        @csrf
                        <button type="submit" class="solar-button-secondary">Obtener datos de estacion</button>
                    </form>
                </div>
            </div>

            <script id="weather-station-realtime-chart-data" type="application/json">@json($weatherStationChartRows)</script>

            <div class="mt-6 grid gap-4 xl:grid-cols-[minmax(0,1fr)_260px]">
                <div class="solar-table-shell p-4">
                    <div class="h-[320px]">
                        <canvas id="weather-station-realtime-chart" aria-label="Radiacion, UVA, UVB e IUV en tiempo real" role="img"></canvas>
                    </div>
                </div>

                <div class="solar-metric-card" data-weather-station-iuv-card>
                    <p class="solar-metric-label">IUV actual</p>
                    <p class="solar-metric-value" data-weather-station-iuv-value>{{ $latestUvIndex !== null ? $formatNumber($latestUvIndex, 2) : 'N/A' }}</p>
                    <p class="solar-metric-copy" data-weather-station-iuv-risk>{{ $uvRisk }}</p>
                    <div class="mt-4 h-3 overflow-hidden rounded-full bg-[color:var(--solar-border)]">
                        <div class="h-full rounded-full bg-[color:var(--solar-sun)] transition-all" data-weather-station-iuv-bar style="width: {{ $uvIndexPercent }}%"></div>
                    </div>
                </div>
            </div>

            <div class="solar-table-shell mt-6">
                <div class="overflow-x-auto">
                    <table class="solar-table min-w-[1240px]">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Dispositivo</th>
                                <th>Radiacion</th>
                                <th>Temp.</th>
                                <th>Humedad</th>
                                <th>Sensacion termica</th>
                                <th>CO2</th>
                                <th>PM2.5</th>
                                <th>PM10</th>
                                <th>UVA</th>
                                <th>UVB</th>
                                <th>IUV</th>
                            </tr>
                        </thead>
                        <tbody data-weather-station-rows>
                            @forelse ($weatherStationRows as $row)
                                <tr>
                                    <td class="font-semibold text-[color:var(--solar-text)]">{{ $formatDate($row->recorded_at) }}</td>
                                    <td>{{ $row->device_code ?? 'N/A' }}</td>
                                    <td>{{ $formatNumber($row->radiation, 3) }}</td>
                                    <td>{{ $formatNumber($row->temperature, 2) }}</td>
                                    <td>{{ $formatNumber($row->humidity, 2) }}</td>
                                    <td>{{ $formatNumber($row->thermal_sensation, 2) }}</td>
                                    <td>{{ $row->co2 ?? 'N/A' }}</td>
                                    <td>{{ $formatNumber($row->pm25, 2) }}</td>
                                    <td>{{ $formatNumber($row->pm10, 2) }}</td>
                                    <td>{{ $formatNumber($row->uva, 3) }}</td>
                                    <td>{{ $formatNumber($row->uvb, 3) }}</td>
                                    <td>{{ $formatNumber($row->uv_index, 3) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="py-10 text-center">
                                        Aun no hay lecturas registradas desde el centro meteorologico.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="solar-pagination mt-5" data-api-pagination-links>
                {{ $weatherStationRows->links() }}
            </div>
        </section>

        {{-- ══════════════════════════════════════════════
             3. NASA POWER
        ══════════════════════════════════════════════ --}}
        <section class="solar-card" data-api-pagination-section="nasa">
            <div class="solar-page-header">
                <div>
                    <p class="solar-kicker">NASA power</p>
                    <h2 class="text-2xl text-[color:var(--solar-text)]">Fuente satelital</h2>
                    <p class="solar-subtitle mt-2">Datos climaticos sincronizados desde NASA POWER con una lectura tabular mas clara.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="solar-pill">{{ number_format($nasaCount, 0, ',', '.') }} registros</span>
                    <form method="POST" action="{{ route('api-data.fetch-nasa-data') }}">
                        @csrf
                        <button type="submit" class="solar-button">Obtener datos NASA POWER</button>
                    </form>
                </div>
            </div>

            <div class="solar-table-shell mt-6">
                <div class="solar-table-scroll">
                    <table class="solar-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Radiacion</th>
                                <th>Origen radiacion</th>
                                <th>Temp.</th>
                                <th>Humedad</th>
                                <th>Precipitacion</th>
                                <th>Viento</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($nasaRows as $row)
                                @php
                                    $isIncomplete = $row->radiation === null
                                        || $row->temperature === null
                                        || $row->humidity === null
                                        || $row->precipitation === null
                                        || $row->wind_speed === null;
                                    $sourceLabel = match ($row->radiation_method ?? 'nasa_real') {
                                        'nasa_real' => 'NASA real',
                                        'interpolated_recent' => 'Estimado: interpolacion',
                                        'weather_signals_model' => 'Estimado: señales meteo',
                                        'historical_monthly' => 'Estimado: historico mensual',
                                        'riohacha_climatology' => 'Estimado: climatologia Riohacha',
                                        'last_valid_known' => 'Estimado: ultimo valor valido',
                                        default => 'Estimado',
                                    };
                                @endphp
                                <tr>
                                    <td class="font-semibold text-[color:var(--solar-text)]">{{ $formatDate($row->recorded_at) }}</td>
                                    <td>
                                        <span class="solar-pill {{ $isIncomplete ? 'solar-pill-warn' : '' }}">
                                            {{ $isIncomplete ? 'Incompleto' : 'Completo' }}
                                        </span>
                                    </td>
                                    <td>{{ $formatNasaNumber($row->radiation, 3) }}</td>
                                    <td>
                                        {{ $sourceLabel }}
                                        <span class="text-xs text-[color:var(--solar-text-muted)]">
                                            ({{ number_format((float) ($row->radiation_confidence ?? 0), 2, ',', '.') }})
                                        </span>
                                    </td>
                                    <td>{{ $formatNasaNumber($row->temperature, 2) }}</td>
                                    <td>{{ $formatNasaNumber($row->humidity, 2) }}</td>
                                    <td>{{ $formatNasaNumber($row->precipitation, 4) }}</td>
                                    <td>{{ $formatNasaNumber($row->wind_speed, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="py-10 text-center">
                                        Aun no hay datos registrados desde NASA POWER.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="solar-pagination mt-5" data-api-pagination-links>
                {{ $nasaRows->links() }}
            </div>
        </section>
    </div>
</x-layouts::app>
