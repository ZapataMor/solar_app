{{--
    One quote request, as the installer who received it reads it (ADR-0023): the estimate the client
    already calculated, the appliances they registered space by space (ADR-0013), and the answer.

    Params: App\Actions\Installers\DescribeQuoteRequest.
--}}
@php
    use App\Domain\Installers\QuoteRequestStatus;
    use App\Domain\Property\PropertyType;

    $money = fn (float $cop): string => '$'.number_format(round($cop, -3), 0, ',', '.');
    $kwh = fn (float $value): string => number_format($value, $value < 10 ? 1 : 0, ',', '.');
    $date = fn ($value): string => \Illuminate\Support\Carbon::parse($value)->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
@endphp

<x-layouts::app :title="$project->name">
    @include('solar-projects.partials.appliance-icons')

    <div class="solar-page solar-quote">
        <div class="solar-page-header">
            <div>
                <a href="{{ route('installer-inbox.index') }}" class="solar-project-nav__back" wire:navigate>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                    Solicitudes
                </a>
                <p class="solar-inbox-card__status mt-3" data-status="{{ $status }}">{{ $statusLabel }}</p>
                <h1 class="solar-title mt-2">{{ $project->name }}</h1>
                <p class="solar-subtitle mt-2">
                    {{ PropertyType::label($project->property_type) }}
                    @if ($municipality) · {{ $municipality }} @endif
                    @if ($project->location_type) · {{ ucfirst($project->location_type) }} @endif
                    · Pedida el {{ $date($requestedAt) }}
                </p>
            </div>

            <div class="solar-quote-client">
                <p class="solar-inbox-card__label">Cliente</p>
                <p class="solar-quote-client__name">{{ $clientName ?? 'Sin nombre' }}</p>
                @if ($clientEmail)
                    <a href="mailto:{{ $clientEmail }}" class="solar-inbox-mail">{{ $clientEmail }}</a>
                @endif
            </div>
        </div>

        @if ($errors->any())
            <div class="solar-alert solar-alert-danger" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @if ($note)
            <blockquote class="solar-inbox-quote">{{ $note }}</blockquote>
        @endif

        <section class="solar-card">
            <h2 class="solar-quote-heading">Lo que pide el proyecto</h2>

            <dl class="solar-inbox-figures mt-3">
                <div>
                    <dt>Consumo</dt>
                    <dd>{{ $kwh($monthlyKwh) }} kWh/mes</dd>
                </div>
                <div>
                    <dt>Techo disponible</dt>
                    <dd>{{ $roofAreaM2 !== null ? number_format($roofAreaM2, 0, ',', '.').' m²' : '—' }}</dd>
                </div>
                <div>
                    <dt>Paneles que pide</dt>
                    <dd>
                        {{ $panels ?? '—' }}
                        @if ($panelPowerW) <span class="solar-quote-sub">de {{ number_format($panelPowerW, 0, ',', '.') }} W</span> @endif
                    </dd>
                </div>
                <div>
                    <dt>Caben en el techo</dt>
                    <dd>
                        {{ $panelsThatFit ?? '—' }}
                        @if ($roofIsEnough === false)
                            <span class="solar-inbox-warning">no alcanza para lo que pide</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt>Cobertura</dt>
                    <dd>{{ $coverage !== null ? number_format(min($coverage, 999), 0, ',', '.').' %' : '—' }}</dd>
                </div>
                <div>
                    <dt>Presupuesto de referencia</dt>
                    <dd>
                        {{ $budgetCop !== null ? $money($budgetCop) : '—' }}
                        @if ($quotedPricePerKwCop)
                            <span class="solar-quote-sub">{{ $money($quotedPricePerKwCop) }} por kW</span>
                        @endif
                    </dd>
                </div>
            </dl>

            <p class="solar-inbox-note mt-3">
                @if ($fromClimateData)
                    Los paneles salen del último cálculo con datos climáticos del proyecto.
                @else
                    Los paneles salen del sol de referencia de La Guajira: el proyecto aún no se calculó con datos climáticos.
                @endif
                El presupuesto es el precio por kW de su municipio, no tu cotización.
                @if ($budgetMovedSince)
                    Es el del día en que te escribió: el precio del municipio cambió después y este número no se movió.
                @endif
            </p>
        </section>

        <section class="solar-card">
            <h2 class="solar-quote-heading">Qué tiene conectado</h2>

            @if ($usesBill)
                <p class="solar-subtitle mt-2">
                    Este cliente dio su consumo con el recibo de luz, no equipo por equipo (ADR-0020): son
                    {{ $kwh($monthlyKwh) }} kWh al mes, sin desglose.
                </p>
            @elseif ($diary && $diary['applianceCount'] > 0)
                <p class="solar-subtitle mt-2">
                    {{ $diary['applianceCount'] }} {{ $diary['applianceCount'] === 1 ? 'equipo registrado' : 'equipos registrados' }}
                    por el cliente, espacio por espacio. Suman {{ $kwh($diary['totalKwh']) }} kWh al mes.
                    @if ($diary['biggest'])
                        Lo que más gasta es {{ $diary['biggest']['label'] }}, con el {{ number_format($diary['biggest']['share'], 0) }} %.
                    @endif
                </p>

                <div class="solar-quote-spaces">
                    @foreach ($diary['spaces'] as $space)
                        @continue(! $space['items'])
                        <div class="solar-quote-space">
                            <div class="solar-quote-space__head">
                                <h3>{{ $space['label'] }}</h3>
                                <p>{{ $kwh($space['kwh']) }} kWh/mes · {{ number_format($space['share'], 0) }} %</p>
                            </div>

                            <ul class="solar-quote-appliances">
                                @foreach ($space['items'] as $item)
                                    <li>
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#appliance-{{ $item['icon'] }}"></use></svg>
                                        <span class="solar-quote-appliance__name">
                                            {{ $item['quantity'] > 1 ? $item['quantity'].' × ' : '' }}{{ $item['label'] }}
                                            @if ($item['variantLabel'])
                                                <small>{{ $item['variantLabel'] }}</small>
                                            @endif
                                        </span>
                                        <span class="solar-quote-appliance__usage">{{ $item['usageText'] }}</span>
                                        <span class="solar-quote-appliance__kwh">{{ $kwh($item['kwh']) }} kWh/mes</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="solar-subtitle mt-2">El cliente todavía no registró equipos en su proyecto.</p>
            @endif
        </section>

        {{-- The price the client asked for (ADR-0026). Above the status, because it is the answer. --}}
        <section class="solar-card solar-quote-offer">
            <div class="solar-quote-offer__head">
                <h2 class="solar-quote-heading">Tu cotización</h2>
                @if ($quote)
                    <p @class(['solar-inbox-card__status', 'is-expired' => $quote->hasExpired()]) data-status="{{ $quote->hasExpired() ? 'lost' : 'won' }}">
                        {{ $quote->hasExpired() ? 'Vencida' : 'Vigente' }}
                    </p>
                @endif
            </div>

            @if ($quote)
                <p class="solar-subtitle mt-2">
                    Le ofreciste <strong>{{ $money((float) $quote->amount_cop) }}</strong>
                    @if ($quote->power_kw) por un sistema de {{ number_format((float) $quote->power_kw, 2, ',', '.') }} kW @endif
                    {{ $quote->includes_battery ? 'con baterías' : 'sin baterías' }}.
                    @if ($quote->hasExpired())
                        <span class="solar-inbox-warning">El precio venció el {{ $date($quote->valid_until) }}; actualízalo si sigue en pie.</span>
                    @else
                        Vale hasta el {{ $date($quote->valid_until) }}.
                    @endif
                </p>
            @else
                <p class="solar-subtitle mt-2">Todavía no le has puesto precio. Es lo que el cliente fue a buscar.</p>
            @endif

            <form method="POST" action="{{ route('installer-inbox.quote', $quoteRequest) }}" class="solar-quote-offer__form">
                @csrf
                @method('PUT')

                <label class="solar-field">
                    <span class="solar-field-label">Cuánto cuesta</span>
                    <input
                        type="number"
                        name="amount_cop"
                        value="{{ old('amount_cop', $quote ? (int) $quote->amount_cop : '') }}"
                        min="1000000"
                        step="1000"
                        required
                        class="solar-input"
                        inputmode="numeric"
                        placeholder="18000000"
                    >
                    <span class="solar-field-hint">En pesos, sin puntos. Instalación completa.</span>
                </label>

                <label class="solar-field">
                    <span class="solar-field-label">Potencia que propones <span class="solar-field-optional">· opcional</span></span>
                    <input
                        type="number"
                        name="power_kw"
                        value="{{ old('power_kw', $quote?->power_kw ? rtrim(rtrim(number_format((float) $quote->power_kw, 2, '.', ''), '0'), '.') : '') }}"
                        min="0.1"
                        step="0.1"
                        class="solar-input"
                        inputmode="decimal"
                        placeholder="{{ $panels && $panelPowerW ? number_format($panels * $panelPowerW / 1000, 1, '.', '') : '5' }}"
                    >
                    <span class="solar-field-hint">En kW. Puede diferir de lo que estimó la app.</span>
                </label>

                <label class="solar-field">
                    <span class="solar-field-label">Hasta cuándo vale</span>
                    <input
                        type="date"
                        name="valid_until"
                        value="{{ old('valid_until', $quote?->valid_until?->format('Y-m-d') ?? now()->addDays(30)->format('Y-m-d')) }}"
                        min="{{ now()->format('Y-m-d') }}"
                        required
                        class="solar-input"
                    >
                    <span class="solar-field-hint">Los equipos son importados: el precio se mueve.</span>
                </label>

                <label class="solar-field solar-quote-offer__scope">
                    <span class="solar-field-label">Qué incluye <span class="solar-field-optional">· opcional</span></span>
                    <textarea name="scope" rows="3" maxlength="1000" class="solar-textarea" placeholder="Paneles, inversor, estructura, cableado, mano de obra, trámite con la electrificadora…">{{ old('scope', $quote?->scope) }}</textarea>
                </label>

                <label class="solar-installer-toggle solar-quote-offer__battery">
                    <input type="hidden" name="includes_battery" value="0">
                    <input type="checkbox" name="includes_battery" value="1" @checked(old('includes_battery', $quote?->includes_battery))>
                    <span>
                        <strong>Incluye baterías</strong>
                        <small>Dos cotizaciones del mismo sistema no se comparan si una las lleva y la otra no.</small>
                    </span>
                </label>

                <div class="solar-quote-offer__actions">
                    <button type="submit" class="solar-button">{{ $quote ? 'Actualizar la cotización' : 'Enviar la cotización' }}</button>
                </div>
            </form>
        </section>

        <section class="solar-card solar-quote-answer">
            <h2 class="solar-quote-heading">Cómo va</h2>
            <p class="solar-subtitle mt-2">
                @if ($answeredAt)
                    La respondiste el {{ $date($answeredAt) }}.
                    @if ($contractValueCop) Quedó cerrada en <strong>{{ $money($contractValueCop) }}</strong>. @endif
                @else
                    Nadie ha respondido esta solicitud todavía.
                @endif
            </p>

            <form method="POST" action="{{ route('installer-inbox.update', $quoteRequest) }}" class="solar-inbox-form" data-inbox-answer>
                @csrf
                @method('PUT')

                <label class="solar-field">
                    <span class="solar-field-label">Marcar como</span>
                    <select name="status" class="solar-input" data-inbox-status required>
                        {{-- "Cotizada" is set by sending the price, not from here, so it is not an
                             option: when that is the state, nothing comes preselected. --}}
                        @unless (array_key_exists($status, QuoteRequestStatus::answers()))
                            <option value="" disabled selected>Elige una</option>
                        @endunless
                        @foreach (QuoteRequestStatus::answers() as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                {{-- Only with a closed deal; app.js shows it with the class, never with [hidden]. --}}
                <label @class(['solar-field', 'solar-inbox-contract', 'is-shown' => $status === QuoteRequestStatus::WON]) data-inbox-contract>
                    <span class="solar-field-label">Valor del contrato</span>
                    <input
                        type="number"
                        name="contract_value_cop"
                        value="{{ $contractValueCop !== null ? (int) $contractValueCop : '' }}"
                        min="0"
                        step="1000"
                        class="solar-input"
                        inputmode="numeric"
                        placeholder="18000000"
                    >
                    <span class="solar-field-hint">En pesos, sin puntos. Queda guardado para la liquidación.</span>
                </label>

                <button type="submit" class="solar-button">Guardar</button>
            </form>
        </section>
    </div>
</x-layouts::app>
