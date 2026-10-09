{{--
    Navigation shared by a project's own pages (panel, consumption diary, notes and edit).
    Params: $solarProject, $active ('panel'|'system'|'consumption'|'notes'|'edit'),
    $backUrl (optional: portfolio URL that keeps search/page).
--}}
@php
    use App\Domain\Installers\QuoteComparison;

    $backLabel = auth()->user()?->isAdmin() ? 'Todos los proyectos' : 'Mis proyectos';
    $backUrl = $backUrl ?? route('solar-projects.index');
    // Con dos precios o más hay algo que comparar, y se llega desde cualquier pestaña (ADR-0028).
    $quotes = $solarProject->receivedQuotes();
    $tabs = [
        'panel' => ['label' => 'Técnico', 'url' => route('solar-projects.show', $solarProject)],
        // ADR-0014: "Mi sistema" (client view) is compared with "Técnico" (the original panel) before choosing.
        'system' => ['label' => 'Mi sistema', 'url' => route('solar-projects.system', $solarProject)],
        'consumption' => ['label' => 'Consumo', 'url' => route('solar-projects.consumption', $solarProject)],
        'notes' => ['label' => 'Notas', 'url' => route('solar-projects.notes', $solarProject)],
        'edit' => ['label' => 'Editar datos', 'url' => route('solar-projects.edit', $solarProject)],
    ];
@endphp

<nav class="solar-project-nav" aria-label="Proyecto {{ $solarProject->name }}">
    <div class="solar-project-nav__crumbs">
        {{-- Without wire:navigate, so going back also animates: the page shrinks into its card. --}}
        <a href="{{ $backUrl }}" class="solar-project-nav__back">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            {{ $backLabel }}
        </a>
        <span class="solar-project-nav__sep" aria-hidden="true">/</span>
        <span class="solar-project-nav__current" title="{{ $solarProject->name }}" style="view-transition-name: project-title-{{ $solarProject->id }}">{{ $solarProject->name }}</span>
    </div>

    <div class="solar-project-nav__tabs" role="tablist">
        @foreach ($tabs as $key => $tab)
            <a
                href="{{ $tab['url'] }}"
                class="solar-project-nav__tab"
                role="tab"
                aria-selected="{{ $active === $key ? 'true' : 'false' }}"
                @if ($active === $key) aria-current="page" @endif
                wire:navigate
            >{{ $tab['label'] }}</a>
        @endforeach
    </div>

    @if ($quotes >= QuoteComparison::MINIMUM)
        {{-- Fuera de la cápsula de pestañas: no es una sección del proyecto, es otra pantalla. --}}
        <a href="{{ route('installers.quotes.compare', $solarProject) }}" class="solar-project-nav__quotes" wire:navigate>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>
            Comparar {{ $quotes }} cotizaciones
        </a>
    @endif
</nav>
