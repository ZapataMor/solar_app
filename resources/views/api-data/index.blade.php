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

            <form method="GET" action="{{ route('api-data.index') }}" class="flex items-center gap-2">
                <label for="source" class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Fuente</label>
                <select id="source" name="source" class="rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100" onchange="this.form.submit()">
                    <option value="all" @selected($source === 'all')>Todas</option>
                    <option value="nasa" @selected($source === 'nasa')>NASA POWER</option>
                    <option value="weather_station" @selected($source === 'weather_station')>Centro meteorologico</option>
                </select>
            </form>
        </div>

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

        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-left text-xs font-medium uppercase tracking-wide text-zinc-500 dark:bg-zinc-950 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3">Fuente</th>
                            <th class="px-4 py-3">Proyecto</th>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Radiacion</th>
                            <th class="px-4 py-3">Temp.</th>
                            <th class="px-4 py-3">Humedad</th>
                            <th class="px-4 py-3">Precipitacion</th>
                            <th class="px-4 py-3">Viento</th>
                            <th class="px-4 py-3">Dispositivo</th>
                            <th class="px-4 py-3">CO2</th>
                            <th class="px-4 py-3">PM2.5</th>
                            <th class="px-4 py-3">PM10</th>
                            <th class="px-4 py-3">UV</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($apiRows as $row)
                            <tr class="text-zinc-700 dark:text-zinc-200">
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $row->source_key === 'nasa' ? 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-200' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-200' }}">
                                        {{ $row->source_name }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-50">{{ $row->project_name ?? 'Sin asociar' }}</td>
                                <td class="px-4 py-3">{{ $formatDate($row->recorded_at) }}</td>
                                <td class="px-4 py-3">{{ $formatNumber($row->radiation, 3) }}</td>
                                <td class="px-4 py-3">{{ $formatNumber($row->temperature, 2) }}</td>
                                <td class="px-4 py-3">{{ $formatNumber($row->humidity, 2) }}</td>
                                <td class="px-4 py-3">{{ $formatNumber($row->precipitation, 4) }}</td>
                                <td class="px-4 py-3">{{ $formatNumber($row->wind_speed, 2) }}</td>
                                <td class="px-4 py-3">{{ $row->device_code ?? 'N/A' }}</td>
                                <td class="px-4 py-3">{{ $row->co2 ?? 'N/A' }}</td>
                                <td class="px-4 py-3">{{ $formatNumber($row->pm25, 2) }}</td>
                                <td class="px-4 py-3">{{ $formatNumber($row->pm10, 2) }}</td>
                                <td class="px-4 py-3">
                                    UVA {{ $formatNumber($row->uva, 3) }} / UVB {{ $formatNumber($row->uvb, 3) }} / IUV {{ $formatNumber($row->uv_index, 3) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="px-4 py-8 text-center text-zinc-600 dark:text-zinc-400">
                                    Aun no hay datos registrados desde las APIs.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $apiRows->links() }}
    </div>
</x-layouts::app>
