<x-layouts::app :title="__('Crear proyecto solar')">
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-zinc-900 dark:text-zinc-50">Crear proyecto solar</h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Registra la información técnica inicial del proyecto.</p>
        </div>

        @include('solar-projects._form', [
            'action' => route('solar-projects.store'),
            'method' => 'POST',
            'buttonText' => 'Guardar proyecto',
            'solarProject' => null,
        ])
    </div>
</x-layouts::app>
