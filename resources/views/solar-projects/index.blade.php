<x-layouts::app :title="__('Proyectos solares')">
    <div class="solar-page">
        <section class="solar-hero">
            <div class="solar-page-header">
                <div>
                    <p class="solar-kicker">Solar command center</p>
                    <h1 class="solar-title">Proyectos solares listos para decision ejecutiva</h1>
                    <p class="solar-subtitle">Gestiona simulaciones fotovoltaicas para Riohacha con una interfaz mas limpia, tecnica y orientada a ahorro, radiacion y operacion energetica.</p>
                </div>

                <a href="{{ route('solar-projects.create') }}" class="solar-button">
                    Nuevo proyecto
                </a>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-3">
                <div class="solar-metric-card">
                    <p class="solar-metric-label">Portafolio activo</p>
                    <p class="solar-metric-value">{{ number_format($solarProjects->total(), 0, ',', '.') }}</p>
                    <p class="solar-metric-copy">Proyectos registrados para modelar consumo, cobertura y ahorro.</p>
                </div>
                <div class="solar-metric-card">
                    <p class="solar-metric-label">Contexto geografico</p>
                    <p class="solar-metric-value">Riohacha</p>
                    <p class="solar-metric-copy">Entorno costero-desertico con alta radiacion y gran potencial solar.</p>
                </div>
                <div class="solar-metric-card">
                    <p class="solar-metric-label">Narrativa visual</p>
                    <p class="solar-metric-value">Solar SaaS</p>
                    <p class="solar-metric-copy">Base lista para demo premium de hackathon sin apariencia de panel generico.</p>
                </div>
            </div>
        </section>

        @if (session('status'))
            <div class="solar-alert solar-alert-success">
                {{ session('status') }}
            </div>
        @endif

        <section class="solar-card">
            <div class="solar-page-header">
                <div>
                    <p class="solar-kicker">Portafolio</p>
                    <h2 class="text-2xl text-[color:var(--solar-text)]">Vista general de proyectos</h2>
                    <p class="solar-subtitle mt-2">La tabla funciona como centro de lectura: mejor contraste, menos ruido y acciones mas claras.</p>
                </div>
                <span class="solar-pill">{{ number_format($solarProjects->count(), 0, ',', '.') }} en esta pagina</span>
            </div>

            <div class="solar-table-shell mt-6">
                <div class="overflow-x-auto">
                    <table class="solar-table min-w-[820px]">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Ubicacion</th>
                                <th>Fecha inicial</th>
                                <th>Fecha final</th>
                                <th>Consumo anual</th>
                                <th>Tarifa</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($solarProjects as $solarProject)
                                <tr>
                                    <td class="font-semibold text-[color:var(--solar-text)]">{{ $solarProject->name }}</td>
                                    <td>{{ $solarProject->location_name }}</td>
                                    <td>{{ $solarProject->start_date->format('Y-m-d') }}</td>
                                    <td>{{ $solarProject->end_date->format('Y-m-d') }}</td>
                                    <td>{{ number_format((float) $solarProject->annual_consumption_kwh, 2) }} kWh</td>
                                    <td>$ {{ number_format((float) $solarProject->energy_rate_cop_kwh, 2) }}</td>
                                    <td>
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('solar-projects.show', $solarProject) }}" class="solar-button-secondary px-3 py-2 text-xs">Ver</a>
                                            <a href="{{ route('solar-projects.edit', $solarProject) }}" class="solar-button-ghost px-3 py-2 text-xs">Editar</a>
                                            <form method="POST" action="{{ route('solar-projects.destroy', $solarProject) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="solar-button-danger px-3 py-2 text-xs">
                                                    Eliminar
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-10 text-center">
                                        No hay proyectos solares registrados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <div class="solar-pagination">
            {{ $solarProjects->links() }}
        </div>
    </div>
</x-layouts::app>
