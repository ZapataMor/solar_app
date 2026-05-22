<x-layouts::app :title="__('Proyectos solares')">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Proyectos solares</h1>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Simulaciones fotovoltaicas para Riohacha, La Guajira.</p>
            </div>

            <a href="{{ route('solar-projects.create') }}" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200">
                Nuevo proyecto
            </a>
        </div>

        @if (session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                    <thead class="bg-zinc-50 text-left text-xs font-medium uppercase tracking-wide text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3">Ubicación</th>
                            <th class="px-4 py-3">Fecha inicial</th>
                            <th class="px-4 py-3">Fecha final</th>
                            <th class="px-4 py-3">Consumo anual</th>
                            <th class="px-4 py-3">Tarifa</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 bg-white dark:divide-zinc-700 dark:bg-zinc-800">
                        @forelse ($solarProjects as $solarProject)
                            <tr>
                                <td class="px-4 py-3 font-medium text-zinc-900 dark:text-zinc-50">{{ $solarProject->name }}</td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $solarProject->location_name }}</td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $solarProject->start_date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ $solarProject->end_date->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">{{ number_format((float) $solarProject->annual_consumption_kwh, 2) }} kWh</td>
                                <td class="px-4 py-3 text-zinc-600 dark:text-zinc-300">$ {{ number_format((float) $solarProject->energy_rate_cop_kwh, 2) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('solar-projects.show', $solarProject) }}" class="rounded-md border border-zinc-300 px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-900">Ver</a>
                                        <a href="{{ route('solar-projects.edit', $solarProject) }}" class="rounded-md border border-zinc-300 px-3 py-1.5 text-xs font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-900">Editar</a>
                                        <form method="POST" action="{{ route('solar-projects.destroy', $solarProject) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/40">
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-zinc-600 dark:text-zinc-400">
                                    No hay proyectos solares registrados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $solarProjects->links() }}
    </div>
</x-layouts::app>
