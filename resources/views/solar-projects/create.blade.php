<x-layouts::app :title="__('Crear proyecto solar')">
    <div class="solar-page solar-page-narrow">
        <section class="solar-hero">
            <p class="solar-kicker">Nuevo escenario</p>
            <h1 class="solar-title">Crear proyecto solar</h1>
            <p class="solar-subtitle">Cinco pasos cortos y obtienes una estimación de tu sistema solar: cuánto genera, cuánto cuesta y en cuánto tiempo se paga.</p>
        </section>

        @include('solar-projects._form', [
            'action' => route('solar-projects.store'),
            'method' => 'POST',
            'buttonText' => 'Guardar proyecto',
            'solarProject' => null,
        ])
    </div>
</x-layouts::app>
