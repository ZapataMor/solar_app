{{--
    Consumption of a project that gives it from the electricity bill (ADR-0020): there is no diary to fill,
    only the kWh per month of the bill and what they mean. The appliances version is consumption.blade.php.
    Params: $solarProject, $calculationFreshness, $sizing (SizeProjectSystem).
--}}
@php
    use App\Domain\Property\PropertyType;
    use App\Domain\Solar\CalculationFreshness;

    $kwh = fn (float $value): string => number_format($value, $value >= 100 || fmod(round($value, 1), 1.0) === 0.0 ? 0 : 1, ',', '.');
    // Pesos per month at the project's tariff, rounded to hundreds: "$348.000".
    $money = fn (float $cop): string => '$'.number_format(round($cop, -2), 0, ',', '.');

    $monthlyKwh = $solarProject->monthlyConsumption();
    $rate = max(0.0, (float) $solarProject->energy_rate_cop_kwh);
    $status = $calculationFreshness->status;
    $propertyLabel = mb_strtolower(PropertyType::label($solarProject->property_type));
    // The consumption lives in the third stage of the edit form (steps: place is fixed, then location, roof, consumption).
    $changeUrl = route('solar-projects.edit', $solarProject).'#paso-3';
    $roof = $sizing['sizing'] ?? null;
    $panels = fn (int $count): string => $count.' '.($count === 1 ? 'panel' : 'paneles');
@endphp

<x-layouts::app :title="__('Consumo').' · '.$solarProject->name">
    <div class="solar-project-detail" style="view-transition-name: project-{{ $solarProject->id }}">
        @include('solar-projects.partials.project-nav', ['solarProject' => $solarProject, 'active' => 'consumption'])

        <div class="solar-page solar-diary" data-consumption-bill>
            @if (session('status'))
                <div class="solar-alert solar-alert-success" role="status">{{ session('status') }}</div>
            @endif

            <section class="solar-card-strong solar-bill-summary" aria-labelledby="bill-total">
                <div class="solar-bill-summary__body">
                    <p class="solar-kicker">Consumo de tu {{ $propertyLabel }} · de tu recibo</p>
                    <h1 id="bill-total" class="solar-diary-total">{{ $kwh($monthlyKwh) }} kWh al mes</h1>
                    <p class="solar-diary-summary__meta">
                        ≈ {{ $kwh($monthlyKwh / 30) }} kWh al día
                        @if ($rate > 0)
                            · unos {{ $money($monthlyKwh * $rate) }} al mes con tu tarifa de ${{ number_format($rate, 0, ',', '.') }} por kWh
                        @endif
                    </p>
                    <p class="solar-bill-summary__note">
                        Es el número que escribiste de tu recibo de luz. Con él calculamos cuántos paneles necesitas.
                    </p>
                    <div class="solar-bill-summary__links">
                        <a href="{{ $changeUrl }}" class="solar-button-ghost">Cambiar mi consumo</a>
                        <a href="{{ $changeUrl }}" class="solar-diary-link">¿Prefieres calcularlo con tus equipos?</a>
                    </div>
                </div>

                <div class="solar-diary-summary__cta">
                    @if ($calculationFreshness->needsRecalculation())
                        <form method="POST" action="{{ route('solar-projects.calculate', $solarProject) }}">
                            @csrf
                            <input type="hidden" name="then" value="system">
                            <button type="submit" class="solar-button solar-recalc-anchor" data-test="bill-calculate">
                                {{ $status === CalculationFreshness::PENDING ? 'Calcular mi sistema' : 'Recalcular mi sistema' }}
                                <span class="solar-recalc-badge solar-recalc-badge--floating" aria-hidden="true">!</span>
                            </button>
                        </form>
                        <p>{{ $status === CalculationFreshness::PENDING ? 'Tu consumo está listo para calcular.' : ($calculationFreshness->reasons[0] ?? 'Tus datos cambiaron desde el último cálculo.') }}</p>
                    @elseif ($status === CalculationFreshness::FRESH)
                        <a href="{{ route('solar-projects.system', $solarProject) }}" class="solar-button-ghost" wire:navigate>Ver mi sistema</a>
                        <p>El cálculo está al día con tu consumo.</p>
                    @endif
                </div>
            </section>

            {{-- How much of this consumption the roof covers (ADR-0014). --}}
            @if ($roof !== null && $monthlyKwh > 0)
                @php($coverage = min(100.0, $roof->coveragePercentage()))
                <section class="solar-card solar-coverage-strip" aria-labelledby="coverage-title" data-test="coverage-strip">
                    <div class="solar-coverage-strip__head">
                        <h2 id="coverage-title">
                            @if ($roof->panelsThatFit === 0)
                                Tu techo no alcanza para ningún panel
                            @else
                                Tu techo cubre el {{ number_format($coverage, 0) }} % de tu consumo
                            @endif
                        </h2>
                        <a href="{{ route('solar-projects.system', $solarProject) }}" class="solar-diary-link" wire:navigate>Ver mi sistema →</a>
                    </div>
                    <div class="solar-coverage-bar" role="img" aria-label="El sol cubre el {{ number_format($coverage, 0) }} %">
                        <span style="width: {{ round($coverage, 1) }}%"></span>
                    </div>
                    <p class="solar-coverage-strip__text">
                        @if ($roof->panelsThatFit === 0)
                            Con el área que indicaste no cabe un panel completo. Revisa los metros del techo en Editar datos.
                        @elseif ($roof->roofIsEnough())
                            Bastan {{ $panels($roof->panelsNeeded) }} de los {{ $roof->panelsThatFit }} que caben.
                        @else
                            Necesitarías {{ $panels($roof->panelsNeeded) }} y caben {{ $roof->panelsThatFit }}: faltan {{ $roof->missingPanels() }}.
                        @endif
                    </p>
                    @unless ($sizing['fromClimateData'])
                        <p class="solar-coverage-strip__note">Estimado con el sol promedio de La Guajira; al calcular se afina con los datos de tu zona.</p>
                    @endunless
                </section>
            @endif
        </div>
    </div>
</x-layouts::app>
