<x-layouts::app :title="__('Crear proyecto solar')">
    <div class="solar-page solar-page-narrow">
        <section class="solar-hero">
            <p class="solar-kicker">Nuevo escenario</p>
            <h1 class="solar-title">Crear proyecto solar</h1>
            <p class="solar-subtitle">Registra el contexto tecnico inicial del sistema para convertir radiacion, clima y consumo en una lectura ejecutiva clara.</p>
        </section>

        @include('solar-projects._form', [
            'action' => route('solar-projects.store'),
            'method' => 'POST',
            'buttonText' => 'Guardar proyecto',
            'solarProject' => null,
        ])
    </div>
</x-layouts::app>
