<x-layouts::app :title="__('Proyectos solares')">
    <div class="solar-page">
        <div class="solar-page-header items-center">
            <h1 class="solar-title mt-0">Portafolio</h1>

            <a href="{{ route('solar-projects.create') }}" class="solar-button">
                Nuevo proyecto
            </a>
        </div>

        @if (session('status'))
            <div class="solar-alert solar-alert-success">
                {{ session('status') }}
            </div>
        @endif

        @if ($solarProjects->isEmpty())
            <div class="solar-empty-state">
                <div class="solar-empty-state__glow" aria-hidden="true"></div>
                <p class="solar-kicker">Portafolio vacio</p>
                <h3 class="mt-3 text-3xl text-[color:var(--solar-text)]">
                    {{ $isAdmin ? 'Aun no hay proyectos registrados en la plataforma.' : 'Todavia no has creado tu primer proyecto solar.' }}
                </h3>
                <p class="solar-subtitle mx-auto mt-3 max-w-2xl">
                    {{ $isAdmin
                        ? 'Cuando los usuarios comiencen a registrar escenarios, apareceran aqui con sus indicadores principales.'
                        : 'Crea un proyecto para empezar a modelar consumo, cobertura energetica y ahorro anual en una experiencia lista para demo.' }}
                </p>
                <div class="mt-6">
                    <a href="{{ route('solar-projects.create') }}" class="solar-button">
                        Crear proyecto
                    </a>
                </div>
            </div>
        @else
            <div class="solar-project-grid">
                @foreach ($solarProjects as $solarProject)
                    @include('solar-projects.partials.project-card', ['solarProject' => $solarProject])
                @endforeach
            </div>
        @endif

        <div class="solar-pagination">
            {{ $solarProjects->links() }}
        </div>
    </div>
</x-layouts::app>
