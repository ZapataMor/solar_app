{{--
    Content of the consumption diary (ADR-0013): summary, bill notice and spaces. Rendered with the page
    and again by the server after each save, so the page updates without reloading.
    Params: $solarProject, $diary (BuildConsumptionDiary), $calculationFreshness, $usesBillConsumption.
--}}
@php
    use App\Domain\Property\PropertyType;
    use App\Domain\Solar\CalculationFreshness;

    // "374", "14,5", "36" (never "36,0").
    $kwh = fn (float $value): string => number_format($value, $value >= 100 || fmod(round($value, 1), 1.0) === 0.0 ? 0 : 1, ',', '.');
    $percent = fn (float $value): string => number_format($value, 0, ',', '.').' %';
    // Pesos per month at the project's tariff, rounded to hundreds: "$348.000".
    $money = fn (float $cop): string => '$'.number_format(round($cop, -2), 0, ',', '.');
    // Short form for the ring center: "$1,1 M", "$348 mil".
    $moneyShort = fn (float $cop): string => match (true) {
        $cop >= 1_000_000 => '$'.number_format($cop / 1_000_000, 1, ',', '.').' M',
        $cop >= 10_000 => '$'.number_format(round($cop / 1000), 0, ',', '.').' mil',
        default => $money($cop),
    };
    // Each figure is drawn in both units; the diary root decides which one shows (kWh or pesos).
    $unit = fn (string $kwhText, string $moneyText): string => '<span class="solar-unit solar-unit--kwh">'.e($kwhText).'</span>'
        .'<span class="solar-unit solar-unit--money">'.e($moneyText).'</span>';

    // Ring: one color per space, in diary order ("Otros" keeps the neutral one).
    $spaceColors = [];
    $stops = [];
    $start = 0.0;
    foreach ($diary['spaces'] as $index => $space) {
        $spaceColors[$space['key']] = $space['key'] === PropertyType::OTHER_SPACE ? 'var(--diary-other)' : 'var(--diary-c'.($index + 1).')';
        if ($space['share'] > 0) {
            $end = $start + $space['share'];
            $stops[] = $spaceColors[$space['key']].' '.round($start, 2).'% '.round($end, 2).'%';
            $start = $end;
        }
    }
    $ringBackground = $stops === [] ? 'var(--solar-border)' : 'conic-gradient('.implode(', ', $stops).')';
    $ringDescription = collect($diary['spaces'])
        ->filter(fn ($space) => $space['kwh'] > 0)
        ->map(fn ($space) => $space['label'].' '.$percent($space['share']))
        ->implode(', ');

    $propertyLabel = mb_strtolower(PropertyType::label($solarProject->property_type));
    $status = $calculationFreshness->status;
@endphp

{{-- Summary: total, ring by space and the biggest consumer --}}
<section class="solar-card-strong solar-diary-summary" aria-labelledby="diary-total">
    <div class="solar-diary-ring" style="--ring: {{ $ringBackground }}" role="img"
         aria-label="{{ $ringDescription !== '' ? 'Consumo por espacio: '.$ringDescription : 'Todavía no hay equipos' }}">
        <div class="solar-diary-ring__center">
            <strong>{!! $unit($kwh($diary['totalKwh']), $moneyShort($diary['totalCost'])) !!}</strong>
            <span>{!! $unit('kWh/mes', 'al mes') !!}</span>
        </div>
    </div>

    <div class="solar-diary-summary__body">
        <p class="solar-kicker">Consumo de tu {{ $propertyLabel }}</p>
        <h1 id="diary-total" class="solar-diary-total">
            @if ($diary['applianceCount'] > 0)
                {!! $unit($kwh($diary['totalKwh']).' kWh al mes', $money($diary['totalCost']).' al mes') !!}
            @else
                Recorre tu {{ $propertyLabel }} y agrega tus equipos
            @endif
        </h1>
        <p class="solar-diary-summary__meta">
            @if ($diary['applianceCount'] > 0)
                ≈ {!! $unit($kwh($diary['dailyKwh']).' kWh al día', $money($diary['totalCost'] / 30).' al día') !!} · {{ $diary['applianceCount'] }} {{ $diary['applianceCount'] === 1 ? 'equipo' : 'equipos' }}
            @else
                Espacio por espacio, como un diario: con tus equipos calculamos cuántos paneles necesitas.
            @endif
        </p>

        @if ($diary['applianceCount'] > 0)
            <ul class="solar-diary-legend">
                @foreach ($diary['spaces'] as $space)
                    @continue($space['kwh'] <= 0)
                    <li>
                        <span class="solar-diary-dot" style="--dot: {{ $spaceColors[$space['key']] }}" aria-hidden="true"></span>
                        {{ $space['label'] }}
                        <strong>{!! $unit($kwh($space['kwh']).' kWh', $money($space['cost'])) !!}</strong>
                        <span>{{ $percent($space['share']) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($diary['biggest'])
            <p class="solar-diary-biggest">
                Lo que más consume: <strong>{{ $diary['biggest']['label'] }}</strong>,
                {!! $unit($kwh($diary['biggest']['kwh']).' kWh al mes', $money($diary['biggest']['cost']).' al mes') !!} ({{ $percent($diary['biggest']['share']) }} del total).
            </p>
        @endif
    </div>

    <div class="solar-diary-summary__cta">
        @if ($calculationFreshness->needsRecalculation())
            <form method="POST" action="{{ route('solar-projects.calculate', $solarProject) }}">
                @csrf
                <input type="hidden" name="then" value="panel">
                <button type="submit" class="solar-button solar-recalc-anchor" data-test="diary-calculate">
                    {{ $status === CalculationFreshness::PENDING ? 'Calcular mi sistema' : 'Recalcular mi sistema' }}
                    <span class="solar-recalc-badge solar-recalc-badge--floating" aria-hidden="true">!</span>
                </button>
            </form>
            <p>{{ $status === CalculationFreshness::PENDING ? 'Tus equipos están listos para calcular.' : ($calculationFreshness->reasons[0] ?? 'Tus datos cambiaron desde el último cálculo.') }}</p>
        @elseif ($status === CalculationFreshness::FRESH)
            <a href="{{ route('solar-projects.show', $solarProject) }}" class="solar-button-ghost" wire:navigate>Ver mis resultados</a>
            <p>El cálculo está al día con tus equipos.</p>
        @endif
    </div>
</section>

@if ($usesBillConsumption)
    <div class="solar-alert solar-alert-warning" role="note">
        Este proyecto usa un consumo de <strong>{{ $kwh($solarProject->monthlyConsumption()) }} kWh al mes</strong> tomado del recibo.
        Cuando agregues tu primer equipo, el cálculo pasará a basarse en tus equipos, que reflejan mejor tu uso real.
    </div>
@endif

{{-- Spaces --}}
@foreach ($diary['spaces'] as $space)
    <section class="solar-card solar-diary-space" id="espacio-{{ $space['key'] }}" aria-labelledby="espacio-{{ $space['key'] }}-titulo">
        <header class="solar-diary-space__header">
            <span class="solar-diary-dot" style="--dot: {{ $spaceColors[$space['key']] }}" aria-hidden="true"></span>
            <h2 id="espacio-{{ $space['key'] }}-titulo">{{ $space['label'] }}</h2>
            @if ($space['kwh'] > 0)
                <span class="solar-diary-space__kwh">{!! $unit($kwh($space['kwh']).' kWh/mes', $money($space['cost']).' al mes') !!}</span>
            @endif
            <button type="button" class="solar-diary-add" data-diary-add="{{ $space['key'] }}">
                <span aria-hidden="true">+</span> Agregar<span class="sr-only"> equipo a {{ $space['label'] }}</span>
            </button>
        </header>

        @if ($space['items'] === [])
            <p class="solar-diary-empty">Sin equipos todavía.</p>
        @else
            <ul class="solar-diary-items">
                @foreach ($space['items'] as $item)
                    <li class="solar-diary-item" data-diary-item="{{ $item['id'] }}">
                        <svg viewBox="0 0 24 24" class="solar-diary-item__icon" aria-hidden="true"><use href="#appliance-{{ $item['icon'] }}"></use></svg>
                        <div class="solar-diary-item__body">
                            <strong>{{ $item['label'] }}</strong>
                            <span>
                                @if ($item['variantLabel'] !== ''){{ $item['variantLabel'] }} · @endif{{ $item['quantity'] }} × {{ $item['usageText'] }}
                            </span>
                        </div>
                        <span class="solar-diary-item__kwh">
                            <span class="solar-unit solar-unit--kwh"><strong>{{ $kwh($item['kwh']) }}</strong> kWh/mes</span>
                            <span class="solar-unit solar-unit--money"><strong>{{ $money($item['cost']) }}</strong> al mes</span>
                        </span>
                        <div class="solar-diary-item__actions">
                            <button type="button" class="solar-diary-link" data-diary-edit='@json($item)'>Editar<span class="sr-only"> {{ $item['label'] }}</span></button>
                            <form method="POST" action="{{ route('solar-projects.appliances.destroy', [$solarProject, $item['id']]) }}" data-diary-remove="{{ $item['label'] }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="solar-diary-link solar-diary-link--danger">Quitar<span class="sr-only"> {{ $item['label'] }}</span></button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endforeach

<p class="solar-diary-footnote">Usamos consumos de referencia por tipo y tamaño de equipo. El consumo real cambia con la marca, la antigüedad y el uso.</p>
