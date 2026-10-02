@php
    // ADR-0013: the description became internal notes; the card says what the project is and how far it got.
    $monthlyConsumption = $solarProject->monthlyConsumption();
    $description = \App\Domain\Property\PropertyType::label($solarProject->property_type).' · '.($monthlyConsumption > 0
        ? number_format($monthlyConsumption, 0, ',', '.').' kWh al mes'
        : 'falta agregar sus equipos');

    $freshness = $projectFreshness[$solarProject->id] ?? null;
    // Cost, payback and the ribbon (SummarizePortfolioProject).
    $summary = $projectSummaries[$solarProject->id] ?? null;
    $profitability = $summary['profitability'] ?? null;
    $moneyShort = fn (float $cop): string => $cop >= 1_000_000
        ? '$'.number_format($cop / 1_000_000, 1, ',', '.').' M'
        : '$'.number_format(round($cop, -3), 0, ',', '.');
@endphp

{{-- Opens in "Mi sistema". A full navigation on purpose: the card morphs into the project page
     (resources/css/project-transitions.css). --}}
<a
    href="{{ route('solar-projects.system', ['solarProject' => $solarProject, ...($portfolioQuery ?? [])]) }}"
    @class(['solar-project-card', 'has-ribbon' => $profitability !== null])
    style="view-transition-name: project-{{ $solarProject->id }}"
>
    @if ($profitability)
        <span class="solar-project-ribbon solar-project-ribbon--{{ $profitability->level }}" title="{{ $profitability->description() }}" data-test="ribbon">
            {{ $profitability->label() }}
        </span>
    @endif
    @if ($freshness?->needsRecalculation())
        <span class="solar-recalc-chip" title="{{ implode(' ', $freshness->reasons) }}">
            <span class="solar-recalc-chip__dot" aria-hidden="true">!</span>
            {{ $freshness->status === \App\Domain\Solar\CalculationFreshness::PENDING ? 'Sin calcular' : 'Por recalcular' }}
        </span>
    @endif
    <h3 class="solar-project-card__title" style="view-transition-name: project-title-{{ $solarProject->id }}">{{ $solarProject->name }}</h3>
    <p class="solar-project-card__summary">{{ $description }}</p>

    <dl class="solar-project-card__figures">
        <div>
            <dt>Cuesta</dt>
            <dd>{{ ($summary['costCop'] ?? null) !== null ? $moneyShort($summary['costCop']) : '—' }}</dd>
        </div>
        <div>
            <dt>Se paga en</dt>
            <dd>
                @if (($summary['calculated'] ?? false))
                    {{ $summary['paybackText'] }}
                @else
                    <span class="solar-project-card__pending">{{ $monthlyConsumption > 0 ? 'Calcúlalo para saberlo' : 'Agrega tus equipos' }}</span>
                @endif
            </dd>
        </div>
    </dl>

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
