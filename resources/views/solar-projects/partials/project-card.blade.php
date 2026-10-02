@php
    // ADR-0013: the description became internal notes; the card says what the project is and how far it got.
    $monthlyConsumption = $solarProject->monthlyConsumption();
    $description = \App\Domain\Property\PropertyType::label($solarProject->property_type).' · '.($monthlyConsumption > 0
        ? number_format($monthlyConsumption, 0, ',', '.').' kWh al mes'
        : 'falta agregar sus equipos');
@endphp

@php
    $freshness = $projectFreshness[$solarProject->id] ?? null;
@endphp

{{-- A full navigation on purpose: the card morphs into the project page (resources/css/project-transitions.css). --}}
<a
    href="{{ route('solar-projects.show', ['solarProject' => $solarProject, ...($portfolioQuery ?? [])]) }}"
    class="solar-project-card"
    style="view-transition-name: project-{{ $solarProject->id }}"
>
    @if ($freshness?->needsRecalculation())
        <span class="solar-recalc-chip" title="{{ implode(' ', $freshness->reasons) }}">
            <span class="solar-recalc-chip__dot" aria-hidden="true">!</span>
            {{ $freshness->status === \App\Domain\Solar\CalculationFreshness::PENDING ? 'Sin calcular' : 'Por recalcular' }}
        </span>
    @endif
    <h3 class="solar-project-card__title" style="view-transition-name: project-title-{{ $solarProject->id }}">{{ $solarProject->name }}</h3>
    <p class="solar-project-card__summary">{{ $description }}</p>

    <dl class="solar-project-card__details">
        <div>
            <dt>Ubicacion</dt>
            <dd>{{ $solarProject->location_name ?: 'Sin ubicacion' }}</dd>
        </div>
        <div>
            <dt>Creado</dt>
            <dd>{{ $solarProject->created_at?->format('d/m/Y') }}</dd>
        </div>
    </dl>
</a>
