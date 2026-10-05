{{--
    Administration: the price per kW the app quotes each municipality with (ADR-0024).

    An accordion, not a wall: fifteen municipalities by four kinds of location are sixty rows, and
    most of them say "sin precio". Closed, each municipality says the one thing worth scanning (its
    urban price and how many of its four are set); open, it shows the rows and their forms.

    Params: $municipalities, $locationTypes, $missing (App\Actions\Pricing\DescribeMunicipalityPrices).
--}}
@php
    $money = fn (float $cop): string => '$'.number_format(round($cop), 0, ',', '.');
    $factor = fn (float $value): string => number_format($value, 2, ',', '.');
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
                    <strong>Lo ya cotizado no cambia:</strong> el precio nuevo rige para lo que se cotice
                    desde que lo guardes.
                </p>
            </div>
        </div>

        @if ($errors->any())
            <div class="solar-alert solar-alert-danger" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @if ($missing > 0)
            <p class="solar-catalog-note">
                {{ $missing === 1 ? 'Hay 1 municipio sin ningún precio' : 'Hay '.$missing.' municipios sin ningún precio' }}:
                un proyecto allí no se puede cotizar.
            </p>
        @endif

        <div class="solar-price-list">
            @foreach ($municipalities as $municipality)
                @php($set = count($municipality['prices']))
                @php($main = $municipality['prices']['urbana'] ?? reset($municipality['prices']) ?: null)

                <details @class(['solar-card', 'solar-price-card', 'is-empty' => ! $set]) data-test="municipality-{{ $municipality['id'] }}">
                    <summary class="solar-price-card__head">
                        <span class="solar-price-card__name">
                            <strong>{{ $municipality['name'] }}</strong>
                            <small>
                                {{ $municipality['zone'] ?? 'Sin zona' }}
                                · {{ $municipality['projects'] === 1 ? '1 proyecto' : $municipality['projects'].' proyectos' }}
                            </small>
                        </span>

                        <span class="solar-price-card__summary">
                            @if ($main)
                                <span class="solar-price-type__value">{{ $money($main['finalPricePerKw']) }}<small>por kW</small></span>
                                <small>{{ $set }} de {{ count($locationTypes) }} con precio</small>
                            @else
                                <span class="solar-installer-badge is-muted">Sin precio</span>
                            @endif
                        </span>
                    </summary>

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
                                        <span class="solar-field-hint">1 es sin recargo.</span>
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
                </details>
            @endforeach
        </div>
    </div>
</x-layouts::app>
