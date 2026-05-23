@php
    $formatNumber = fn ($value, int $decimals = 2) => $value !== null ? number_format((float) $value, $decimals, ',', '.') : 'N/A';
    $formatDate = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i') : 'N/A';
    $totalRows = $nasaCount + $weatherStationCount;
@endphp

<x-layouts::app :title="__('Datos APIs')">
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Datos APIs</h1>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Registros obtenidos desde NASA POWER y el centro meteorologico.</p>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->has('nasa_data'))
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200">
                {{ $errors->first('nasa_data') }}
            </div>
        @endif

        @if ($errors->has('weather_station'))
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200">
                {{ $errors->first('weather_station') }}
            </div>
        @endif

        <dl class="grid gap-4 text-sm sm:grid-cols-3">
            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <dt class="text-zinc-500 dark:text-zinc-400">Total registros</dt>
                <dd class="mt-2 text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ number_format($totalRows, 0, ',', '.') }}</dd>
            </div>
            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <dt class="text-zinc-500 dark:text-zinc-400">NASA POWER</dt>
                <dd class="mt-2 text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ number_format($nasaCount, 0, ',', '.') }}</dd>
            </div>
            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <dt class="text-zinc-500 dark:text-zinc-400">Centro meteorologico</dt>
                <dd class="mt-2 text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ number_format($weatherStationCount, 0, ',', '.') }}</dd>
            </div>
        </dl>

        <section class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">NASA POWER</h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Datos climaticos sincronizados desde NASA POWER.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-sky-100 px-3 py-1 text-sm font-medium text-sky-700 dark:bg-sky-950 dark:text-sky-200">
                        {{ number_format($nasaCount, 0, ',', '.') }} registros
                    </span>
                    <form method="POST" action="{{ route('api-data.fetch-nasa-data') }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200">
                            Obtener datos NASA POWER
                        </button>
                    </form>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 text-left text-xs font-medium uppercase tracking-wide text-zinc-500 dark:bg-zinc-950 dark:text-zinc-400">
                            <tr>
                                <th class="px-4 py-3">Proyecto</th>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3">Radiacion</th>
                                <th class="px-4 py-3">Temp.</th>
                                <th class="px-4 py-3">Humedad</th>
                                <th class="px-4 py-3">Precipitacion</th>
                                <th class="px-4 py-3">Viento</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse ($nasaRows as $row)
                                <tr class="text-zinc-700 dark:text-zinc-200">
                                    <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-50">{{ $row->project_name ?? 'Sin asociar' }}</td>
                                    <td class="px-4 py-3">{{ $formatDate($row->recorded_at) }}</td>
                                    <td class="px-4 py-3">{{ $formatNumber($row->radiation, 3) }}</td>
                                    <td class="px-4 py-3">{{ $formatNumber($row->temperature, 2) }}</td>
                                    <td class="px-4 py-3">{{ $formatNumber($row->humidity, 2) }}</td>
                                    <td class="px-4 py-3">{{ $formatNumber($row->precipitation, 4) }}</td>
                                    <td class="px-4 py-3">{{ $formatNumber($row->wind_speed, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-zinc-600 dark:text-zinc-400">
                                        Aun no hay datos registrados desde NASA POWER.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $nasaRows->links() }}
        </section>

        <section class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-50">Centro meteorologico</h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">Lecturas recibidas desde la estacion local.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-200">
                        {{ number_format($weatherStationCount, 0, ',', '.') }} registros
                    </span>
                    <form method="POST" action="{{ route('api-data.fetch-weather-station-data') }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white hover:bg-amber-600 dark:bg-amber-400 dark:text-zinc-950 dark:hover:bg-amber-300">
                            Obtener datos de estacion
                        </button>
                    </form>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead class="bg-zinc-50 text-left text-xs font-medium uppercase tracking-wide text-zinc-500 dark:bg-zinc-950 dark:text-zinc-400">
                            <tr>
                                <th class="px-4 py-3">Proyecto</th>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-4 py-3">Dispositivo</th>
                                <th class="px-4 py-3">Radiacion</th>
                                <th class="px-4 py-3">Temp.</th>
                                <th class="px-4 py-3">Humedad</th>
                                <th class="px-4 py-3">Sensacion termica</th>
                                <th class="px-4 py-3">CO2</th>
                                <th class="px-4 py-3">PM2.5</th>
                                <th class="px-4 py-3">PM10</th>
                                <th class="px-4 py-3">UVA</th>
                                <th class="px-4 py-3">UVB</th>
                                <th class="px-4 py-3">IUV</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @forelse ($weatherStationRows as $row)
                                <tr class="text-zinc-700 dark:text-zinc-200">
                                    <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-50">{{ $row->project_name ?? 'Sin asociar' }}</td>
                                    <td class="px-4 py-3">{{ $formatDate($row->recorded_at) }}</td>
                                    <td class="px-4 py-3">{{ $row->device_code ?? 'N/A' }}</td>
                                    <td class="px-4 py-3">{{ $formatNumber($row->radiation, 3) }}</td>
                                    <td class="px-4 py-3">{{ $formatNumber($row->temperature, 2) }}</td>
                                    <td class="px-4 py-3">{{ $formatNumber($row->humidity, 2) }}</td>
                                    <td class="px-4 py-3">{{ $formatNumber($row->thermal_sensation, 2) }}</td>
                                    <td class="px-4 py-3">{{ $row->co2 ?? 'N/A' }}</td>
                                    <td class="px-4 py-3">{{ $formatNumber($row->pm25, 2) }}</td>
                                    <td class="px-4 py-3">{{ $formatNumber($row->pm10, 2) }}</td>
                                    <td class="px-4 py-3">{{ $formatNumber($row->uva, 3) }}</td>
                                    <td class="px-4 py-3">{{ $formatNumber($row->uvb, 3) }}</td>
                                    <td class="px-4 py-3">{{ $formatNumber($row->uv_index, 3) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="px-4 py-8 text-center text-zinc-600 dark:text-zinc-400">
                                        Aun no hay lecturas registradas desde el centro meteorologico.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $weatherStationRows->links() }}
        </section>
    </div>
</x-layouts::app>
