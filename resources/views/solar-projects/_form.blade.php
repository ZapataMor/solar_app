@php
    $technicalParameter = $solarProject?->technicalParameter;
    $investmentCostPerKwpCop = 5000000;
    $referenceDailyHsp = 5.8;
    $ambientContextUrl = route('solar-projects.simulator.ambient-context');
    $municipalities = $municipalities ?? collect();
    $locationTypes = [
        'urbana' => 'Urbana',
        'rural' => 'Rural',
        'rural_dispersa' => 'Rural dispersa',
        'alta_guajira' => 'Alta Guajira',
    ];
    $municipalityDaneCodes = [
        'Riohacha' => '44001',
        'Albania' => '44035',
        'Barrancas' => '44078',
        'Dibulla' => '44090',
        'Distracción' => '44098',
        'El Molino' => '44110',
        'Fonseca' => '44279',
        'Hatonuevo' => '44378',
        'La Jagua del Pilar' => '44420',
        'Maicao' => '44430',
        'Manaure' => '44560',
        'San Juan del Cesar' => '44650',
        'Uribia' => '44847',
        'Urumita' => '44855',
        'Villanueva' => '44874',
    ];
    $selectedMunicipalityId = old('municipality_id', $solarProject?->municipality_id);
    $selectedLocationType = old('location_type', $solarProject?->location_type ?? 'urbana');
    $selectedRequiredPower = old('required_power_kw', $solarProject?->required_power_kw);
    $isCreating = strtoupper($method) === 'POST';
    $solarPriceUrlTemplate = route('municipalities.solar-price', ['municipality' => '__MUNICIPALITY__']);
@endphp

@if ($errors->any())
    <div class="solar-alert solar-alert-danger">
        <ul class="list-disc space-y-1 ps-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}" class="solar-page">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <section class="solar-card-strong">
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Base del proyecto</p>
                <h2 class="text-2xl text-[color:var(--solar-text)]">Informacion del proyecto</h2>
                <p class="solar-subtitle mt-2">Riohacha es el punto de referencia principal, pero pronto podrás seleccionar cualquier municipio de La Guajira.</p>
            </div>
            <span class="solar-pill">Contexto local permanente</span>
        </div>

        <div class="solar-form-grid mt-6 md:grid-cols-2">
            <label class="solar-field">
                <span class="solar-field-label">Nombre del proyecto</span>
                <input
                    name="name"
                    value="{{ old('name', $solarProject?->name) }}"
                    required
                    class="solar-input"
                >
            </label>

            <label class="solar-field">
                <span class="solar-field-label">Ubicacion registrada</span>
                <input
                    value="{{ old('location_name', $solarProject?->location_name ?? 'La Guajira, Colombia') }}"
                    disabled
                    class="solar-input"
                >
            </label>

            <label class="solar-field md:col-span-2">
                <span class="solar-field-label">Descripcion</span>
                <textarea
                    name="description"
                    rows="4"
                    class="solar-textarea"
                >{{ old('description', $solarProject?->description) }}</textarea>
            </label>

            <label class="solar-field">
                <span class="solar-field-label">Fecha inicial</span>
                <input
                    type="date"
                    name="start_date"
                    value="{{ old('start_date', $solarProject?->start_date?->format('Y-m-d')) }}"
                    required
                    class="solar-input"
                >
            </label>

            @unless ($isCreating)
                <label class="solar-field">
                    <span class="solar-field-label">Fecha final</span>
                    <input
                        type="date"
                        name="end_date"
                        value="{{ old('end_date', $solarProject?->end_date?->format('Y-m-d')) }}"
                        required
                        class="solar-input"
                    >
                </label>
            @endunless

            <label class="solar-field">
                <span class="solar-field-label">Consumo mensual en kWh</span>
                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    name="monthly_consumption_kwh"
                    value="{{ old('monthly_consumption_kwh', $solarProject?->monthly_consumption_kwh ?? ($solarProject?->annual_consumption_kwh ? $solarProject->annual_consumption_kwh / 12 : null)) }}"
                    required
                    class="solar-input"
                >
                <span class="text-xs text-[color:var(--solar-text-muted)]">
                    El sistema derivara automaticamente consumo diario aproximado y consumo anual.
                </span>
            </label>

            <label class="solar-field">
                <span class="solar-field-label">Tarifa energetica en COP/kWh</span>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="energy_rate_cop_kwh"
                    value="{{ old('energy_rate_cop_kwh', $solarProject?->energy_rate_cop_kwh) }}"
                    required
                    class="solar-input"
                >
            </label>

            <div class="md:col-span-2">
                <button type="button" class="solar-button-ghost solar-energy-guide-trigger" data-energy-guide-open>
                    ¿Cuál es mi consumo en kWh y mi tarifa en COP/kWh?
                </button>
            </div>
        </div>
    </section>

    <div
        class="solar-energy-guide-modal"
        data-energy-guide-modal
        hidden
        role="dialog"
        aria-modal="true"
        aria-labelledby="energy-guide-title"
    >
        <div class="solar-energy-guide-backdrop" data-energy-guide-close></div>
        <div class="solar-energy-guide-panel" role="document">
            <div class="solar-energy-guide-header">
                <h3 id="energy-guide-title" class="solar-energy-guide-title">
                    En la segunda hoja de tu recibo de energía encontrarás este apartado, aquí podrás ver tu consumo en kWh y tu tarifa energética en COP/kWh.
                </h3>
                <button type="button" class="solar-button-ghost solar-energy-guide-close" data-energy-guide-close aria-label="Cerrar guia del recibo">
                    Cerrar
                </button>
            </div>

            <div class="solar-energy-guide-body">
                <img
                    src="{{ asset('images/guia-recibo-energia.jpeg') }}"
                    alt="Guia visual del recibo de energia donde se encuentran la tarifa y el consumo en kWh"
                    class="solar-energy-guide-image"
                >
            </div>
        </div>
    </div>

    <section class="solar-card" data-location-quote>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Ubicacion del proyecto</p>
                <h2 class="text-2xl text-[color:var(--solar-text)]">Mapa y matriz de precios por municipio</h2>
                <p class="solar-subtitle mt-2">Selecciona un municipio en el listado o haz clic en el mapa para estimar el costo de instalacion con el factor logistico territorial.</p>
            </div>
            <span class="solar-pill solar-pill-success">La Guajira</span>
        </div>

        <style>
            .solar-location-layout { display: grid; gap: 1rem; margin-top: 1.5rem; }
            @media (min-width: 1024px) { .solar-location-layout { grid-template-columns: minmax(0, 1.5fr) minmax(20rem, .8fr); } }
            .solar-location-map { min-height: 26rem; overflow: hidden; border: 1px solid var(--solar-border); border-radius: 1rem; background: var(--solar-surface-muted); }
            .solar-location-summary { display: grid; gap: .7rem; align-content: start; border: 1px solid var(--solar-border); border-radius: 1rem; background: var(--solar-surface-muted); padding: 1rem; }
            .solar-location-row { display: flex; justify-content: space-between; gap: 1rem; border-bottom: 1px solid color-mix(in srgb, var(--solar-border) 72%, transparent); padding-bottom: .55rem; color: var(--solar-text-muted); font-size: .86rem; }
            .solar-location-row strong { color: var(--solar-text); text-align: right; }
            .solar-location-message { border-radius: .8rem; background: var(--solar-warning-bg); padding: .8rem; color: var(--solar-warning); font-size: .84rem; }
        </style>

        <div class="solar-form-grid mt-6 md:grid-cols-2">
            <label class="solar-field">
                <span class="solar-field-label">Municipio</span>
                <select name="municipality_id" required class="solar-input" data-location-municipality>
                    <option value="">Selecciona un municipio</option>
                    @foreach ($municipalities as $municipality)
                        <option
                            value="{{ $municipality->id }}"
                            data-name="{{ $municipality->name }}"
                            data-zone="{{ $municipality->zone }}"
                            data-dane-code="{{ $municipalityDaneCodes[$municipality->name] ?? '' }}"
                            data-latitude="{{ $municipality->latitude }}"
                            data-longitude="{{ $municipality->longitude }}"
                            @selected((string) $selectedMunicipalityId === (string) $municipality->id)
                        >
                            {{ $municipality->name }}
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="solar-field">
                <span class="solar-field-label">Tipo de ubicacion</span>
                <select name="location_type" required class="solar-input" data-location-type>
                    @foreach ($locationTypes as $value => $label)
                        <option value="{{ $value }}" @selected($selectedLocationType === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="solar-field">
                <span class="solar-field-label">Latitud</span>
                <input type="number" step="0.000001" name="latitude" value="{{ old('latitude', $solarProject?->latitude) }}" class="solar-input" data-location-latitude>
            </label>

            <label class="solar-field">
                <span class="solar-field-label">Longitud</span>
                <input type="number" step="0.000001" name="longitude" value="{{ old('longitude', $solarProject?->longitude) }}" class="solar-input" data-location-longitude>
            </label>

            <input type="hidden" name="required_power_kw" value="{{ $selectedRequiredPower }}" data-required-power>
        </div>

        <div class="solar-location-layout">
            <div id="la-guajira-map" class="solar-location-map"></div>
            <aside class="solar-location-summary">
                <div class="solar-location-row"><span>Municipio seleccionado</span><strong data-price-municipality>--</strong></div>
                <div class="solar-location-row"><span>Zona</span><strong data-price-zone>--</strong></div>
                <div class="solar-location-row"><span>Tipo de ubicacion</span><strong data-price-location-type>--</strong></div>
                <div class="solar-location-row"><span>Precio base por kW</span><strong data-price-base>--</strong></div>
                <div class="solar-location-row"><span>Factor logistico</span><strong data-price-factor>--</strong></div>
                <div class="solar-location-row"><span>Precio final por kW</span><strong data-price-final>--</strong></div>
                <div class="solar-location-row"><span>Potencia requerida</span><strong data-price-power>--</strong></div>
                <div class="solar-location-row"><span>Costo estimado</span><strong data-price-estimated>--</strong></div>
                <p class="solar-location-message" data-price-message>Selecciona municipio, tipo de ubicacion y potencia para calcular.</p>
            </aside>
        </div>
    </section>

    <section class="solar-card">
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Dimensionamiento</p>
                <h2 class="text-2xl text-[color:var(--solar-text)]">Parametros tecnicos</h2>
                <p class="solar-subtitle mt-2">Estos valores alimentan la lectura diaria, mensual y anual del sistema fotovoltaico.</p>
            </div>
            <span class="solar-pill solar-pill-success">Optimizacion energetica</span>
        </div>

        <div class="solar-form-grid mt-6 md:grid-cols-2">
            <label class="solar-field">
                <span class="solar-field-label">Area total disponible en m2</span>
                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    name="available_area_m2"
                    value="{{ old('available_area_m2', $technicalParameter?->available_area_m2) }}"
                    required
                    class="solar-input"
                >
            </label>

            <label class="solar-field">
                <span class="solar-field-label">Area utilizable %</span>
                <input
                    type="number"
                    step="0.01"
                    min="1"
                    max="100"
                    name="usable_area_percentage"
                    value="{{ old('usable_area_percentage', $technicalParameter?->usable_area_percentage) }}"
                    required
                    class="solar-input"
                >
            </label>

            <label class="solar-field">
                <span class="solar-field-label">Potencia del panel en W</span>
                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    name="panel_power_w"
                    value="{{ old('panel_power_w', $technicalParameter?->panel_power_w) }}"
                    required
                    class="solar-input"
                >
            </label>

            <label class="solar-field">
                <span class="solar-field-label">Area del panel en m2</span>
                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    name="panel_area_m2"
                    value="{{ old('panel_area_m2', $technicalParameter?->panel_area_m2) }}"
                    required
                    class="solar-input"
                >
            </label>

            <label class="solar-field">
                <span class="solar-field-label">Perdidas del sistema %</span>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    name="system_losses_percentage"
                    value="{{ old('system_losses_percentage', $technicalParameter?->system_losses_percentage) }}"
                    required
                    class="solar-input"
                >
            </label>
        </div>
    </section>

    <section class="solar-card" data-project-simulator>
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Pre-simulacion</p>
                <h2 class="text-2xl text-[color:var(--solar-text)]">Impacto energetico y financiero estimado</h2>
                <p class="solar-subtitle mt-2">
                    Vista previa basada en parametros del formulario para comparar generacion esperada vs consumo y estimar inversion/retorno.
                </p>
            </div>
            <span class="solar-pill solar-pill-warn">Estimado previo</span>
        </div>

        <div class="flex items-center gap-2 mt-4" role="group" aria-label="Escala del simulador">
            <button type="button" class="solar-button-ghost" data-sim-scale-btn="monthly" aria-pressed="true">Mensual</button>
            <button type="button" class="solar-button-ghost" data-sim-scale-btn="annual" aria-pressed="false">Anual</button>
        </div>

        <div class="solar-form-grid mt-6 md:grid-cols-3">
            <article class="solar-metric-card min-w-0">
                <p class="solar-metric-label">Capacidad instalada estimada</p>
                <p class="solar-metric-value" data-sim-capacity>—</p>
                <p class="solar-metric-copy" data-sim-panels>Paneles estimados: —</p>
            </article>
            <article class="solar-metric-card min-w-0">
                <p class="solar-metric-label" data-sim-generation-label>Generacion mensual estimada</p>
                <p class="solar-metric-value" data-sim-generation>—</p>
                <p class="solar-metric-copy" data-sim-generation-alt>Equivalente anual: —</p>
            </article>
            <article class="solar-metric-card min-w-0">
                <p class="solar-metric-label">Cobertura estimada</p>
                <p class="solar-metric-value" data-sim-coverage>—</p>
                <p class="solar-metric-copy" data-sim-balance>Balance mensual: —</p>
            </article>
            <article class="solar-metric-card min-w-0">
                <p class="solar-metric-label">Inversion estimada</p>
                <p class="solar-metric-value" data-sim-investment>—</p>
                <p class="solar-metric-copy">Base: {{ number_format($investmentCostPerKwpCop, 0, ',', '.') }} COP/kWp</p>
            </article>
            <article class="solar-metric-card min-w-0">
                <p class="solar-metric-label" data-sim-savings-label>Ahorro mensual estimado</p>
                <p class="solar-metric-value" data-sim-savings>—</p>
                <p class="solar-metric-copy" data-sim-savings-alt>Equivalente anual: —</p>
            </article>
            <article class="solar-metric-card min-w-0">
                <p class="solar-metric-label">Retorno de inversion</p>
                <p class="solar-metric-value" data-sim-payback>—</p>
                <p class="solar-metric-copy" data-sim-status>Completa los datos para estimar el payback.</p>
            </article>
        </div>

        <p class="text-xs text-[color:var(--solar-text-muted)] mt-4" data-sim-radiation-context>
            Cargando contexto de radiacion desde Ambient Weather...
        </p>
    </section>

    <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="solar-button">
            {{ $buttonText }}
        </button>

        <a href="{{ route('solar-projects.index') }}" class="solar-button-ghost">
            Cancelar
        </a>
    </div>
</form>

<script>
(() => {
    const modal = document.querySelector('[data-energy-guide-modal]');
    const openButton = document.querySelector('[data-energy-guide-open]');
    const closeButtons = Array.from(document.querySelectorAll('[data-energy-guide-close]'));
    let previousFocus = null;

    if (!modal || !openButton) {
        return;
    }

    const closeModal = () => {
        modal.hidden = true;
        modal.classList.remove('is-open');
        document.body.classList.remove('overflow-hidden');
        document.removeEventListener('keydown', handleKeydown);
        previousFocus?.focus();
    };

    const openModal = () => {
        previousFocus = document.activeElement;
        modal.hidden = false;
        modal.classList.add('is-open');
        document.body.classList.add('overflow-hidden');
        document.addEventListener('keydown', handleKeydown);
        modal.querySelector('button[data-energy-guide-close]')?.focus();
    };

    function handleKeydown(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    }

    openButton.addEventListener('click', openModal);
    closeButtons.forEach((button) => button.addEventListener('click', closeModal));
})();
</script>

<script>
(() => {
    const form = document.querySelector('form[action="{{ $action }}"]');

    if (!form) {
        return;
    }

    const numberFormatter = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 2 });
    const moneyFormatter = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const toNumber = (value) => {
        const normalized = String(value ?? '').trim().replace(',', '.');
        const number = Number(normalized);
        return Number.isFinite(number) ? number : 0;
    };

    const investmentCostPerKwp = {{ $investmentCostPerKwpCop }};
    const defaultDailyHsp = {{ $referenceDailyHsp }};
    const ambientContextUrl = @json($ambientContextUrl);
    let activeScale = 'monthly';
    let ambientDailyHsp = defaultDailyHsp;
    let ambientSourceLabel = 'Referencia local fija';

    const fields = {
        startDate: form.querySelector('[name="start_date"]'),
        endDate: form.querySelector('[name="end_date"]'),
        monthlyConsumption: form.querySelector('[name="monthly_consumption_kwh"]'),
        energyRate: form.querySelector('[name="energy_rate_cop_kwh"]'),
        availableArea: form.querySelector('[name="available_area_m2"]'),
        usableAreaPct: form.querySelector('[name="usable_area_percentage"]'),
        panelPower: form.querySelector('[name="panel_power_w"]'),
        panelArea: form.querySelector('[name="panel_area_m2"]'),
        systemLossesPct: form.querySelector('[name="system_losses_percentage"]'),
        requiredPower: form.querySelector('[name="required_power_kw"]'),
    };

    const out = {
        capacity: form.querySelector('[data-sim-capacity]'),
        panels: form.querySelector('[data-sim-panels]'),
        generation: form.querySelector('[data-sim-generation]'),
        generationLabel: form.querySelector('[data-sim-generation-label]'),
        generationAlt: form.querySelector('[data-sim-generation-alt]'),
        coverage: form.querySelector('[data-sim-coverage]'),
        balance: form.querySelector('[data-sim-balance]'),
        investment: form.querySelector('[data-sim-investment]'),
        savings: form.querySelector('[data-sim-savings]'),
        savingsLabel: form.querySelector('[data-sim-savings-label]'),
        savingsAlt: form.querySelector('[data-sim-savings-alt]'),
        payback: form.querySelector('[data-sim-payback]'),
        status: form.querySelector('[data-sim-status]'),
        radiationContext: form.querySelector('[data-sim-radiation-context]'),
        requiredPowerHint: form.querySelector('[data-required-power-hint]'),
    };

    const scaleButtons = Array.from(form.querySelectorAll('[data-sim-scale-btn]'));
    let requiredPowerManuallyEdited = fields.requiredPower?.type !== 'hidden' && Boolean(fields.requiredPower?.value);

    const sourceCopy = () => `${ambientSourceLabel}: ${numberFormatter.format(ambientDailyHsp)} HSP/dia. Sin degradacion anual ni costos O&M.`;

    const syncRequiredPowerSuggestion = (monthlyConsumption, performanceRatio) => {
        if (!fields.requiredPower || !monthlyConsumption || performanceRatio <= 0 || ambientDailyHsp <= 0) {
            return;
        }

        if (fields.requiredPower.type !== 'hidden' && requiredPowerManuallyEdited && toNumber(fields.requiredPower.value) > 0) {
            return;
        }

        const recommendedPowerKw = monthlyConsumption / (ambientDailyHsp * 30 * performanceRatio);

        if (!Number.isFinite(recommendedPowerKw) || recommendedPowerKw <= 0) {
            return;
        }

        fields.requiredPower.dataset.autoUpdating = 'true';
        fields.requiredPower.value = recommendedPowerKw.toFixed(2);
        fields.requiredPower.dispatchEvent(new Event('input', { bubbles: true }));
        delete fields.requiredPower.dataset.autoUpdating;

        if (out.requiredPowerHint) {
            out.requiredPowerHint.textContent = `Sugerencia automatica: ${numberFormatter.format(recommendedPowerKw)} kW para cubrir aproximadamente el consumo mensual registrado. Puedes ajustarla si quieres simular otra meta.`;
        }
    };

    const setDefaultState = () => {
        out.capacity.textContent = '—';
        out.panels.textContent = 'Paneles estimados: —';
        out.generationLabel.textContent = activeScale === 'annual' ? 'Generacion anual estimada' : 'Generacion mensual estimada';
        out.generation.textContent = '—';
        out.generationAlt.textContent = activeScale === 'annual' ? 'Equivalente mensual: —' : 'Equivalente anual: —';
        out.coverage.textContent = '—';
        out.balance.textContent = activeScale === 'annual' ? 'Balance anual: —' : 'Balance mensual: —';
        out.investment.textContent = '—';
        out.savingsLabel.textContent = activeScale === 'annual' ? 'Ahorro anual estimado' : 'Ahorro mensual estimado';
        out.savings.textContent = '—';
        out.savingsAlt.textContent = activeScale === 'annual' ? 'Equivalente mensual: —' : 'Equivalente anual: —';
        out.payback.textContent = '—';
        out.status.textContent = 'Completa los datos para estimar el payback.';
        if (out.radiationContext) {
            out.radiationContext.textContent = sourceCopy();
        }
    };

    const setScale = (scale) => {
        activeScale = scale === 'annual' ? 'annual' : 'monthly';
        scaleButtons.forEach((button) => {
            const pressed = button.dataset.simScaleBtn === activeScale;
            button.setAttribute('aria-pressed', pressed ? 'true' : 'false');
            button.classList.toggle('solar-button', pressed);
            button.classList.toggle('solar-button-ghost', !pressed);
        });
        update();
    };

    const resolveSourceLabel = (source) => {
        if (source === 'ambient_range') {
            return 'Ambient Weather (rango del proyecto)';
        }
        if (source === 'ambient_recent_fallback') {
            return 'Ambient Weather (historico reciente)';
        }
        return 'Referencia local fija';
    };

    const fetchAmbientContext = async () => {
        const startDate = fields.startDate?.value;
        const endDate = fields.endDate?.value || startDate;

        if (!startDate || !endDate) {
            ambientDailyHsp = defaultDailyHsp;
            ambientSourceLabel = 'Referencia local fija';
            update();
            return;
        }

        try {
            const url = new URL(ambientContextUrl, window.location.origin);
            url.searchParams.set('start_date', startDate);
            url.searchParams.set('end_date', endDate);

            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();
            const hsp = Number(payload?.avg_daily_hsp);
            ambientDailyHsp = Number.isFinite(hsp) && hsp > 0 ? hsp : defaultDailyHsp;
            ambientSourceLabel = resolveSourceLabel(payload?.source);
        } catch (_error) {
            ambientDailyHsp = defaultDailyHsp;
            ambientSourceLabel = 'Referencia local fija';
        }

        update();
    };

    const update = () => {
        const monthlyConsumption = toNumber(fields.monthlyConsumption?.value);
        const annualConsumption = monthlyConsumption * 12;
        const energyRate = toNumber(fields.energyRate?.value);
        const availableArea = toNumber(fields.availableArea?.value);
        const usableAreaPct = toNumber(fields.usableAreaPct?.value);
        const panelPowerW = toNumber(fields.panelPower?.value);
        const panelArea = toNumber(fields.panelArea?.value);
        const systemLossesPct = toNumber(fields.systemLossesPct?.value);
        const performanceRatio = systemLossesPct >= 0 && systemLossesPct <= 100
            ? 1 - (systemLossesPct / 100)
            : 0;

        syncRequiredPowerSuggestion(monthlyConsumption, performanceRatio);

        if (!monthlyConsumption || !energyRate || !availableArea || !usableAreaPct || !panelPowerW || !panelArea || systemLossesPct < 0 || systemLossesPct > 100) {
            setDefaultState();
            return;
        }

        const usableArea = availableArea * (usableAreaPct / 100);
        const panels = panelArea > 0 ? Math.floor(usableArea / panelArea) : 0;
        const capacityKwp = (panels * panelPowerW) / 1000;
        const monthlyGeneration = capacityKwp * ambientDailyHsp * 30 * performanceRatio;
        const annualGeneration = monthlyGeneration * 12;
        const annualSavings = annualGeneration * energyRate;
        const monthlySavings = annualSavings / 12;
        const coverage = annualConsumption > 0 ? (annualGeneration / annualConsumption) * 100 : 0;
        const annualBalance = annualGeneration - annualConsumption;
        const monthlyBalance = monthlyGeneration - monthlyConsumption;
        const investment = capacityKwp * investmentCostPerKwp;
        const paybackYears = annualSavings > 0 ? (investment / annualSavings) : null;
        const scopedGeneration = activeScale === 'annual' ? annualGeneration : monthlyGeneration;
        const altGeneration = activeScale === 'annual' ? monthlyGeneration : annualGeneration;
        const scopedSavings = activeScale === 'annual' ? annualSavings : monthlySavings;
        const altSavings = activeScale === 'annual' ? monthlySavings : annualSavings;
        const scopedBalance = activeScale === 'annual' ? annualBalance : monthlyBalance;

        out.capacity.textContent = `${numberFormatter.format(capacityKwp)} kWp`;
        out.panels.textContent = `Paneles estimados: ${numberFormatter.format(panels)}`;
        out.generationLabel.textContent = activeScale === 'annual' ? 'Generacion anual estimada' : 'Generacion mensual estimada';
        out.generation.textContent = `${numberFormatter.format(scopedGeneration)} kWh`;
        out.generationAlt.textContent = activeScale === 'annual'
            ? `Equivalente mensual: ${numberFormatter.format(altGeneration)} kWh`
            : `Equivalente anual: ${numberFormatter.format(altGeneration)} kWh`;
        out.coverage.textContent = `${numberFormatter.format(coverage)}%`;
        out.balance.textContent = activeScale === 'annual'
            ? `Balance anual: ${numberFormatter.format(scopedBalance)} kWh`
            : `Balance mensual: ${numberFormatter.format(scopedBalance)} kWh`;
        out.investment.textContent = moneyFormatter.format(investment);
        out.savingsLabel.textContent = activeScale === 'annual' ? 'Ahorro anual estimado' : 'Ahorro mensual estimado';
        out.savings.textContent = moneyFormatter.format(scopedSavings);
        out.savingsAlt.textContent = activeScale === 'annual'
            ? `Equivalente mensual: ${moneyFormatter.format(altSavings)}`
            : `Equivalente anual: ${moneyFormatter.format(altSavings)}`;
        if (out.radiationContext) {
            out.radiationContext.textContent = sourceCopy();
        }

        if (paybackYears === null || !Number.isFinite(paybackYears) || paybackYears <= 0) {
            out.payback.textContent = 'N/A';
            out.status.textContent = 'Con estos datos no se puede estimar un retorno valido.';
            return;
        }

        out.payback.textContent = `${numberFormatter.format(paybackYears)} anos`;
        out.status.textContent = paybackYears <= 6
            ? 'Retorno atractivo en el escenario actual.'
            : (paybackYears <= 10 ? 'Retorno moderado; revisa eficiencia y costos.' : 'Retorno largo; conviene optimizar dimensionamiento o tarifa.');
    };

    Object.entries(fields).forEach(([name, field]) => {
        if (name === 'requiredPower') {
            return;
        }

        if (field) {
            field.addEventListener('input', update);
        }
    });

    if (fields.requiredPower) {
        fields.requiredPower.addEventListener('input', () => {
            if (fields.requiredPower.dataset.autoUpdating === 'true') {
                return;
            }

            requiredPowerManuallyEdited = fields.requiredPower.type !== 'hidden' && toNumber(fields.requiredPower.value) > 0;
        });
    }

    if (fields.startDate) {
        fields.startDate.addEventListener('change', fetchAmbientContext);
    }
    if (fields.endDate) {
        fields.endDate.addEventListener('change', fetchAmbientContext);
    }
    scaleButtons.forEach((button) => {
        button.addEventListener('click', () => setScale(button.dataset.simScaleBtn));
    });

    setScale('monthly');
    fetchAmbientContext();
})();
</script>

<script>
(() => {
    const root = document.querySelector('[data-location-quote]');

    if (!root) {
        return;
    }

    const endpointTemplate = @json($solarPriceUrlTemplate);
    const geoJsonUrl = '/maps/la_guajira_municipios.geojson';
    const municipalitySelect = root.querySelector('[data-location-municipality]');
    const locationTypeSelect = root.querySelector('[data-location-type]');
    const requiredPowerInput = root.querySelector('[data-required-power]');
    const latitudeInput = root.querySelector('[data-location-latitude]');
    const longitudeInput = root.querySelector('[data-location-longitude]');
    const moneyFormatter = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const numberFormatter = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 2 });
    const locationTypeLabels = @json($locationTypes);
    let municipalityLayer = null;
    let selectedLayer = null;

    const out = {
        municipality: root.querySelector('[data-price-municipality]'),
        zone: root.querySelector('[data-price-zone]'),
        locationType: root.querySelector('[data-price-location-type]'),
        base: root.querySelector('[data-price-base]'),
        factor: root.querySelector('[data-price-factor]'),
        final: root.querySelector('[data-price-final]'),
        power: root.querySelector('[data-price-power]'),
        estimated: root.querySelector('[data-price-estimated]'),
        message: root.querySelector('[data-price-message]'),
    };

    const currentOption = () => municipalitySelect.options[municipalitySelect.selectedIndex] ?? null;
    const selectedMunicipalityName = () => currentOption()?.dataset.name ?? '';
    const selectedMunicipalityId = () => municipalitySelect.value;
    const requiredPower = () => Number(String(requiredPowerInput.value || '').replace(',', '.'));

    const resetPrice = (message = 'Selecciona municipio, tipo de ubicacion y potencia para calcular.') => {
        out.municipality.textContent = selectedMunicipalityName() || '--';
        out.zone.textContent = currentOption()?.dataset.zone || '--';
        out.locationType.textContent = locationTypeLabels[locationTypeSelect.value] || '--';
        out.base.textContent = '--';
        out.factor.textContent = '--';
        out.final.textContent = '--';
        out.power.textContent = requiredPower() > 0 ? `${numberFormatter.format(requiredPower())} kW` : '--';
        out.estimated.textContent = '--';
        out.message.textContent = message;
    };

    const syncCoordinatesFromOption = () => {
        const option = currentOption();
        if (!option) {
            return;
        }
        if (!latitudeInput.value && option.dataset.latitude) {
            latitudeInput.value = option.dataset.latitude;
        }
        if (!longitudeInput.value && option.dataset.longitude) {
            longitudeInput.value = option.dataset.longitude;
        }
    };

    const updatePrice = async () => {
        if (!selectedMunicipalityId() || !(requiredPower() > 0)) {
            resetPrice();
            return;
        }

        syncCoordinatesFromOption();
        const url = new URL(endpointTemplate.replace('__MUNICIPALITY__', selectedMunicipalityId()), window.location.origin);
        url.searchParams.set('location_type', locationTypeSelect.value);
        url.searchParams.set('required_power_kw', requiredPower());

        try {
            const response = await fetch(url.toString(), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();
            out.municipality.textContent = payload.municipality_name;
            out.zone.textContent = payload.zone_name || currentOption()?.dataset.zone || '--';
            out.locationType.textContent = locationTypeLabels[payload.location_type] || payload.location_type;
            out.base.textContent = moneyFormatter.format(payload.base_price_per_kw);
            out.factor.textContent = Number(payload.logistic_factor).toFixed(2);
            out.final.textContent = moneyFormatter.format(payload.final_price_per_kw);
            out.power.textContent = `${numberFormatter.format(requiredPower())} kW`;
            out.estimated.textContent = moneyFormatter.format(payload.estimated_installation_cost);
            out.message.textContent = payload.notes || 'Precio calculado con la matriz municipal vigente.';
        } catch (_error) {
            resetPrice('No hay precio disponible para esa ubicacion.');
        }
    };

    const normalizeText = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, ' ')
        .trim()
        .toUpperCase();

    const findProperty = (properties, names) => {
        if (!properties) {
            return null;
        }

        for (const name of names) {
            if (properties[name] !== undefined && properties[name] !== null) {
                return properties[name];
            }
        }

        const lowerMap = Object.fromEntries(Object.keys(properties).map((key) => [key.toLowerCase(), key]));
        for (const name of names) {
            const key = lowerMap[name.toLowerCase()];
            if (key && properties[key] !== undefined && properties[key] !== null) {
                return properties[key];
            }
        }

        return null;
    };

    const municipalityNameFromFeature = (feature) => findProperty(feature?.properties, [
        'NOMBRE_MPI',
        'MPIO_CNMBR',
        'MUNICIPIO',
        'name',
        'nombre',
        'mpio_cnmbr',
    ]);

    const daneCodeFromFeature = (feature) => {
        const code = findProperty(feature?.properties, [
            'MPIO_CDPMP',
            'COD_DANE',
            'DANE',
            'DIVIPOLA',
            'mpio_cdpmp',
            'divipola',
        ]);

        return code === null ? '' : String(code).replace(/\.0$/, '').trim();
    };

    const findOptionByMunicipality = (name, daneCode = '') => Array.from(municipalitySelect.options).find((option) => {
        if (daneCode && option.dataset.daneCode === daneCode) {
            return true;
        }

        return normalizeText(option.dataset.name) === normalizeText(name);
    });

    const selectMunicipality = (name, daneCode = '') => {
        const option = findOptionByMunicipality(name, daneCode);
        if (!option) {
            out.message.textContent = `El municipio "${name || daneCode}" no existe en el selector de precios.`;
            return;
        }

        municipalitySelect.value = option.value;
        latitudeInput.value = option.dataset.latitude || latitudeInput.value;
        longitudeInput.value = option.dataset.longitude || longitudeInput.value;
        updatePrice();
    };

    const municipalityStyle = {
        color: '#7a6653',
        fillColor: '#e1a751',
        fillOpacity: 0.28,
        weight: 1,
    };

    const normalizeLayerStyle = (layer) => layer.setStyle(municipalityStyle);

    const highlightLayer = (layer) => {
        if (selectedLayer && selectedLayer !== layer) {
            normalizeLayerStyle(selectedLayer);
        }
        selectedLayer = layer;
        layer.setStyle({
            color: '#a85b1e',
            fillColor: '#c87427',
            fillOpacity: 0.56,
            weight: 3,
        });
        layer.bringToFront();
    };

    const loadLeaflet = () => new Promise((resolve, reject) => {
        if (window.L) {
            resolve(window.L);
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.onload = () => resolve(window.L);
        script.onerror = reject;
        document.head.appendChild(script);
    });

    const onEachMunicipality = (feature, layer) => {
        const name = municipalityNameFromFeature(feature);
        const daneCode = daneCodeFromFeature(feature);
        const label = name || `DANE ${daneCode}`;

        layer.bindTooltip(label, { sticky: true });
        layer.on('click', () => {
            highlightLayer(layer);
            selectMunicipality(name, daneCode);
        });
    };

    const initMap = async () => {
        try {
            const L = await loadLeaflet();
            const map = L.map('la-guajira-map', { scrollWheelZoom: false });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 12,
                attribution: '&copy; OpenStreetMap',
            }).addTo(map);

            const geoResponse = await fetch(geoJsonUrl);
            if (!geoResponse.ok) {
                throw new Error(`GeoJSON HTTP ${geoResponse.status}`);
            }

            const geoJson = await geoResponse.json();
            const detectedNames = (geoJson.features || [])
                .map((feature) => municipalityNameFromFeature(feature))
                .filter(Boolean);

            console.debug('Municipios detectados en GeoJSON de La Guajira:', detectedNames);

            municipalityLayer = L.geoJSON(geoJson, {
                style: municipalityStyle,
                onEachFeature: onEachMunicipality,
            }).addTo(map);
            map.fitBounds(municipalityLayer.getBounds());
        } catch (_error) {
            console.error(_error);
            out.message.textContent = 'No fue posible cargar los límites reales de los municipios de La Guajira. Verifique que el archivo public/maps/la_guajira_municipios.geojson exista y sea un GeoJSON válido.';
        }
    };

    municipalitySelect.addEventListener('change', () => {
        latitudeInput.value = '';
        longitudeInput.value = '';
        syncCoordinatesFromOption();
        if (municipalityLayer) {
            municipalityLayer.eachLayer((layer) => {
                const layerName = municipalityNameFromFeature(layer.feature);
                const layerCode = daneCodeFromFeature(layer.feature);
                const selectedCode = currentOption()?.dataset.daneCode || '';

                if (
                    normalizeText(layerName) === normalizeText(selectedMunicipalityName())
                    || (selectedCode && layerCode === selectedCode)
                ) {
                    highlightLayer(layer);
                }
            });
        }
        updatePrice();
    });
    locationTypeSelect.addEventListener('change', updatePrice);
    requiredPowerInput.addEventListener('input', updatePrice);

    resetPrice();
    updatePrice();
    initMap();
})();
</script>
