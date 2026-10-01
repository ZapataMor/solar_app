<x-layouts::app :title="__('Editar').' · '.$solarProject->name">
    <div class="solar-project-detail">
        @include('solar-projects.partials.project-nav', ['solarProject' => $solarProject, 'active' => 'edit', 'backUrl' => $portfolioUrl])

        <div class="solar-page solar-page-narrow">
            <section class="solar-hero">
                <p class="solar-kicker">Editar datos</p>
                <h1 class="solar-title">{{ $solarProject->name }}</h1>
                <p class="solar-subtitle">Cambia lo que necesites en cualquier etapa y guarda. Al actualizar vuelves al panel del proyecto; recuerda recalcular para ver los nuevos resultados.</p>
            </section>

            @include('solar-projects._form', [
                'action' => route('solar-projects.update', $solarProject),
                'method' => 'PUT',
                'buttonText' => 'Guardar cambios',
                'solarProject' => $solarProject,
            ])
        </div>
    </div>
</x-layouts::app>
