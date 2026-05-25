@php
    $projectsOnPage = collect($solarProjects->items());
    $projectGroups = $isAdmin
        ? $projectsOnPage
            ->groupBy('user_id')
            ->map(fn ($projects) => [
                'owner' => $projects->first()->user,
                'projects' => $projects,
            ])
            ->values()
        : collect();

    $ownersOnPage = $isAdmin
        ? $projectsOnPage->pluck('user_id')->filter()->unique()->count()
        : 1;

    $projectsWithResults = $projectsOnPage->filter(fn ($project) => $project->calculationResult !== null)->count();
    $aiReadyProjects = $projectsOnPage->filter(fn ($project) => filled(config('openai.api_key')) && $project->calculationResult !== null)->count();

    $formatMoney = fn ($value) => '$ ' . number_format((float) $value, 0, ',', '.') . ' COP';
    $formatDate = fn ($value) => optional($value)->format('d M Y');
    $badgeClass = fn ($tone) => match ($tone) {
        'success' => 'solar-pill-success',
        'warn' => 'solar-pill-warn',
        'danger' => 'solar-pill-danger',
        default => '',
    };
@endphp

<x-layouts::app :title="__('Proyectos solares')">
    <div class="solar-page">
        <section class="solar-hero">
            <div class="solar-page-header">
                <div>
                    <p class="solar-kicker">Solar command center</p>
                    <h1 class="solar-title">Portafolio solar con lectura visual inteligente</h1>
                    <p class="solar-subtitle">
                        {{ $isAdmin
                            ? 'Agrupa proyectos por propietario para revisar el portafolio completo con una narrativa mas clara, visual y lista para demo.'
                            : 'Consulta tus proyectos en una vista tipo SaaS con foco en cobertura, ahorro y decisiones rapidas.' }}
                    </p>
                </div>

                <a href="{{ route('solar-projects.create') }}" class="solar-button">
                    Nuevo proyecto
                </a>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-3">
                <div class="solar-metric-card">
                    <p class="solar-metric-label">{{ $isAdmin ? 'Portafolio total' : 'Tus proyectos' }}</p>
                    <p class="solar-metric-value">{{ number_format($solarProjects->total(), 0, ',', '.') }}</p>
                    <p class="solar-metric-copy">{{ $isAdmin ? 'Todos los proyectos visibles desde la administracion.' : 'Escenarios activos disponibles en tu cuenta.' }}</p>
                </div>
                <div class="solar-metric-card">
                    <p class="solar-metric-label">{{ $isAdmin ? 'Propietarios visibles' : 'Analisis listos' }}</p>
                    <p class="solar-metric-value">{{ number_format($isAdmin ? $ownersOnPage : $projectsWithResults, 0, ',', '.') }}</p>
                    <p class="solar-metric-copy">{{ $isAdmin ? 'Usuarios representados en esta pagina del dashboard.' : 'Proyectos con calculos disponibles para lectura ejecutiva.' }}</p>
                </div>
                <div class="solar-metric-card">
                    <p class="solar-metric-label">IA disponible</p>
                    <p class="solar-metric-value">{{ number_format($aiReadyProjects, 0, ',', '.') }}</p>
                    <p class="solar-metric-copy">Cards listas para resumen asistido cuando existe calculo y configuracion IA.</p>
                </div>
            </div>
        </section>

        @if (session('status'))
            <div class="solar-alert solar-alert-success">
                {{ session('status') }}
            </div>
        @endif

        <section class="solar-card">
            <div class="solar-page-header">
                <div>
                    <p class="solar-kicker">{{ $isAdmin ? 'Vista administrativa' : 'Vista personal' }}</p>
                    <h2 class="text-2xl text-[color:var(--solar-text)]">{{ $isAdmin ? 'Proyectos agrupados por usuario' : 'Tus proyectos solares' }}</h2>
                    <p class="solar-subtitle mt-2">
                        {{ $isAdmin
                            ? 'Cada bloque resume al propietario y despliega sus proyectos sin tablas internas.'
                            : 'Accede rapido al dashboard, edicion o eliminacion desde una estructura mas limpia y responsive.' }}
                    </p>
                </div>
                <span class="solar-pill">{{ number_format($solarProjects->count(), 0, ',', '.') }} en esta pagina</span>
            </div>

            @if ($solarProjects->isEmpty())
                <div class="solar-empty-state mt-6">
                    <div class="solar-empty-state__glow" aria-hidden="true"></div>
                    <p class="solar-kicker">Portafolio vacio</p>
                    <h3 class="mt-3 text-3xl text-[color:var(--solar-text)]">
                        {{ $isAdmin ? 'Aun no hay proyectos registrados en la plataforma.' : 'Todavia no has creado tu primer proyecto solar.' }}
                    </h3>
                    <p class="solar-subtitle mx-auto mt-3 max-w-2xl">
                        {{ $isAdmin
                            ? 'Cuando los usuarios comiencen a registrar escenarios, apareceran aqui organizados por propietario con sus indicadores principales.'
                            : 'Crea un proyecto para empezar a modelar consumo, cobertura energetica y ahorro anual en una experiencia lista para demo.' }}
                    </p>
                    <div class="mt-6">
                        <a href="{{ route('solar-projects.create') }}" class="solar-button">
                            Crear proyecto
                        </a>
                    </div>
                </div>
            @elseif ($isAdmin)
                <div class="mt-6 space-y-6">
                    @foreach ($projectGroups as $group)
                        @php
                            $owner = $group['owner'];
                            $projects = $group['projects'];
                        @endphp

                        <section class="solar-owner-section">
                            <div class="solar-owner-section__header">
                                <div>
                                    <p class="solar-kicker">Propietario</p>
                                    <h3 class="solar-owner-section__title">{{ $owner?->name ?? 'Usuario sin nombre' }}</h3>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <span class="solar-pill">{{ number_format($projects->count(), 0, ',', '.') }} proyectos</span>
                                    <span class="solar-pill {{ $badgeClass($projects->contains(fn ($project) => $project->calculationResult !== null) ? 'success' : 'warn') }}">
                                        {{ $projects->contains(fn ($project) => $project->calculationResult !== null) ? 'Con analisis' : 'Pendientes' }}
                                    </span>
                                </div>
                            </div>

                            <div class="solar-project-grid">
                                @foreach ($projects as $solarProject)
                                    @include('solar-projects.partials.project-card', ['solarProject' => $solarProject])
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            @else
                <div class="solar-project-grid mt-6">
                    @foreach ($solarProjects as $solarProject)
                        @include('solar-projects.partials.project-card', ['solarProject' => $solarProject])
                    @endforeach
                </div>
            @endif
        </section>

        <div class="solar-pagination">
            {{ $solarProjects->links() }}
        </div>
    </div>
</x-layouts::app>
