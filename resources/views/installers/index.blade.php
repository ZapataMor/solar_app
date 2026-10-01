<x-layouts::app :title="__('Instaladores')">
    <div class="solar-page solar-page-narrow">
        <section class="solar-hero">
            <p class="solar-kicker">Próximamente</p>
            <h1 class="solar-title">Instaladores de tu zona</h1>
            <p class="solar-subtitle">Estamos armando la red de instaladores aliados de La Guajira. Muy pronto, desde tu proyecto podrás pedirles una cotización con la estimación que ya calculaste.</p>
        </section>

        <section class="solar-card">
            <h2 class="text-lg font-semibold text-[color:var(--solar-text)]">Cómo va a funcionar</h2>
            <ol class="mt-3 grid gap-3 text-[color:var(--solar-text-muted)]">
                <li><strong class="text-[color:var(--solar-text)]">1.</strong> Calculas tu proyecto: consumo, ubicación y área disponible.</li>
                <li><strong class="text-[color:var(--solar-text)]">2.</strong> Te mostramos instaladores aliados que cubren tu municipio.</li>
                <li><strong class="text-[color:var(--solar-text)]">3.</strong> Les pides cotización y comparan sobre la misma estimación.</li>
            </ol>
        </section>

        <section class="solar-card">
            <p class="solar-subtitle">Mientras tanto, deja tu proyecto listo: cuando abramos la red quedará disponible para cotizar.</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('solar-projects.create') }}" class="solar-button" wire:navigate>Crear un proyecto</a>
                <a href="{{ route('solar-projects.index') }}" class="solar-button-ghost" wire:navigate>Ver mis proyectos</a>
            </div>
        </section>
    </div>
</x-layouts::app>
