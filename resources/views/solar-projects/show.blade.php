<x-layouts::app :title="$solarProject->name">
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ $solarProject->name }}</h1>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $solarProject->location_name }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('solar-projects.edit', $solarProject) }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">
                    Editar
                </a>
                <a href="{{ route('solar-projects.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">
                    Volver
                </a>
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

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Periodo</div>
                <div class="mt-2 text-sm font-semibold text-zinc-900 dark:text-zinc-50">{{ $solarProject->start_date->format('Y-m-d') }} a {{ $solarProject->end_date->format('Y-m-d') }}</div>
            </div>
            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Consumo anual</div>
                <div class="mt-2 text-sm font-semibold text-zinc-900 dark:text-zinc-50">{{ number_format((float) $solarProject->annual_consumption_kwh, 2) }} kWh</div>
            </div>
            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Tarifa energética</div>
                <div class="mt-2 text-sm font-semibold text-zinc-900 dark:text-zinc-50">$ {{ number_format((float) $solarProject->energy_rate_cop_kwh, 2) }} COP/kWh</div>
            </div>
            <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Registros climáticos</div>
                <div class="mt-2 text-sm font-semibold text-zinc-900 dark:text-zinc-50">{{ number_format($solarProject->weather_data_count) }}</div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="space-y-4 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Información general</h2>
                <dl class="grid gap-4 text-sm">
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Descripción</dt>
                        <dd class="mt-1 text-zinc-900 dark:text-zinc-50">{{ $solarProject->description ?: 'Sin descripción' }}</dd>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-zinc-500 dark:text-zinc-400">Latitud</dt>
                            <dd class="mt-1 text-zinc-900 dark:text-zinc-50">{{ $solarProject->latitude }}</dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500 dark:text-zinc-400">Longitud</dt>
                            <dd class="mt-1 text-zinc-900 dark:text-zinc-50">{{ $solarProject->longitude }}</dd>
                        </div>
                    </div>
                </dl>
            </section>

            <section class="space-y-4 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Parámetros técnicos</h2>
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Área total disponible</dt>
                        <dd class="mt-1 text-zinc-900 dark:text-zinc-50">{{ number_format((float) $solarProject->technicalParameter->available_area_m2, 2) }} m2</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Área utilizable</dt>
                        <dd class="mt-1 text-zinc-900 dark:text-zinc-50">{{ number_format((float) $solarProject->technicalParameter->usable_area_percentage, 2) }}%</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Potencia del panel</dt>
                        <dd class="mt-1 text-zinc-900 dark:text-zinc-50">{{ number_format((float) $solarProject->technicalParameter->panel_power_w, 2) }} W</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Área del panel</dt>
                        <dd class="mt-1 text-zinc-900 dark:text-zinc-50">{{ number_format((float) $solarProject->technicalParameter->panel_area_m2, 2) }} m2</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Performance ratio</dt>
                        <dd class="mt-1 text-zinc-900 dark:text-zinc-50">{{ number_format((float) $solarProject->technicalParameter->performance_ratio, 3) }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Pérdidas del sistema</dt>
                        <dd class="mt-1 text-zinc-900 dark:text-zinc-50">{{ number_format((float) $solarProject->technicalParameter->system_losses_percentage, 2) }}%</dd>
                    </div>
                </dl>
            </section>
        </div>

        <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">NASA POWER</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Datos horarios de irradiancia, temperatura, humedad, precipitación y viento.</p>
                </div>

                <form method="POST" action="{{ route('solar-projects.fetch-weather-data', $solarProject) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200">
                        Consultar datos NASA POWER
                    </button>
                </form>
            </div>
        </section>
    </div>
</x-layouts::app>
