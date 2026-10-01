<x-layouts::app :title="__('Guía del recibo')">
    <div class="solar-page solar-page-narrow">
        <section class="solar-hero">
            <p class="solar-kicker">Recursos</p>
            <h1 class="solar-title">Cómo leer tu recibo de energía</h1>
            <p class="solar-subtitle">Para estimar tu sistema solar necesitamos dos datos de tu recibo: cuánta energía consumes al mes y cuánto pagas por cada kWh.</p>
        </section>

        <section class="solar-card">
            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <p class="solar-kicker">Dato 1</p>
                    <h2 class="text-lg font-semibold text-[color:var(--solar-text)]">Consumo mensual (kWh)</h2>
                    <p class="solar-subtitle mt-1">La energía que usaste en el periodo facturado. Si tu consumo cambia mucho entre meses, usa el promedio de los últimos seis.</p>
                </div>
                <div>
                    <p class="solar-kicker">Dato 2</p>
                    <h2 class="text-lg font-semibold text-[color:var(--solar-text)]">Tarifa (COP/kWh)</h2>
                    <p class="solar-subtitle mt-1">Lo que cuesta cada kWh. Con este valor calculamos cuánto ahorrarías cada mes con paneles solares.</p>
                </div>
            </div>
        </section>

        <section class="solar-card">
            <p class="solar-subtitle mb-4">En la segunda hoja de tu recibo encontrarás este apartado con ambos datos:</p>
            <img
                src="{{ asset('images/guia-recibo-energia.jpeg') }}"
                alt="Guía visual del recibo de energía donde se encuentran la tarifa y el consumo en kWh"
                class="w-full rounded-xl"
            >
        </section>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('solar-projects.create') }}" class="solar-button" wire:navigate>Crear un proyecto con estos datos</a>
            <a href="{{ route('solar-projects.index') }}" class="solar-button-ghost" wire:navigate>Volver a mis proyectos</a>
        </div>
    </div>
</x-layouts::app>
