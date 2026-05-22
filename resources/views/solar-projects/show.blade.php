@php
    $technicalParameter = $solarProject->technicalParameter;
    $calculationResult = $solarProject->calculationResult;
    $monthlyResults = $solarProject->monthlyResults;
    $hasWeatherData = $solarProject->weather_data_count > 0;
    $weatherStationStats = $weatherStationStats ?? [];
    $recentWeatherStationReadings = $recentWeatherStationReadings ?? collect();
    $hasWeatherStationData = ($weatherStationStats['total'] ?? 0) > 0;
    $latestWeatherStationReading = $weatherStationStats['latest'] ?? null;

    $formatNumber = fn ($value, int $decimals = 2) => number_format((float) $value, $decimals, ',', '.');
    $formatKwh = fn ($value) => $formatNumber($value) . ' kWh';
    $formatKwp = fn ($value) => $formatNumber($value) . ' kWp';
    $formatPercent = fn ($value) => $formatNumber($value) . '%';
    $formatMoney = fn ($value) => '$ ' . number_format((float) $value, 0, ',', '.') . ' COP';
    $formatCoordinate = fn ($value) => number_format((float) $value, 4, '.', '');
    $energyDifference = $calculationResult
        ? (float) $calculationResult->estimated_annual_generation_kwh - (float) $calculationResult->annual_consumption_kwh
        : null;
@endphp

<x-layouts::app :title="$solarProject->name">
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">Dashboard solar</p>
                <h1 class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-zinc-50">{{ $solarProject->name }}</h1>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $solarProject->location_name }}</p>
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

        <section class="space-y-4 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Informacion general del proyecto</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Simulacion fotovoltaica conectada a red para Riohacha, La Guajira.</p>
            </div>

            <dl class="grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Nombre del proyecto</dt>
                    <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ $solarProject->name }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Ubicacion fija</dt>
                    <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ $solarProject->location_name }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Coordenadas fijas</dt>
                    <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatCoordinate($solarProject->latitude) }}, {{ $formatCoordinate($solarProject->longitude) }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Registros climaticos almacenados</dt>
                    <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ number_format($solarProject->weather_data_count, 0, ',', '.') }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Lecturas centro meteorologico</dt>
                    <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ number_format($weatherStationStats['total'] ?? 0, 0, ',', '.') }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Fecha inicial</dt>
                    <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ $solarProject->start_date->format('Y-m-d') }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Fecha final</dt>
                    <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ $solarProject->end_date->format('Y-m-d') }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Consumo anual</dt>
                    <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatKwh($solarProject->annual_consumption_kwh) }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 dark:text-zinc-400">Tarifa energetica</dt>
                    <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatMoney($solarProject->energy_rate_cop_kwh) }}/kWh</dd>
                </div>
            </dl>

            <div>
                <h3 class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Descripcion</h3>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $solarProject->description ?: 'Sin descripcion registrada.' }}</p>
            </div>

            @unless ($hasWeatherData)
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                    Este proyecto aun no tiene datos climaticos sincronizados. Sincroniza los datos NASA POWER antes de ejecutar los calculos.
                </div>
            @endunless
        </section>

        <section class="space-y-4 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Parametros tecnicos</h2>

            @if ($technicalParameter)
                <dl class="grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-3">
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Area disponible</dt>
                        <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatNumber($technicalParameter->available_area_m2) }} m2</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Porcentaje de area utilizable</dt>
                        <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatPercent($technicalParameter->usable_area_percentage) }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Potencia del panel</dt>
                        <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatNumber($technicalParameter->panel_power_w) }} W</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Area del panel</dt>
                        <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatNumber($technicalParameter->panel_area_m2) }} m2</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Performance ratio</dt>
                        <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatNumber($technicalParameter->performance_ratio, 3) }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Perdidas del sistema</dt>
                        <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatPercent($technicalParameter->system_losses_percentage) }}</dd>
                    </div>
                </dl>
            @else
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                    Este proyecto aun no tiene parametros tecnicos registrados.
                </div>
            @endif
        </section>

        <section class="space-y-4 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Acciones principales</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Consulta clima, ejecuta la simulacion y administra el proyecto.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('solar-projects.fetch-weather-data', $solarProject) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200">
                        Consultar o sincronizar datos NASA POWER
                    </button>
                </form>

                <form method="POST" action="{{ route('solar-projects.fetch-weather-station-data', $solarProject) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white hover:bg-amber-600 dark:bg-amber-400 dark:text-zinc-950 dark:hover:bg-amber-300">
                        Obtener datos desde centro metereologico
                    </button>
                </form>

                <form method="POST" action="{{ route('solar-projects.calculate', $solarProject) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200">
                        Ejecutar calculos solares
                    </button>
                </form>

                <a href="{{ route('solar-projects.edit', $solarProject) }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">
                    Editar proyecto
                </a>

                <a href="{{ route('solar-projects.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">
                    Volver al listado
                </a>
            </div>
        </section>

        <section class="space-y-4 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <div>
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Centro meteorologico</h2>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Lecturas locales de radiacion tomadas con la logica de UVA, UVB e indice UV del sistema de estacion.</p>
            </div>

            @if ($hasWeatherStationData)
                <dl class="grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <dt class="text-zinc-500 dark:text-zinc-400">Lecturas asociadas</dt>
                        <dd class="mt-2 text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ number_format($weatherStationStats['total'], 0, ',', '.') }}</dd>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <dt class="text-zinc-500 dark:text-zinc-400">Radiacion promedio</dt>
                        <dd class="mt-2 text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatNumber($weatherStationStats['averageRadiation'] ?? 0, 3) }}</dd>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <dt class="text-zinc-500 dark:text-zinc-400">Indice UV maximo</dt>
                        <dd class="mt-2 text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatNumber($weatherStationStats['maxUvIndex'] ?? 0, 2) }}</dd>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <dt class="text-zinc-500 dark:text-zinc-400">Ultima medicion</dt>
                        <dd class="mt-2 text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ $latestWeatherStationReading?->measured_at?->format('Y-m-d H:i') ?? 'Sin fecha' }}</dd>
                    </div>
                </dl>

                <div class="grid gap-4 xl:grid-cols-2">
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Radiacion diaria del centro meteorologico</h3>
                        <div class="mt-4 h-72">
                            <canvas id="weather-station-radiation-chart" aria-label="Radiacion diaria del centro meteorologico" role="img"></canvas>
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                            <thead>
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

                <script type="application/json" id="weather-station-chart-data">@json($weatherStationChartData)</script>
            @else
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                    Aun no hay lecturas del centro meteorologico asociadas a este proyecto. Cuando la estacion envie datos al endpoint o existan lecturas sin asociar en el rango, usa el boton para obtenerlas.
                </div>
            @endif
        </section>

        <section class="space-y-4 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Resultados generales</h2>

            @if ($calculationResult)
                <dl class="grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-3">
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <dt class="text-zinc-500 dark:text-zinc-400">Area util disponible</dt>
                        <dd class="mt-2 text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatNumber($calculationResult->usable_area_m2) }} m2</dd>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <dt class="text-zinc-500 dark:text-zinc-400">Numero de paneles</dt>
                        <dd class="mt-2 text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ number_format($calculationResult->number_of_panels, 0, ',', '.') }}</dd>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <dt class="text-zinc-500 dark:text-zinc-400">Capacidad instalada</dt>
                        <dd class="mt-2 text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatKwp($calculationResult->installed_capacity_kwp) }}</dd>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <dt class="text-zinc-500 dark:text-zinc-400">Generacion diaria estimada</dt>
                        <dd class="mt-2 text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatKwh($calculationResult->estimated_daily_generation_kwh) }}</dd>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <dt class="text-zinc-500 dark:text-zinc-400">Generacion mensual promedio estimada</dt>
                        <dd class="mt-2 text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatKwh($calculationResult->estimated_monthly_generation_kwh) }}</dd>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <dt class="text-zinc-500 dark:text-zinc-400">Generacion anual estimada</dt>
                        <dd class="mt-2 text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatKwh($calculationResult->estimated_annual_generation_kwh) }}</dd>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <dt class="text-zinc-500 dark:text-zinc-400">Consumo anual</dt>
                        <dd class="mt-2 text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatKwh($calculationResult->annual_consumption_kwh) }}</dd>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <dt class="text-zinc-500 dark:text-zinc-400">Cobertura energetica</dt>
                        <dd class="mt-2 text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatPercent($calculationResult->coverage_percentage) }}</dd>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <dt class="text-zinc-500 dark:text-zinc-400">Ahorro anual estimado</dt>
                        <dd class="mt-2 text-xl font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatMoney($calculationResult->estimated_annual_savings_cop) }}</dd>
                    </div>
                </dl>
            @else
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                    Ejecuta los calculos solares para visualizar los resultados.
                </div>
            @endif
        </section>

        @if ($calculationResult)
            <section class="space-y-4 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Interpretacion de resultados</h2>
                <p class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $coverageInterpretation }}</p>

                <dl class="grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Energia anual generada</dt>
                        <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatKwh($calculationResult->estimated_annual_generation_kwh) }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Consumo anual</dt>
                        <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatKwh($calculationResult->annual_consumption_kwh) }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Diferencia generacion - consumo</dt>
                        <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatKwh($energyDifference) }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Ahorro anual estimado</dt>
                        <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ $formatMoney($calculationResult->estimated_annual_savings_cop) }}</dd>
                    </div>
                </dl>
            </section>
        @endif

        @if ($monthlyResults->isNotEmpty())
            <section class="space-y-4 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <div>
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Graficos de resultados</h2>
                    <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Visualizacion mensual de generacion, consumo, ahorro y cobertura energetica.</p>
                </div>

                <div class="grid gap-4 xl:grid-cols-2">
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Generacion mensual estimada</h3>
                        <div class="mt-4 h-72">
                            <canvas id="solar-generation-chart" aria-label="Generacion mensual estimada" role="img"></canvas>
                        </div>
                    </div>

                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Consumo vs generacion</h3>
                        <div class="mt-4 h-72">
                            <canvas id="solar-consumption-generation-chart" aria-label="Consumo vs generacion" role="img"></canvas>
                        </div>
                    </div>

                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Ahorro mensual estimado</h3>
                        <div class="mt-4 h-72">
                            <canvas id="solar-savings-chart" aria-label="Ahorro mensual estimado" role="img"></canvas>
                        </div>
                    </div>

                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-50">Cobertura energetica mensual</h3>
                        <div class="mt-4 h-72">
                            <canvas id="solar-coverage-chart" aria-label="Cobertura energetica mensual" role="img"></canvas>
                        </div>
                    </div>
                </div>

                <script type="application/json" id="solar-monthly-chart-data">@json($chartData)</script>
            </section>
        @else
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                Ejecuta los calculos solares para visualizar los graficos del proyecto.
            </div>
        @endif

        <section class="space-y-4 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Resultados mensuales</h2>

            @if ($monthlyResults->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                        <thead>
                            <tr class="text-left text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                <th class="px-3 py-2">Mes</th>
                                <th class="px-3 py-2">Dias</th>
                                <th class="px-3 py-2">Radiacion solar promedio diaria</th>
                                <th class="px-3 py-2">Generacion estimada</th>
                                <th class="px-3 py-2">Consumo estimado</th>
                                <th class="px-3 py-2">Cobertura</th>
                                <th class="px-3 py-2">Ahorro estimado</th>
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
                        <tfoot class="border-t border-zinc-200 text-sm font-semibold text-zinc-900 dark:border-zinc-700 dark:text-zinc-50">
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
            @else
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                    {{ $calculationResult ? 'Los calculos generales existen, pero aun no hay resultados mensuales registrados.' : 'Ejecuta los calculos solares para visualizar los resultados mensuales.' }}
                </div>
            @endif
        </section>

        @if ($monthlyResults->isNotEmpty())
            <section class="space-y-4 rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
                <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Mejores y peores meses</h2>

                <dl class="grid gap-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Mayor generacion estimada</dt>
                        <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ ucfirst($monthlyHighlights['highestGeneration']->month_name) }} - {{ $formatKwh($monthlyHighlights['highestGeneration']->estimated_generation_kwh) }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Menor generacion estimada</dt>
                        <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ ucfirst($monthlyHighlights['lowestGeneration']->month_name) }} - {{ $formatKwh($monthlyHighlights['lowestGeneration']->estimated_generation_kwh) }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Mayor ahorro estimado</dt>
                        <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ ucfirst($monthlyHighlights['highestSavings']->month_name) }} - {{ $formatMoney($monthlyHighlights['highestSavings']->estimated_savings_cop) }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">Menor cobertura energetica</dt>
                        <dd class="mt-1 font-semibold text-zinc-900 dark:text-zinc-50">{{ ucfirst($monthlyHighlights['lowestCoverage']->month_name) }} - {{ $formatPercent($monthlyHighlights['lowestCoverage']->coverage_percentage) }}</dd>
                    </div>
                </dl>
            </section>
        @endif
    </div>
</x-layouts::app>
