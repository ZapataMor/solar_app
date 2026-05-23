@php
    $formatNumber = fn ($value, int $decimals = 2) => $value !== null ? number_format((float) $value, $decimals, ',', '.') : 'N/A';
    $formatDate = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i') : 'N/A';
    $totalRows = $nasaCount + $weatherStationCount;
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

            <div class="mt-6 grid gap-4 md:grid-cols-3">
                <div class="solar-metric-card">
                    <p class="solar-metric-label">Total registros</p>
                    <p class="solar-metric-value" data-api-data-total-count>{{ number_format($totalRows, 0, ',', '.') }}</p>
                    <p class="solar-metric-copy">Base consolidada para decisiones de energia, radiacion y riesgo operativo.</p>
                </div>
                <div class="solar-metric-card">
                    <p class="solar-metric-label">NASA POWER</p>
                    <p class="solar-metric-value" data-api-data-nasa-count data-count="{{ $nasaCount }}">{{ number_format($nasaCount, 0, ',', '.') }}</p>
                    <p class="solar-metric-copy">Fuente satelital para comparacion y cobertura historica.</p>
                </div>
                <div class="solar-metric-card">
                    <p class="solar-metric-label">Estacion local</p>
                    <p class="solar-metric-value" data-weather-station-count data-count="{{ $weatherStationCount }}">{{ number_format($weatherStationCount, 0, ',', '.') }}</p>
                    <p class="solar-metric-copy">Lecturas de contexto real para Riohacha y seguimiento ambiental.</p>
                </div>
            </div>
        </section>

        @if (session('status'))
            <div class="solar-alert solar-alert-success">
                {{ session('status') }}
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

        <section class="solar-card" data-weather-station-sync data-sync-interval="15000">
            <div class="solar-page-header">
                <div>
                    <p class="solar-kicker">Estacion local</p>
                    <h2 class="text-2xl text-[color:var(--solar-text)]">Centro meteorologico</h2>
                    <p class="solar-subtitle mt-2">Lecturas locales con mas personalidad visual y mejor lectura de variables ambientales.</p>
                    <p class="mt-2 text-sm text-[color:var(--solar-text-muted)]" data-weather-station-status>
                        Actualizacion automatica activa.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="solar-pill solar-pill-warn" data-weather-station-count-pill>
                        {{ number_format($weatherStationCount, 0, ',', '.') }} registros
                    </span>
                    <form method="POST" action="{{ route('api-data.fetch-weather-station-data') }}" data-weather-station-fetch-form>
                        @csrf
                        <button type="submit" class="solar-button-secondary">Obtener datos de estacion</button>
                    </form>
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

            <div class="solar-pagination mt-5">
                {{ $weatherStationRows->links() }}
            </div>
        </section>

        <section class="solar-card">
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
                                <th>Radiacion</th>
                                <th>Temp.</th>
                                <th>Humedad</th>
                                <th>Precipitacion</th>
                                <th>Viento</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($nasaRows as $row)
                                <tr>
                                    <td class="font-semibold text-[color:var(--solar-text)]">{{ $formatDate($row->recorded_at) }}</td>
                                    <td>{{ $formatNumber($row->radiation, 3) }}</td>
                                    <td>{{ $formatNumber($row->temperature, 2) }}</td>
                                    <td>{{ $formatNumber($row->humidity, 2) }}</td>
                                    <td>{{ $formatNumber($row->precipitation, 4) }}</td>
                                    <td>{{ $formatNumber($row->wind_speed, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-10 text-center">
                                        Aun no hay datos registrados desde NASA POWER.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="solar-pagination mt-5">
                {{ $nasaRows->links() }}
            </div>
        </section>
    </div>
</x-layouts::app>
