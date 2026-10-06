{{--
    One installer's quote, as the client who asked for it reads it (ADR-0026). The card of the
    directory shows the price; here the app does what the price alone cannot: it recalculates the
    payback with *this* number and puts it next to the reference budget and next to what the other
    installers offered for the same roof.

    Params: App\Actions\Installers\DescribeQuoteForClient.
--}}
@php
    use App\Domain\Solar\Profitability;

    $money = fn (float $cop): string => '$'.number_format(round($cop, -3), 0, ',', '.');
    $kwh = fn (float $value): string => number_format($value, $value < 10 ? 1 : 0, ',', '.');
    $kw = fn (float $value): string => number_format($value, 2, ',', '.');
    $date = fn ($value): string => \Illuminate\Support\Carbon::parse($value)->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
    $expired = $quote->hasExpired();
    $daysLeft = $quote->daysLeft();
@endphp

<x-layouts::app :title="'Cotización de '.$installer->name">
    <div class="solar-page solar-client-quote">
        <div class="solar-page-header">
            <div>
                <a href="{{ route('installers.index', ['proyecto' => $project->id]) }}" class="solar-project-nav__back" wire:navigate>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                    Instaladores
                </a>
                <p class="solar-inbox-card__status mt-3" data-status="{{ $status }}">{{ $statusLabel }}</p>
                <h1 class="solar-title mt-2">Cotización de {{ $installer->name }}</h1>
                <p class="solar-subtitle mt-2">
                    Para {{ $project->name }}
                    @if ($municipalityName) · {{ $municipalityName }} @endif
                    · Se la pediste el {{ $date($requestedAt) }}
                </p>
            </div>
        </div>

        {{-- The price first: it is what the client came to read. --}}
        <section @class(['solar-card', 'solar-client-quote__price', 'is-expired' => $expired])>
            <div class="solar-client-quote__amount">
                <p class="solar-inbox-card__label">Lo que te cuesta la instalación</p>
                <p class="solar-client-quote__figure">{{ $money($amountCop) }}</p>
                <p class="solar-client-quote__meta">
                    {{ $quote->includes_battery ? 'Con baterías' : 'Sin baterías' }}
                    @if ($powerKw) · Sistema de {{ $kw($powerKw) }} kW @endif
                    @if ($pricePerKwCop) · {{ $money($pricePerKwCop) }} por kW @endif
                </p>
            </div>

            <p @class(['solar-client-quote__valid', 'is-expired' => $expired])>
                @if ($expired)
                    <strong>Este precio venció</strong> el {{ $date($quote->valid_until) }}.
                    Escríbele a {{ $installer->name }} para que te lo actualice: los equipos son importados y
                    el precio se mueve con el dólar.
                @else
                    Vale hasta el <strong>{{ $date($quote->valid_until) }}</strong>{{ $daysLeft > 0 ? ' · te quedan '.($daysLeft === 1 ? '1 día' : $daysLeft.' días') : ' · hoy es el último día' }}.
                @endif
            </p>
        </section>

        {{-- The arithmetic the client cannot do alone: this price against the savings of their project. --}}
        <section class="solar-card">
            <h2 class="solar-quote-heading">¿Te conviene este precio?</h2>

            @if ($paybackYears !== null)
                <p class="solar-subtitle mt-2">
                    Con lo que tu proyecto ahorra al año, esta cotización se paga sola en
                    <strong>{{ Profitability::paybackText($paybackYears) }}</strong>. De ahí en adelante, la
                    energía que produzcas es tuya.
                </p>

                <div class="solar-client-quote__verdict" data-level="{{ $profitability->level }}">
                    <p class="solar-client-quote__verdict-label">{{ $profitability->label() }}</p>
                    <p class="solar-client-quote__verdict-text">{{ $profitability->description() }}</p>
                </div>

                <dl class="solar-inbox-figures mt-3">
                    <div>
                        <dt>Ahorro al mes</dt>
                        <dd>{{ $money($monthlySavingsCop) }}</dd>
                    </div>
                    <div>
                        <dt>Ahorro al año</dt>
                        <dd>{{ $money($annualSavingsCop) }}</dd>
                    </div>
                    @if ($coveragePercentage !== null)
                        <div>
                            <dt>De tu consumo cubre</dt>
                            <dd>{{ number_format(min($coveragePercentage, 999), 0, ',', '.') }} %</dd>
                        </div>
                    @endif
                </dl>

                <p class="solar-inbox-note mt-3">
                    El ahorro sale del cálculo de tu proyecto con datos climáticos y de tu tarifa: solo cuenta la
                    energía que realmente consumes, porque los excedentes no bajan el recibo.
                </p>
            @else
                <p class="solar-subtitle mt-2">
                    Todavía no sabemos en cuánto tiempo se paga: tu proyecto no tiene un cálculo con datos
                    climáticos, y sin él no hay ahorro con qué comparar este precio.
                </p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ route('solar-projects.show', $project) }}" class="solar-button" wire:navigate>Calcular mi proyecto</a>
                </div>
            @endif
        </section>

        <section class="solar-card">
            <h2 class="solar-quote-heading">Cómo se compara</h2>

            @if ($referenceCop !== null)
                <p class="solar-subtitle mt-2">
                    @php($difference = $differenceCop ?? 0)
                    @if (abs($difference) < 1000)
                        Es prácticamente igual al presupuesto de referencia que calculamos para tu municipio.
                    @elseif ($difference < 0)
                        Está <strong>{{ $money(abs($difference)) }} por debajo</strong> del presupuesto de referencia
                        que calculamos para tu municipio el día que la pediste.
                    @else
                        Está <strong>{{ $money($difference) }} por encima</strong> del presupuesto de referencia
                        que calculamos para tu municipio el día que la pediste. No significa que esté mal: puede
                        incluir equipos o trabajos que la referencia no contempla.
                    @endif
                </p>
            @endif

            <dl class="solar-inbox-figures mt-3">
                <div>
                    <dt>Esta cotización</dt>
                    <dd>{{ $money($amountCop) }}</dd>
                </div>
                @if ($referenceCop !== null)
                    <div>
                        <dt>Presupuesto de referencia</dt>
                        <dd>{{ $money($referenceCop) }}</dd>
                    </div>
                @endif
                @if ($pricePerKwCop !== null)
                    <div>
                        <dt>Precio por kW</dt>
                        <dd>
                            {{ $money($pricePerKwCop) }}
                            @if ($referencePricePerKwCop)
                                <span class="solar-quote-sub">referencia: {{ $money($referencePricePerKwCop) }}</span>
                            @endif
                        </dd>
                    </div>
                @endif
                @if ($requiredPowerKw !== null)
                    <div>
                        <dt>Potencia que pide tu consumo</dt>
                        <dd>
                            {{ $kw($requiredPowerKw) }} kW
                            @if ($powerKw && abs($powerKw - $requiredPowerKw) >= 0.5)
                                <span class="solar-inbox-warning">te propone {{ $kw($powerKw) }} kW</span>
                            @endif
                        </dd>
                    </div>
                @endif
                <div>
                    <dt>Tu consumo</dt>
                    <dd>{{ $kwh($monthlyKwh) }} kWh/mes</dd>
                </div>
            </dl>

            @if ($others)
                <h3 class="solar-client-quote__others-title">Lo que te ofrecieron los demás</h3>
                <ul class="solar-client-quote__others">
                    @foreach ($others as $other)
                        <li>
                            <a href="{{ route('installers.quotes.show', $other['id']) }}" wire:navigate>
                                <span class="solar-client-quote__others-name">{{ $other['name'] }}</span>
                                <span class="solar-client-quote__others-meta">
                                    {{ $other['includesBattery'] ? 'con baterías' : 'sin baterías' }}
                                    @if ($other['powerKw']) · {{ $kw($other['powerKw']) }} kW @endif
                                    @if ($other['expired']) · precio vencido @endif
                                </span>
                                <span class="solar-client-quote__others-amount">{{ $money($other['amountCop']) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <p class="solar-inbox-note mt-3">
                    Dos cotizaciones solo se comparan si llevan lo mismo: fíjate en las baterías y en los kW antes
                    que en el total.
                </p>
            @endif
        </section>

        <section class="solar-card">
            <h2 class="solar-quote-heading">Qué incluye</h2>

            @if ($quote->scope)
                <p class="solar-client-quote__scope">{{ $quote->scope }}</p>
            @else
                <p class="solar-subtitle mt-2">
                    {{ $installer->name }} no detalló qué entra en el precio. Pregúntaselo antes de decidir.
                </p>
            @endif

            <h3 class="solar-client-quote__others-title">Qué preguntar antes de firmar</h3>
            <ul class="solar-client-quote__checklist">
                <li>¿El precio incluye la estructura del techo, el cableado y la mano de obra?</li>
                <li>¿Quién hace el trámite con la electrificadora y cuánto demora?</li>
                <li>¿Qué garantía tienen los paneles y el inversor, y quién responde por ella?</li>
                @unless ($quote->includes_battery)
                    <li>Sin baterías, de noche sigues comprando energía: ¿cuánto costaría agregarlas?</li>
                @endunless
            </ul>
        </section>

        <section class="solar-card solar-client-quote__contact">
            <div>
                <h2 class="solar-quote-heading">Habla con {{ $installer->name }}</h2>
                <p class="solar-subtitle mt-2">
                    @if ($installer->years_experience)
                        {{ $installer->years_experience }} años instalando en La Guajira ·
                    @endif
                    {{ $coverage }}
                </p>
                @if ($installer->contact_name)
                    <p class="solar-quote-client__name mt-2">{{ $installer->contact_name }}</p>
                @endif
            </div>

            <div class="solar-installer-card__links">
                @if ($installer->whatsappNumber())
                    <a href="https://wa.me/{{ $installer->whatsappNumber() }}" target="_blank" rel="noopener">WhatsApp</a>
                @endif
                @if ($installer->phone)
                    <a href="tel:{{ preg_replace('/\s+/', '', $installer->phone) }}">{{ $installer->phone }}</a>
                @endif
                @if ($installer->email)
                    <a href="mailto:{{ $installer->email }}">{{ $installer->email }}</a>
                @endif
            </div>
        </section>
    </div>
</x-layouts::app>
