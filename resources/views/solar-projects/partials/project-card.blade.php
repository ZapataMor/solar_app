@php
    $description = filled($solarProject->description)
        ? \Illuminate\Support\Str::limit(trim($solarProject->description), 160)
        : 'Escenario solar para consumo, cobertura y ahorro en Riohacha.';
@endphp

<a href="{{ route('solar-projects.show', ['solarProject' => $solarProject, ...($portfolioQuery ?? [])]) }}" class="solar-project-card">
    <h3 class="solar-project-card__title">{{ $solarProject->name }}</h3>
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
