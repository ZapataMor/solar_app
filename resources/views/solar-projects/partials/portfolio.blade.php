<div class="solar-page">
    <div class="solar-page-header items-center">
        <h1 class="solar-title mt-0">Portafolio</h1>

        <div class="flex flex-wrap items-center gap-3">
            {{-- Smart recalculation: the "!" appears only when some project's data changed since its last calculation. --}}
            <form method="POST" action="{{ route('solar-projects.recalculate-outdated') }}">
                @csrf
                @if (($projectsToRecalculate ?? 0) > 0)
                    <button
                        type="submit"
                        class="solar-button-ghost solar-recalc-button"
                        title="{{ $projectsToRecalculate }} {{ $projectsToRecalculate === 1 ? 'proyecto está' : 'proyectos están' }} sin calcular o con datos nuevos desde su último cálculo"
                    >
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg>
                        Recalcular proyectos
                        <span class="solar-recalc-badge" aria-label="{{ $projectsToRecalculate }} por recalcular">!<span class="solar-recalc-badge__count">{{ $projectsToRecalculate }}</span></span>
                    </button>
                @else
                    <button type="button" class="solar-button-ghost solar-recalc-button" disabled title="Ningún proyecto tiene datos nuevos">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                        Cálculos al día
                    </button>
                @endif
            </form>

            <a href="{{ route('solar-projects.create') }}" class="solar-button">
                Nuevo proyecto
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route('solar-projects.index') }}" class="solar-project-search" role="search">
        <input
            type="search"
            name="search"
            value="{{ $search }}"
            class="solar-input"
            placeholder="Buscar proyecto por nombre"
            aria-label="Buscar proyecto por nombre"
        >
        <button type="submit" class="solar-button">Buscar</button>
        @if ($search !== '')
            <a href="{{ route('solar-projects.index') }}" class="solar-button-ghost">Limpiar</a>
        @endif
    </form>

    @if ($solarProjects->isEmpty() && $search !== '')
        <div class="solar-empty-state">
            <p class="solar-kicker">Sin resultados</p>
            <h3 class="mt-3 text-3xl text-[color:var(--solar-text)]">No hay proyectos que coincidan con "{{ $search }}".</h3>
            <p class="solar-subtitle mx-auto mt-3 max-w-2xl">Prueba con otro nombre o limpia la busqueda para ver todo el portafolio.</p>
        </div>
    @elseif ($solarProjects->isEmpty())
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
