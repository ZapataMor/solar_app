{{--
    Alternative panel "Mi sistema" (ADR-0014): the project in money and plain words, to compare with
    the "Técnico" panel. The 3D illustration (ADR-0012, resources/js/solar-scene) reads the data-*
    attributes of [data-solar-scene]; without WebGL the flat sketch stays.
--}}
@php
    use App\Domain\Solar\CalculationFreshness;

    $sizing = $system['sizing'];
    // The consumption comes from the bill, not from appliances (ADR-0020): the texts talk about the consumption.
    $bill = $solarProject->usesBillConsumption();
    $rate = $system['rate'];
    $status = $calculationFreshness->status;
    $parameters = $solarProject->technicalParameter;

    $kwh = fn (float $value): string => number_format($value, $value >= 100 || fmod(round($value, 1), 1.0) === 0.0 ? 0 : 1, ',', '.');
    $money = fn (float $cop): string => '$'.number_format(round($cop, -2), 0, ',', '.');
    $moneyShort = fn (float $cop): string => match (true) {
        $cop >= 1_000_000 => '$'.number_format($cop / 1_000_000, 1, ',', '.').' M',
        $cop >= 10_000 => '$'.number_format(round($cop / 1000), 0, ',', '.').' mil',
        default => $money($cop),
    };
    $unit = fn (string $kwhText, string $moneyText): string => '<span class="solar-unit solar-unit--kwh">'.e($kwhText).'</span>'
        .'<span class="solar-unit solar-unit--money">'.e($moneyText).'</span>';
    $panels = fn (int $count): string => $count.' '.($count === 1 ? 'panel' : 'paneles');
    $years = function (?float $value): string {
        if ($value === null) {
            return '—';
        }
        $whole = (int) floor($value);
        $months = (int) round(($value - $whole) * 12);
        if ($months === 12) {
            [$whole, $months] = [$whole + 1, 0];
        }
        $parts = array_filter([
            $whole > 0 ? $whole.' '.($whole === 1 ? 'año' : 'años') : null,
            $months > 0 ? $months.' '.($months === 1 ? 'mes' : 'meses') : null,
        ]);

        return $parts === [] ? 'menos de un mes' : implode(' y ', $parts);
    };

    $coverage = min(100.0, $system['coveragePercentage']);
    $sunShareOfTen = (int) round($coverage / 10);

    // The month chart: both bars on the same scale.
    $chartMax = max(1.0, ...array_map(fn ($month) => max($month['sunKwh'], $month['useKwh']), $system['months'] ?: [['sunKwh' => 1, 'useKwh' => 1]]));
@endphp

<x-layouts::app :title="'Mi sistema · '.$solarProject->name">
    {{-- The 3D scene downloads with the page, so the loader gives way to it sooner (ADR-0012). --}}
    @push('head')
        @vite('resources/js/solar-scene/scene.js')
    @endpush

    <div class="solar-project-detail" style="view-transition-name: project-{{ $solarProject->id }}">
        @include('solar-projects.partials.project-nav', ['solarProject' => $solarProject, 'active' => 'system', 'backUrl' => $portfolioUrl])

        @include('solar-projects.partials.project-explainer', ['questions' => $projectQuestions, 'solarProject' => $solarProject])

        <div class="solar-page solar-system" data-unit-root data-unit="kwh" data-rate="{{ $rate }}">
            @include('solar-projects.partials.unit-toolbar', ['rate' => $rate, 'label' => 'Ver en'])

            @if (! $system['hasConsumption'])
                <div class="solar-recalc-banner solar-recalc-banner--start" role="status">
                    <span class="solar-recalc-banner__icon" aria-hidden="true">+</span>
                    <div class="solar-recalc-banner__body">
                        <strong>Agrega tus equipos para saber cuántos paneles necesitas.</strong>
                        <ul><li>Con lo que usas, calculamos cuántos paneles te hacen falta y si caben en tu techo.</li></ul>
                    </div>
                    <a href="{{ route('solar-projects.consumption', $solarProject) }}" class="solar-button" wire:navigate>Agregar mis equipos</a>
                </div>
            @elseif ($calculationFreshness->needsRecalculation())
                <div class="solar-recalc-banner" role="status">
                    <span class="solar-recalc-banner__icon" aria-hidden="true">!</span>
                    <div class="solar-recalc-banner__body">
                        <strong>{{ $status === CalculationFreshness::PENDING ? 'Calcula tu sistema con el sol de tu zona.' : 'Tus datos cambiaron desde el último cálculo.' }}</strong>
                        <ul>
                            @foreach ($calculationFreshness->reasons as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <form method="POST" action="{{ route('solar-projects.calculate', $solarProject) }}">
                        @csrf
                        <input type="hidden" name="then" value="system">
                        <button type="submit" class="solar-button">{{ $status === CalculationFreshness::PENDING ? 'Calcular mi sistema' : 'Recalcular' }}</button>
                    </form>
                </div>
            @endif

            {{-- Hero: illustration + recommendation in one sentence + money --}}
            <section class="solar-card-strong solar-system-hero" aria-labelledby="system-headline">
                <figure
                    class="solar-scene"
                    data-solar-scene
                    data-property-type="{{ $system['scene']['propertyType'] }}"
                    data-panels-installed="{{ $system['scene']['panelsInstalled'] }}"
                    data-panels-fit="{{ $system['scene']['panelsThatFit'] }}"
                    data-panels-missing="{{ $system['scene']['panelsMissing'] }}"
                    data-roof-area-m2="{{ $system['scene']['roofAreaM2'] }}"
                    data-panel-area-m2="{{ $system['scene']['panelAreaM2'] }}"
                    data-daily-kwh="{{ round($system['scene']['dailyKwh'], 2) }}"
                    aria-label="{{ $sizing ? 'Ilustración: '.$panels($sizing->panelsInstalled).' en el techo'.($sizing->missingPanels() > 0 ? ' y '.$sizing->missingPanels().' que no caben' : '') : 'Ilustración del sistema' }}"
                >
                    <div class="solar-scene__placeholder" aria-hidden="true">
                        <svg viewBox="0 0 200 120" class="solar-scene__house">
                            @if ($system['scene']['propertyType'] === 'business')
                                <path d="M20 50h160v62H20z" /><path d="M14 50l12-24h148l12 24" /><path d="M86 112V82h28v30" />
                            @elseif ($system['scene']['propertyType'] === 'institution')
                                <path d="M24 56h152v56H24z" /><path d="M14 56L100 18l86 38" /><path d="M90 112V84h20v28" />
                            @else
                                <path d="M36 58h128v54H36z" /><path d="M22 62L100 16l78 46" /><path d="M88 112V84h24v28" />
                            @endif
                        </svg>
                        @if ($sizing && $sizing->panelsThatFit + $sizing->missingPanels() > 0)
                            <div class="solar-scene__panels">
                                @for ($index = 0; $index < min(40, $sizing->panelsThatFit + $sizing->missingPanels()); $index++)
                                    <span @class([
                                        'solar-scene__panel',
                                        'is-installed' => $index < $sizing->panelsInstalled,
                                        'is-free' => $index >= $sizing->panelsInstalled && $index < $sizing->panelsThatFit,
                                        'is-missing' => $index >= $sizing->panelsThatFit,
                                    ])></span>
                                @endfor
                            </div>
                        @endif
                    </div>

                    {{-- 3D scene: with WebGL it takes the sketch's place from the first paint, with a loader until it arrives. --}}
                    <div class="solar-scene__stage" data-solar-scene-stage>
                        <p class="solar-3d-loading" aria-hidden="true"><span class="solar-sync-spinner"></span>Preparando la ilustración 3D…</p>
                        <button type="button" class="solar-scene__replay" data-solar-scene-replay title="Repetir la animación">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg>
                            <span class="sr-only">Repetir la animación</span>
                        </button>
                    </div>
                    {{-- Below the scene, so it never covers the building. --}}
                    <div class="solar-scene__hud" aria-hidden="true">
                        <p class="solar-scene__status" data-solar-scene-status hidden></p>
                        <div class="solar-scene__day" data-solar-scene-day hidden>
                            <svg viewBox="0 0 64 36" class="solar-scene__arc"><path d="M4 32a28 28 0 0 1 56 0"/><circle r="4.5" cx="4" cy="32" data-solar-scene-sun/></svg>
                            <span><strong data-solar-scene-energy></strong><small data-solar-scene-clock></small></span>
                        </div>
                    </div>

                    <figcaption>
                        @if ($sizing)
                            <span><i class="solar-scene__key is-installed"></i>Instalados</span>
                            @if ($sizing->sparePanels() > 0)<span><i class="solar-scene__key is-free"></i>Espacio libre</span>@endif
                            @if ($sizing->missingPanels() > 0)<span><i class="solar-scene__key is-missing"></i>No caben</span>@endif
                        @endif
                        <span class="solar-scene__note">Ilustración: no es el plano de instalación<span class="solar-scene__hint"> · arrastra para girarla</span></span>
                    </figcaption>
                </figure>

                <div class="solar-system-reco">
                    <p class="solar-kicker">Mi sistema</p>
                    <h1 id="system-headline" class="solar-system-reco__headline">
                        @if (! $system['hasConsumption'])
                            Primero, tus equipos
                        @elseif ($sizing === null)
                            Faltan los datos de tu techo
                        @elseif ($sizing->panelsThatFit === 0)
                            Tu techo no alcanza para ningún panel
                        @elseif ($sizing->roofIsEnough())
                            Necesitas {{ $panels($sizing->panelsNeeded) }} y te caben
                        @else
                            Necesitas {{ $panels($sizing->panelsNeeded) }}; en tu techo caben {{ $sizing->panelsThatFit }}
                        @endif
                    </h1>

                    @if ($system['hasConsumption'] && $sizing && $sizing->panelsThatFit > 0)
                        <p class="solar-system-reco__sub">
                            @if ($sizing->roofIsEnough())
                                El sol cubriría el {{ number_format($coverage, 0) }} % de tu consumo.
                                @if ($sizing->sparePanels() > 0) Te queda espacio para {{ $panels($sizing->sparePanels()) }} más si {{ $bill ? 'tu consumo crece' : 'algún día sumas equipos' }}. @endif
                            @else
                                Con esos {{ $sizing->panelsThatFit }}, el sol pagaría {{ $sunShareOfTen }} de cada 10 pesos de tu luz.
                            @endif
                            @if ($system['sizingIsEstimate'])
                                <span class="solar-system-reco__estimate">Estimado con el sol promedio de La Guajira.</span>
                            @endif
                        </p>

                        <div class="solar-coverage-bar" role="img" aria-label="El sol cubre el {{ number_format($coverage, 0) }} % de tu consumo">
                            <span style="width: {{ round($coverage, 1) }}%"></span>
                        </div>
                        <div class="solar-system-reco__bar-labels">
                            <span>Cubre {{ number_format($coverage, 0) }} %</span>
                            @if ($system['uncoveredKwh'] > 0.5)
                                <span>{!! $unit('Seguirías tomando '.$kwh($system['uncoveredKwh']).' kWh/mes de la red', 'Seguirías pagando unos '.$money($system['uncoveredCop']).' al mes') !!}</span>
                            @else
                                <span>Tu consumo quedaría cubierto</span>
                            @endif
                        </div>

                        @if ($system['calculated'])
                            <dl class="solar-system-metrics">
                                <div>
                                    <dt>Cuesta</dt>
                                    <dd>{{ $system['installationCostCop'] !== null ? $moneyShort($system['installationCostCop']) : '—' }}</dd>
                                </div>
                                <div>
                                    <dt>Ahorras</dt>
                                    <dd>{!! $unit($kwh(min($system['generationKwh'], $system['consumptionKwh'])).' kWh/mes', $money($system['savingsCop']).'/mes') !!}</dd>
                                </div>
                                <div>
                                    <dt>Se paga en</dt>
                                    <dd>{{ $years($system['paybackYears']) }}</dd>
                                </div>
                            </dl>
                        @endif
                    @elseif (! $system['hasConsumption'])
                        <p class="solar-system-reco__sub">Recorre tu casa o negocio en la pestaña Consumo y agrega lo que usas; aquí verás cuántos paneles te hacen falta, si caben y cuánto ahorras.</p>
                    @elseif ($sizing === null)
                        <p class="solar-system-reco__sub">Indica el área del techo en Editar datos para saber cuántos paneles caben.</p>
                    @else
                        <p class="solar-system-reco__sub">Con el área que indicaste no cabe un panel completo. Revisa los metros del techo en Editar datos.</p>
                    @endif
                </div>
            </section>

            {{-- Sun vs. appliances, month by month --}}
            @if ($system['calculated'] && $system['months'] !== [])
                <section class="solar-card solar-system-chart" aria-labelledby="system-chart-title">
                    <div class="solar-system-chart__head">
                        <h2 id="system-chart-title">Lo que da el sol frente a {{ $bill ? 'tu consumo' : 'lo que gastan tus equipos' }}</h2>
                        <p class="solar-system-chart__legend">
                            <span><i class="is-sun"></i>Sol</span>
                            <span><i class="is-use"></i>{{ $bill ? 'Tu consumo' : 'Tus equipos' }}</span>
                        </p>
                    </div>
                    <div class="solar-system-bars" aria-hidden="true">
                        @foreach ($system['months'] as $month)
                            <div class="solar-system-bars__month" title="{{ $month['name'] }}: sol {{ $kwh($month['sunKwh']) }} kWh ({{ $money($month['sunCop']) }}), {{ $bill ? 'consumo' : 'equipos' }} {{ $kwh($month['useKwh']) }} kWh ({{ $money($month['useCop']) }})">
                                <div class="solar-system-bars__pair">
                                    <span class="is-sun" style="height: {{ round($month['sunKwh'] / $chartMax * 100, 1) }}%"></span>
                                    <span class="is-use" style="height: {{ round($month['useKwh'] / $chartMax * 100, 1) }}%"></span>
                                </div>
                                <span class="solar-system-bars__label">{{ $month['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    <table class="sr-only">
                        <caption>Sol frente a consumo por mes</caption>
                        <thead><tr><th>Mes</th><th>Sol</th><th>{{ $bill ? 'Tu consumo' : 'Tus equipos' }}</th></tr></thead>
                        <tbody>
                            @foreach ($system['months'] as $month)
                                <tr>
                                    <td>{{ $month['name'] }}</td>
                                    <td>{!! $unit($kwh($month['sunKwh']).' kWh', $money($month['sunCop'])) !!}</td>
                                    <td>{!! $unit($kwh($month['useKwh']).' kWh', $money($month['useCop'])) !!}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="solar-system-chart__note">Cada mes llevado a 30 días. Cuando la barra del sol pasa la de {{ $bill ? 'tu consumo' : 'tus equipos' }}, ese mes sobra energía.</p>
                </section>
            @endif

            <div class="solar-system-grid">
                {{-- Live: what the panels would be doing now --}}
                <section class="solar-card solar-system-live" aria-labelledby="system-live-title">
                    <h2 id="system-live-title">Ahora mismo</h2>
                    @if ($system['live'])
                        @php($live = $system['live']['output'])
                        <p class="solar-system-live__sun">
                            <span class="solar-system-live__dot @if ($live->isNight()) is-night @endif" aria-hidden="true"></span>
                            {{ $live->sunLabel() }} · {{ number_format($live->irradianceWm2, 0, ',', '.') }} W/m²
                        </p>
                        @if ($live->isNight())
                            <p>Es de noche o está muy nublado: tus paneles no estarían produciendo y la energía vendría de la red.</p>
                        @else
                            <p>Tus paneles estarían dando unos <strong>{{ number_format($live->outputKw, 1, ',', '.') }} kW</strong>.</p>
                            @if ($live->poweredAppliances !== [])
                                <p>Alcanza para: {{ collect($live->poweredAppliances)->join(', ', ' y ') }}.</p>
                            @else
                                <p>Todavía no alcanza para tu equipo más grande: lo completaría la red.</p>
                            @endif
                        @endif
                        <p class="solar-system-live__meta">Estación Ambient Weather · lectura {{ $system['live']['recordedAt']->locale('es')->diffForHumans() }}</p>
                    @elseif (! $system['calculated'])
                        <p>Cuando calcules tu sistema, aquí verás qué estarían haciendo tus paneles con el sol de este momento.</p>
                    @else
                        <p>No hay una lectura reciente de la estación. Vuelve en un rato para ver qué estarían haciendo tus paneles.</p>
                    @endif
                </section>

                {{-- How the recommendation is made, in plain words --}}
                @if ($sizing && $parameters && $system['hasConsumption'])
                    <section class="solar-card solar-system-how" aria-labelledby="system-how-title">
                        <h2 id="system-how-title">¿Cómo lo calculamos?</h2>
                        <ol>
                            <li>Un panel de {{ number_format((float) $parameters->panel_power_w, 0, ',', '.') }} W produce aquí unos <strong>{{ $kwh($sizing->panelMonthlyKwh) }} kWh al mes</strong>.</li>
                            <li>{{ $bill ? 'Tu consumo es de' : 'Tus equipos usan' }} {{ $kwh($sizing->monthlyConsumptionKwh) }} kWh al mes: hacen falta <strong>{{ $panels($sizing->panelsNeeded) }}</strong>.</li>
                            <li>En {{ $kwh((float) $parameters->available_area_m2) }} m² de techo ({{ number_format((float) $parameters->usable_area_percentage, 0) }} % aprovechable) caben <strong>{{ $panels($sizing->panelsThatFit) }}</strong>.</li>
                            <li>Instalamos {{ $panels($sizing->panelsInstalled) }}: {{ $sizing->roofIsEnough() ? 'los que necesitas, ni uno más' : 'todos los que caben' }}.</li>
                        </ol>
                    </section>
                @endif
            </div>
        </div>
    </div>
</x-layouts::app>
