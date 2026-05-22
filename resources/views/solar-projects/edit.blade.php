<x-layouts::app :title="__('Editar proyecto solar')">
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Editar proyecto solar</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">{{ $solarProject->name }}</p>
        </div>

        @include('solar-projects._form', [
            'action' => route('solar-projects.update', $solarProject),
            'method' => 'PUT',
            'buttonText' => 'Actualizar proyecto',
            'solarProject' => $solarProject,
        ])
    </div>
</x-layouts::app>
