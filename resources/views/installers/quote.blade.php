{{--
    One installer's quote, as the client who asked for it reads it (ADR-0026, ADR-0027).

    It is read in steps, not scrolled: with the detail of a real quote the page became long enough
    that the price and the verdict fell off the screen. The price stays on top, always, and the rest
    is one step at a time. Without JavaScript every step is simply shown, one after the other.

    Params: App\Actions\Installers\DescribeQuoteForClient.
--}}
@php
    use App\Domain\Solar\Profitability;

    $money = fn (float $cop): string => '$'.number_format(round($cop, -3), 0, ',', '.');
    $kwh = fn (float $value): string => number_format($value, $value < 10 ? 1 : 0, ',', '.');
    $kw = fn (float $value): string => number_format($value, 2, ',', '.');
    $date = fn ($value): string => \Illuminate\Support\Carbon::parse($value)->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
    $years = fn (int $value): string => $value === 1 ? '1 año' : $value.' años';
    $expired = $quote->hasExpired();
    $daysLeft = $quote->daysLeft();
    $hasWarranties = $quote->panel_warranty_years || $quote->inverter_warranty_years || $quote->workmanship_warranty_years;
    $hasTerms = $quote->down_payment_percentage !== null || $quote->delivery_days || $quote->vat_included !== null;
    $inclusions = $quote->inclusions();

    // The steps, in the order the client asks the questions: ¿me conviene?, ¿contra qué lo comparo?,
    // ¿qué me están dando?, ¿qué me garantizan?, ¿qué hago ahora?
    $steps = [
        'conviene' => ['label' => '¿Te conviene?', 'short' => 'Conviene'],
        'compara' => ['label' => 'Cómo se compara', 'short' => 'Compara'],
        'cubre' => ['label' => 'Qué cubre', 'short' => 'Cubre'],
        'garantias' => ['label' => 'Garantías y condiciones', 'short' => 'Garantías'],
        'firmar' => ['label' => 'Antes de firmar', 'short' => 'Firmar'],
    ];
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

        {{-- The price stays out of the steps: it is the one number the client never wants to lose. --}}
        <section @class(['solar-card', 'solar-client-quote__price', 'is-expired' => $expired])>
            <div class="solar-client-quote__amount">
                <p class="solar-inbox-card__label">Lo que te cuesta la instalación</p>
                <p class="solar-client-quote__figure">{{ $money($amountCop) }}</p>
                <p class="solar-client-quote__meta">
                    @if ($powerKw) Sistema de {{ $kw($powerKw) }} kW · @endif
                    {{ $quote->includes_battery ? 'Con baterías' : 'Sin baterías' }}
                    @if ($quote->includes_battery && $quote->battery_kwh) de {{ $kw((float) $quote->battery_kwh) }} kWh @endif
                    @if ($pricePerKwCop) · {{ $money($pricePerKwCop) }} por kW @endif
                    @if ($quote->vat_included !== null) · IVA {{ $quote->vat_included ? 'incluido' : 'aparte' }} @endif
                </p>
                @if ($quote->panelText() || $quote->inverter_model)
                    <p class="solar-client-quote__equipment">
                        @if ($quote->panelText()) {{ $quote->panelText() }} @endif
                        @if ($quote->panelText() && $quote->inverter_model) · @endif
                        @if ($quote->inverter_model) Inversor {{ $quote->inverter_model }} @endif
                    </p>
                @endif
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

        {{-- Real links to each step, so the keyboard and the back button keep working. --}}
        <nav class="solar-steps" data-quote-steps aria-label="Pasos de la cotización">
            <ol role="tablist">
                @foreach ($steps as $key => $step)
                    <li>
                        <a
                            href="#paso-{{ $key }}"
                            id="tab-{{ $key }}"
                            role="tab"
                            data-quote-step="{{ $key }}"
                            aria-controls="paso-{{ $key }}"
                            aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                            tabindex="{{ $loop->first ? '0' : '-1' }}"
                        >
                            <span class="solar-steps__number" aria-hidden="true">{{ $loop->iteration }}</span>
                            <span class="solar-steps__label">{{ $step['label'] }}</span>
                            <span class="solar-steps__short" aria-hidden="true">{{ $step['short'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ol>
        </nav>

        {{-- Paso 1: the arithmetic the client cannot do alone, this price against their savings. --}}
        <section class="solar-card solar-steps__panel is-current" id="paso-conviene" role="tabpanel" aria-labelledby="tab-conviene" data-quote-panel="conviene" tabindex="-1">
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

            <x-installers.step-nav :steps="$steps" current="conviene" />
        </section>

        {{-- Paso 2 --}}
        <section class="solar-card solar-steps__panel" id="paso-compara" role="tabpanel" aria-labelledby="tab-compara" data-quote-panel="compara" tabindex="-1">
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
                @if ($quote->monthly_generation_kwh)
                    <div>
                        <dt>Producción que promete</dt>
                        <dd>
                            {{ $kwh((float) $quote->monthly_generation_kwh) }} kWh/mes
                            @if ($monthlyKwh > 0)
                                <span class="solar-quote-sub">{{ number_format(min((float) $quote->monthly_generation_kwh / $monthlyKwh * 100, 999), 0, ',', '.') }} % de lo que gastas</span>
                            @endif
                        </dd>
                    </div>
                @endif
            </dl>

            {{-- El cara a cara del ADR-0029: esta cotización contra otra, campo por campo. Va antes
                 de la lista, que ahora sirve para abrir otra, no para comparar de memoria. --}}
            @if ($faceOff)
                <x-installers.face-off :face-off="$faceOff" :project="$project" />
            @endif

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
            @endif

            @if ($comparable)
                {{-- Una lista de enlaces obliga a comparar de memoria: la tabla del ADR-0028 pone los
                     mismos campos de todas, uno al lado del otro. --}}
                <div class="mt-4">
                    <a href="{{ route('installers.quotes.compare', $project) }}" class="solar-button" wire:navigate>
                        Comparar las {{ count($others) + 1 }} cotizaciones lado a lado
                    </a>
                </div>
            @endif

            <x-installers.step-nav :steps="$steps" current="compara" />
        </section>

        {{-- Paso 3: lo que cubre el precio (ADR-0027). La cotización más barata suele ser la que
             deja afuera el trámite y el medidor. --}}
        <section class="solar-card solar-steps__panel" id="paso-cubre" role="tabpanel" aria-labelledby="tab-cubre" data-quote-panel="cubre" tabindex="-1">
            <h2 class="solar-quote-heading">Qué cubre el precio</h2>

            @if ($quote->missesLegalization())
                <p class="solar-client-quote__warning">
                    Esta cotización no cubre todo lo que legaliza la instalación. Antes de compararla con
                    otra, pregunta cuánto cuesta aparte: puede ser la diferencia entre las dos.
                </p>
            @endif

            @if ($inclusions['included'] || $inclusions['excluded'])
                <ul class="solar-client-quote__inclusions">
                    @foreach ($inclusions['included'] as $item)
                        <li class="is-in">
                            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5l5 5L20 6.5"/></svg>
                            <span>
                                <strong>{{ $item['label'] }}</strong>
                                <small>{{ $item['hint'] }}</small>
                            </span>
                        </li>
                    @endforeach
                    @foreach ($inclusions['excluded'] as $item)
                        <li class="is-out">
                            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                            <span>
                                <strong>{{ $item['label'] }}</strong>
                                <small>{{ $item['missing'] }}</small>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($quote->scope)
                <h3 class="solar-client-quote__others-title">En palabras del instalador</h3>
                <p class="solar-client-quote__scope">{{ $quote->scope }}</p>
            @endif

            @if ($quote->exclusions)
                <h3 class="solar-client-quote__others-title">Lo que no entra</h3>
                <p class="solar-client-quote__scope">{{ $quote->exclusions }}</p>
            @endif

            @unless ($quote->scope || $quote->exclusions)
                <p class="solar-inbox-note mt-3">
                    {{ $installer->name }} no escribió el detalle del alcance. Pídeselo antes de decidir.
                </p>
            @endunless

            <x-installers.step-nav :steps="$steps" current="cubre" />
        </section>

        {{-- Paso 4: donde se separan dos ofertas del mismo precio. --}}
        <section class="solar-card solar-steps__panel" id="paso-garantias" role="tabpanel" aria-labelledby="tab-garantias" data-quote-panel="garantias" tabindex="-1">
            <h2 class="solar-quote-heading">Garantías y condiciones</h2>

            @if ($hasWarranties || $hasTerms)
                <dl class="solar-inbox-figures mt-3">
                    @if ($quote->panel_warranty_years)
                        <div>
                            <dt>Garantía de los paneles</dt>
                            <dd>{{ $years($quote->panel_warranty_years) }}</dd>
                        </div>
                    @endif
                    @if ($quote->inverter_warranty_years)
                        <div>
                            <dt>Garantía del inversor</dt>
                            <dd>{{ $years($quote->inverter_warranty_years) }}</dd>
                        </div>
                    @endif
                    @if ($quote->workmanship_warranty_years)
                        <div>
                            <dt>Garantía de la obra</dt>
                            <dd>{{ $years($quote->workmanship_warranty_years) }}</dd>
                        </div>
                    @endif
                    @if ($quote->down_payment_percentage !== null)
                        <div>
                            <dt>Anticipo</dt>
                            <dd>
                                {{ $quote->down_payment_percentage }} %
                                <span class="solar-quote-sub">{{ $money($amountCop * $quote->down_payment_percentage / 100) }}</span>
                            </dd>
                        </div>
                    @endif
                    @if ($quote->delivery_days)
                        <div>
                            <dt>Plazo hasta energizar</dt>
                            <dd>{{ $quote->delivery_days }} días</dd>
                        </div>
                    @endif
                    @if ($quote->vat_included !== null)
                        <div>
                            <dt>IVA</dt>
                            <dd>{{ $quote->vat_included ? 'Incluido en el precio' : 'Se suma aparte' }}</dd>
                        </div>
                    @endif
                </dl>
            @endif

            @unless ($hasWarranties)
                <p class="solar-inbox-note mt-3">
                    No dice cuántos años garantiza nada. Es la pregunta que más vale la pena hacer:
                    lo usual son 25 años en paneles, 5 a 10 en el inversor y 1 a 2 en la obra.
                </p>
            @endunless

            <x-installers.step-nav :steps="$steps" current="garantias" />
        </section>

        {{-- Paso 5: las preguntas que faltan, y con quién hacerlas. --}}
        <section class="solar-card solar-steps__panel" id="paso-firmar" role="tabpanel" aria-labelledby="tab-firmar" data-quote-panel="firmar" tabindex="-1">
            <h2 class="solar-quote-heading">Qué preguntar antes de firmar</h2>
            <ul class="solar-client-quote__checklist">
                @unless ($quote->panel_warranty_years && $quote->inverter_warranty_years)
                    <li>¿Cuántos años garantizan los paneles y el inversor, y quién responde por la garantía?</li>
                @endunless
                @unless ($quote->panel_model && $quote->inverter_model)
                    <li>¿Qué marca y referencia son los paneles y el inversor? Pide las fichas técnicas.</li>
                @endunless
                @unless ($quote->delivery_days)
                    <li>¿Cuánto se demora desde el anticipo hasta que el sistema quede energizado?</li>
                @endunless
                @unless ($quote->vat_included !== null)
                    <li>¿Este precio ya incluye IVA o se suma aparte?</li>
                @endunless
                @unless ($quote->includes_battery)
                    <li>Sin baterías, de noche sigues comprando energía: ¿cuánto costaría agregarlas?</li>
                @endunless
                <li>¿El precio incluye la estructura del techo, el cableado y la mano de obra?</li>
            </ul>

            <div class="solar-client-quote__contact">
                <div>
                    <h3 class="solar-client-quote__others-title">Habla con {{ $installer->name }}</h3>
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
            </div>

            <x-installers.step-nav :steps="$steps" current="firmar" />
        </section>
    </div>
</x-layouts::app>
