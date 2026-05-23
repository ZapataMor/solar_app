<x-layouts::app :title="__('Editar proyecto solar')">
    <div class="solar-page solar-page-narrow">
        <section class="solar-hero">
            <p class="solar-kicker">Ajuste tecnico</p>
            <h1 class="solar-title">Editar proyecto solar</h1>
            <p class="solar-subtitle">Refina el escenario energetico de <strong>{{ $solarProject->name }}</strong> sin perder continuidad en la simulacion ni en las vistas del dashboard.</p>
        </section>

        @include('solar-projects._form', [
            'action' => route('solar-projects.update', $solarProject),
            'method' => 'PUT',
            'buttonText' => 'Actualizar proyecto',
            'solarProject' => $solarProject,
        ])
    </div>
</x-layouts::app>
