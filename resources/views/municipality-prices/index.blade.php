{{--
    Administration: the price per kW the app quotes each municipality with (ADR-0024).
    Params: $municipalities, $locationTypes, $missing (App\Actions\Pricing\DescribeMunicipalityPrices).
--}}
@php
    $money = fn (float $cop): string => '$'.number_format(round($cop), 0, ',', '.');
    $factor = fn (float $value): string => number_format($value, 2, ',', '.');
    $hasErrors = $errors->any();
@endphp

<x-layouts::app :title="__('Precios por municipio')">
    <div class="solar-page solar-prices">
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Administración</p>
                <h1 class="solar-title mt-0">Precios por municipio</h1>
                <p class="solar-subtitle mt-2 max-w-3xl">
                    El precio por kW instalado con el que se cotiza cada municipio. De aquí sale la inversión
                    inicial de todo proyecto, su ahorro y su retorno.
                </p>
            </div>
        </div>

        <p class="solar-installers-notice" role="note">
            <strong>Lo ya cotizado no se toca.</strong> Un proyecto guarda el precio con el que se cotizó y lo
            conserva aunque este cambie; lo mismo la solicitud que el cliente ya le mandó a un instalador. El
            precio nuevo rige para lo que se cotice de ahora en adelante.
        </p>

        @if ($missing > 0)
            <p class="solar-catalog-note">
                {{ $missing === 1 ? 'Hay 1 municipio sin ningún precio' : 'Hay '.$missing.' municipios sin ningún precio' }}:
                un proyecto allí no se puede cotizar.
            </p>
        @endif

        @if ($hasErrors)
            <div class="solar-alert solar-alert-danger" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="solar-price-list">
            @foreach ($municipalities as $municipality)
                <section @class(['solar-card', 'solar-price-card', 'is-empty' => ! $municipality['prices']])>
                    <header class="solar-price-card__head">
                        <div>
                            <h2>{{ $municipality['name'] }}</h2>
                            <p>
                                {{ $municipality['zone'] ?? 'Sin zona' }}
                                · {{ $municipality['projects'] === 1 ? '1 proyecto' : $municipality['projects'].' proyectos' }}
                            </p>
                        </div>
                        @unless ($municipality['prices'])
                            <span class="solar-installer-badge is-muted">Sin precio</span>
                        @endunless
                    </header>

                    <div class="solar-price-types">
                        @foreach ($locationTypes as $type => $label)
                            @php($price = $municipality['prices'][$type] ?? null)
                            <details @class(['solar-price-type', 'is-set' => $price, 'is-off' => $price && ! $price['active']])>
                                <summary>
                                    <span class="solar-price-type__label">{{ $label }}</span>
                                    @if ($price)
                                        <span class="solar-price-type__value">{{ $money($price['finalPricePerKw']) }}<small>por kW</small></span>
                                        @unless ($price['active'])
                                            <span class="solar-installer-badge is-muted">Inactivo</span>
                                        @endunless
                                    @else
                                        <span class="solar-price-type__value is-empty">Sin precio</span>
                                    @endif
                                </summary>

                                <form method="POST" action="{{ route('municipality-prices.store') }}" class="solar-price-form">
                                    @csrf
                                    <input type="hidden" name="municipality_id" value="{{ $municipality['id'] }}">
                                    <input type="hidden" name="location_type" value="{{ $type }}">

                                    <label class="solar-field">
                                        <span class="solar-field-label">Precio base por kW</span>
                                        <input
                                            type="number"
                                            name="base_price_per_kw"
                                            value="{{ $price ? (int) $price['basePricePerKw'] : '' }}"
                                            min="500000"
                                            max="20000000"
                                            step="1000"
                                            required
                                            class="solar-input"
                                            inputmode="numeric"
                                            placeholder="4000000"
                                        >
                                    </label>

                                    <label class="solar-field">
                                        <span class="solar-field-label">Factor logístico</span>
                                        <input
                                            type="number"
                                            name="logistic_factor"
                                            value="{{ $price ? rtrim(rtrim(number_format($price['logisticFactor'], 3, '.', ''), '0'), '.') : '1' }}"
                                            min="1"
                                            max="2.5"
                                            step="0.01"
                                            required
                                            class="solar-input"
                                            inputmode="decimal"
                                        >
                                        <span class="solar-field-hint">Lo que cuesta llegar. 1 es sin recargo.</span>
                                    </label>

                                    <label class="solar-field solar-price-form__notes">
                                        <span class="solar-field-label">Nota <span class="solar-field-optional">· opcional</span></span>
                                        <input name="notes" value="{{ $price['notes'] ?? '' }}" maxlength="255" class="solar-input" placeholder="De dónde salió este precio.">
                                    </label>

                                    <label class="solar-installer-toggle solar-price-form__active">
                                        <input type="hidden" name="active" value="0">
                                        <input type="checkbox" name="active" value="1" @checked(! $price || $price['active'])>
                                        <span>
                                            <strong>Se cotiza con este precio</strong>
                                            <small>Al desactivarlo, el municipio cotiza con su precio urbano.</small>
                                        </span>
                                    </label>

                                    <div class="solar-price-form__actions">
                                        <button type="submit" class="solar-button">{{ $price ? 'Guardar' : 'Agregar precio' }}</button>
                                        @if ($price)
                                            <span class="solar-field-hint">
                                                Hoy: {{ $money($price['basePricePerKw']) }} × {{ $factor($price['logisticFactor']) }}
                                                = {{ $money($price['finalPricePerKw']) }} por kW
                                            </span>
                                        @endif
                                    </div>
                                </form>
                            </details>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    </div>
</x-layouts::app>
