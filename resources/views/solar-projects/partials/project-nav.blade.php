{{--
    Navigation shared by a project's own pages (panel, consumption diary, notes and edit).
    Params: $solarProject, $active ('panel'|'system'|'consumption'|'notes'|'edit'),
    $backUrl (optional: portfolio URL that keeps search/page).
--}}
@php
    $backLabel = auth()->user()?->isAdmin() ? 'Todos los proyectos' : 'Mis proyectos';
    $backUrl = $backUrl ?? route('solar-projects.index');
    $tabs = [
        'panel' => ['label' => 'Panel', 'url' => route('solar-projects.show', $solarProject)],
        // ADR-0014: compared with "Panel" before choosing which one stays.
        'system' => ['label' => 'Panel alternativo', 'url' => route('solar-projects.system', $solarProject)],
        'consumption' => ['label' => 'Consumo', 'url' => route('solar-projects.consumption', $solarProject)],
        'notes' => ['label' => 'Notas', 'url' => route('solar-projects.notes', $solarProject)],
        'edit' => ['label' => 'Editar datos', 'url' => route('solar-projects.edit', $solarProject)],
    ];
@endphp

<nav class="solar-project-nav" aria-label="Proyecto {{ $solarProject->name }}">
    <div class="solar-project-nav__crumbs">
        <a href="{{ $backUrl }}" class="solar-project-nav__back" wire:navigate>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            {{ $backLabel }}
        </a>
        <span class="solar-project-nav__sep" aria-hidden="true">/</span>
        <span class="solar-project-nav__current" title="{{ $solarProject->name }}">{{ $solarProject->name }}</span>
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
</nav>
