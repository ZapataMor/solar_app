@php
    $formatNumber = fn ($value, int $decimals = 2) => $value !== null ? number_format((float) $value, $decimals, ',', '.') : 'N/A';
    $formatNasaNumber = fn ($value, int $decimals = 2) => $value !== null ? number_format((float) $value, $decimals, ',', '.') : 'Dato no publicado por NASA';
    $formatDate = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->timezone(config('app.display_timezone'))->format('Y-m-d H:i') : 'N/A';

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
    {{-- The 3D stations download with the page, so the loader gives way to them sooner (ADR-0018). --}}
    @push('head')
        @vite('resources/js/station-scene/scene.js')
    @endpush

    <style>
        .solar-api-page {
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }

        .solar-api-page .solar-page-header,
        .solar-api-page .solar-api-section-header {
            min-width: 0;
        }

        .solar-api-page .solar-api-chart-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(0, 1fr) minmax(13rem, 16.25rem);
        }

        .solar-api-page .solar-api-chart-frame {
            height: clamp(16rem, 34vw, 20rem);
        }

        .solar-api-page .solar-api-table-scroll {
            width: 100%;
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
        }

        .solar-api-page .solar-api-table {
            width: max(100%, var(--api-table-min, 56rem));
            min-width: var(--api-table-min, 56rem);
        }

        .solar-api-page .solar-api-actions {
            min-width: 0;
        }

        .solar-api-page .solar-api-actions > *,
        .solar-api-page .solar-api-actions form {
            min-width: 0;
        }

        .solar-api-page .solar-api-actions button {
            justify-content: center;
        }

        .solar-api-page .solar-pagination {
            max-width: 100%;
            overflow: hidden;
        }

        .solar-api-page .solar-pagination nav {
            display: grid;
            gap: .75rem;
            min-width: 0;
        }

        .solar-api-page .solar-pagination nav > div:first-child {
            min-width: 0;
        }

        .solar-api-page .solar-pagination nav > div:last-child {
            display: grid;
            gap: .75rem;
            min-width: 0;
        }

        .solar-api-page .solar-pagination nav > div:last-child > div:last-child {
            max-width: 100%;
            overflow-x: auto;
            overflow-y: hidden;
            padding-bottom: .25rem;
            -webkit-overflow-scrolling: touch;
        }

        @media (max-width: 1024px) {
            .solar-api-page .solar-api-chart-grid {
                grid-template-columns: 1fr;
            }

            .solar-api-page .solar-api-actions {
                width: 100%;
                justify-content: stretch;
            }

            .solar-api-page .solar-api-actions .solar-pill,
            .solar-api-page .solar-api-actions form,
            .solar-api-page .solar-api-actions button {
                width: 100%;
            }
        }

        @media (max-width: 720px) {
            .solar-api-page {
                gap: 1rem;
            }

            .solar-api-page .solar-hero,
            .solar-api-page .solar-card {
                padding: 1rem;
                border-radius: 1rem;
            }

            .solar-api-page .solar-page-header,
            .solar-api-page .solar-api-section-header {
                display: grid;
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .solar-api-page .solar-title {
                font-size: clamp(2rem, 12vw, 2.65rem);
                line-height: 1;
            }

            .solar-api-page h2 {
                font-size: 1.35rem;
                line-height: 1.15;
            }

            .solar-api-page .solar-subtitle {
                font-size: .95rem;
            }

            .solar-api-page .solar-api-actions {
                grid-template-columns: 1fr;
            }

            .solar-api-page .solar-api-actions .solar-pill,
            .solar-api-page .solar-api-actions form,
            .solar-api-page .solar-api-actions button {
                width: 100%;
            }

            .solar-api-page .solar-api-chart-frame {
                height: 18rem;
            }

            .solar-api-page .solar-table-shell {
                border-radius: .95rem;
            }

            .solar-api-page .solar-table th,
            .solar-api-page .solar-table td {
                padding: .75rem;
                white-space: nowrap;
            }

            .solar-api-page .solar-pagination nav,
            .solar-api-page .solar-pagination nav > div:last-child {
                gap: .55rem;
            }

            .solar-api-page .solar-pagination nav > div:last-child > div:first-child {
                font-size: .75rem;
                line-height: 1.35;
            }
        }
    </style>

    <div class="solar-page solar-api-page" data-api-auto-sync data-api-sync-interval="300000">
        {{-- The counts live in the tabs and in each section: this space shows the station behind the selected tab (ADR-0018). --}}
        <section class="solar-hero solar-api-hero">
            <div class="solar-api-hero__copy">
                <p class="solar-kicker">Data observatory</p>
                <h1 class="solar-title">Datos climaticos y meteorologicos</h1>
                <p class="solar-subtitle">Consolida la radiacion, temperatura y lecturas locales en una vista mas profesional, con mejor jerarquia para demo y analisis.</p>
                <span class="solar-pill"><span data-api-data-total-count>{{ number_format($totalRows, 0, ',', '.') }}</span> registros visibles</span>
            </div>

            @include('api-data.partials.station-figure')
        </section>

        @include('api-data.partials.sync-health')

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

        {{-- Una pestaña por fuente (ADR-0008). Son enlaces reales: sin JS recargan; con JS cambian al instante. --}}
        @php
            // countAttribute: the sync updates that number (apiSourceConfig in app.js) and the total adds them up.
            $sourceTabs = [
                'ambient' => ['label' => 'Ambient Weather', 'meta' => 'Estación IoT · cada 5 min', 'count' => $ambientCount, 'countAttribute' => 'data-ambient-count'],
                'weather-station' => ['label' => 'Estación local', 'meta' => 'Centro meteorológico · UV', 'count' => $weatherStationCount, 'countAttribute' => 'data-weather-station-count'],
                'nasa' => ['label' => 'NASA POWER', 'meta' => 'Satelital · diaria', 'count' => $nasaCount, 'countAttribute' => 'data-api-data-nasa-count'],
            ];
        @endphp
        <nav class="solar-source-tabs" role="tablist" aria-label="Fuentes de datos climáticos" data-api-tabs>
            @foreach ($sourceTabs as $tabKey => $tab)
                <a
                    href="{{ request()->fullUrlWithQuery(['tab' => $tabKey]) }}"
                    id="api-tab-{{ $tabKey }}"
                    class="solar-source-tab"
                    role="tab"
                    aria-controls="api-panel-{{ $tabKey }}"
                    aria-selected="{{ $activeTab === $tabKey ? 'true' : 'false' }}"
                    tabindex="{{ $activeTab === $tabKey ? '0' : '-1' }}"
                    data-api-tab="{{ $tabKey }}"
                >
                    <span class="solar-source-tab__label">{{ $tab['label'] }}</span>
                    <span class="solar-source-tab__meta">{{ $tab['meta'] }} · <span {{ $tab['countAttribute'] }} data-count="{{ $tab['count'] }}">{{ number_format($tab['count'], 0, ',', '.') }}</span> registros</span>
                </a>
            @endforeach
        </nav>

        {{-- ══════════════════════════════════════════════
             1. AMBIENT WEATHER
        ══════════════════════════════════════════════ --}}
        <section
            class="solar-card"
            id="api-panel-ambient"
            role="tabpanel"
            aria-labelledby="api-tab-ambient"
            tabindex="0"
            data-api-pagination-section="ambient"
            data-api-sync-section="ambient"
            data-api-tab-panel="ambient"
            @if ($activeTab !== 'ambient') hidden @endif
        >
            <div class="solar-page-header solar-api-section-header">
                <div>
                    <p class="solar-kicker">Ambient Weather</p>
                    <h2 class="text-2xl text-[color:var(--solar-text)]">Estacion IoT — UniGuajiraPtG</h2>
                    <p class="solar-subtitle mt-2">Lecturas en tiempo real desde la estacion Ambient Weather conectada. Temperatura, radiacion solar, viento y lluvia con actualizacion automatica unificada cada 5 minutos.</p>
                    <p class="mt-2 text-sm text-[color:var(--solar-text-muted)]" data-api-sync-status="ambient">
                        Actualizacion automatica unificada activa.
                    </p>
                </div>
                <div class="solar-api-actions">
                    <span class="solar-pill solar-pill-warn" data-ambient-count-pill>
                        {{ number_format($ambientCount, 0, ',', '.') }} registros
                    </span>
                    @can('sync-climate-data')
                    <form method="POST" action="{{ route('api-data.fetch-ambient-data') }}" data-api-fetch-form="ambient">
                        @csrf
                        <button type="submit" class="solar-button-secondary">Sincronizar Ambient Weather</button>
                    </form>
                    @endcan
                </div>
            </div>

            <script id="ambient-realtime-chart-data" type="application/json">@json($ambientChartRows)</script>

            <div class="mt-6 solar-api-chart-grid">
                <div class="solar-table-shell p-4">
                    <div class="solar-api-chart-frame">
                        <canvas id="ambient-realtime-chart" aria-label="Radiacion solar y temperatura Ambient Weather" role="img"></canvas>
                    </div>
                </div>

                <div class="solar-metric-card" data-ambient-iuv-card>
                    <p class="solar-metric-label">IUV actual (Ambient)</p>
                    <p class="solar-metric-value" data-ambient-iuv-value>{{ $latestAmbientUv !== null ? $formatNumber($latestAmbientUv, 2) : 'N/A' }}</p>
                    <p class="solar-metric-copy" data-ambient-iuv-risk>{{ $ambientUvRisk }}</p>
                    <div class="mt-4 h-3 overflow-hidden rounded-full bg-[color:var(--solar-border)]">
                        <div class="h-full rounded-full bg-[color:var(--solar-sun)] transition-all" data-ambient-iuv-bar style="width: {{ $ambientUvPercent }}%"></div>
                    </div>
                </div>
            </div>

            <div class="solar-table-shell mt-6">
                <div class="solar-api-table-scroll">
                    <table class="solar-table solar-api-table" style="--api-table-min: 56rem;">
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
                        <tbody data-ambient-rows>
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
        <section
            class="solar-card"
            id="api-panel-weather-station"
            role="tabpanel"
            aria-labelledby="api-tab-weather-station"
            tabindex="0"
            data-api-pagination-section="weather-station"
            data-api-sync-section="weather-station"
            data-api-tab-panel="weather-station"
            @if ($activeTab !== 'weather-station') hidden @endif
        >
            <div class="solar-page-header solar-api-section-header">
                <div>
                    <p class="solar-kicker">Estacion local</p>
                    <h2 class="text-2xl text-[color:var(--solar-text)]">Centro meteorologico</h2>
                    <p class="solar-subtitle mt-2">Lecturas locales con mas personalidad visual y mejor lectura de variables ambientales.</p>
                    <p class="mt-2 text-sm text-[color:var(--solar-text-muted)]" data-api-sync-status="weather-station">
                        Actualizacion automatica unificada activa.
                    </p>
                </div>
                <div class="solar-api-actions">
                    <span class="solar-pill solar-pill-warn" data-weather-station-count-pill>
                        {{ number_format($weatherStationCount, 0, ',', '.') }} registros
                    </span>
                    @can('sync-climate-data')
                    <form method="POST" action="{{ route('api-data.fetch-weather-station-data') }}" data-api-fetch-form="weather-station">
                        @csrf
                        <button type="submit" class="solar-button-secondary">Obtener datos de estacion</button>
                    </form>
                    @endcan
                </div>
            </div>

            <script id="weather-station-realtime-chart-data" type="application/json">@json($weatherStationChartRows)</script>

            <div class="mt-6 solar-api-chart-grid">
                <div class="solar-table-shell p-4">
                    <div class="solar-api-chart-frame">
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
                <div class="solar-api-table-scroll">
                    <table class="solar-table solar-api-table" style="--api-table-min: 76rem;">
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
        <section
            class="solar-card"
            id="api-panel-nasa"
            role="tabpanel"
            aria-labelledby="api-tab-nasa"
            tabindex="0"
            data-api-pagination-section="nasa"
            data-api-sync-section="nasa"
            data-api-tab-panel="nasa"
            @if ($activeTab !== 'nasa') hidden @endif
        >
            <div class="solar-page-header">
                <div>
                    <p class="solar-kicker">NASA power</p>
                    <h2 class="text-2xl text-[color:var(--solar-text)]">Fuente satelital</h2>
                    <p class="solar-subtitle mt-2">Radiación diaria satelital de NASA POWER. Los últimos días aparecen como estimados hasta que NASA los publica; luego se confirman solos.</p>
                    <p class="mt-2 text-sm text-[color:var(--solar-text-muted)]" data-api-sync-status="nasa">
                        Sincronizacion manual disponible.
                    </p>
                </div>
                <div class="solar-api-actions">
                    <span class="solar-pill" data-api-data-nasa-count-pill>{{ number_format($nasaCount, 0, ',', '.') }} registros</span>
                    @can('sync-climate-data')
                    <form method="POST" action="{{ route('api-data.fetch-nasa-data') }}" data-api-fetch-form="nasa">
                        @csrf
                        <button type="submit" class="solar-button">Obtener datos NASA POWER</button>
                    </form>
                    @endcan
                </div>
            </div>

            {{-- Daily radiation: real vs estimated (ADR-0009) --}}
            <script id="nasa-daily-chart-data" type="application/json">@json($nasaChartRows)</script>
            <div class="mt-6 solar-api-chart-frame">
                <canvas id="nasa-daily-chart" aria-label="Radiacion diaria NASA POWER, distinguiendo dias reales y estimados" role="img"></canvas>
            </div>
            <p class="mt-2 text-xs text-[color:var(--solar-text-muted)]">
                Últimos 90 días. Barras sólidas: dato real publicado por NASA. Barras claras: estimación provisional.
            </p>

            <div class="solar-table-shell mt-6">
                <div class="solar-api-table-scroll">
                    <table class="solar-table solar-api-table" style="--api-table-min: 58rem;">
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
                        <tbody data-nasa-rows>
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
