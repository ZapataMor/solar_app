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

    // Defaults for clients who do not know their technical parameters (ADR-0007).
    $systemSpec = \App\Domain\Solar\SystemSpecification::class;
    $defaults = $isCreating ? [
        'usable_area_percentage' => $systemSpec::DEFAULT_USABLE_AREA_PERCENTAGE,
        'panel_power_w' => $systemSpec::DEFAULT_PANEL_POWER_W,
        'panel_area_m2' => $systemSpec::DEFAULT_PANEL_AREA_M2,
        'system_losses_percentage' => $systemSpec::DEFAULT_SYSTEM_LOSSES_PERCENTAGE,
        'start_date' => now(config('app.display_timezone', config('app.timezone')))->toDateString(),
    ] : [];

    // Wizard stages and the fields each one owns, used to reopen the stage with server errors.
    $wizardSteps = [
        ['key' => 'project', 'label' => 'Tu proyecto', 'fields' => ['name', 'description']],
        ['key' => 'location', 'label' => 'Ubicación', 'fields' => ['municipality_id', 'location_type', 'latitude', 'longitude']],
        ['key' => 'consumption', 'label' => 'Consumo', 'fields' => ['consumption_mode', 'appliances', 'monthly_consumption_kwh', 'energy_rate_cop_kwh', 'required_power_kw']],
        ['key' => 'space', 'label' => 'Espacio disponible', 'fields' => ['available_area_m2', 'usable_area_percentage', 'panel_power_w', 'panel_area_m2', 'system_losses_percentage', 'start_date', 'end_date']],
        ['key' => 'summary', 'label' => 'Resumen', 'fields' => []],
    ];
    // "appliances" also owns its row errors (appliances.0.variant, …).
    $errorKeys = collect($errors->keys());
    $stepsWithErrors = collect($wizardSteps)
        ->keys()
        ->filter(fn (int $index) => $errorKeys->contains(fn (string $key) => collect($wizardSteps[$index]['fields'])
            ->contains(fn (string $field) => $key === $field || str_starts_with($key, $field.'.'))))
        ->values();

    // Consumption by appliances (ADR-0002).
    $applianceCatalog = (new \App\Domain\Consumption\ApplianceCatalog)->all();
    $storedAppliances = $solarProject?->appliances ?? collect();
    $consumptionMode = old('consumption_mode', ($isCreating || $storedAppliances->isNotEmpty()) ? 'appliances' : 'bill');
    $initialLoads = collect(is_array(old('appliances')) ? old('appliances') : $storedAppliances->map(fn ($appliance) => [
        'key' => $appliance->appliance_key,
        'variant' => $appliance->variant_key,
        'quantity' => $appliance->quantity,
        'hours_per_day' => $appliance->hours_per_day,
    ])->all())
        ->filter(fn ($row) => is_array($row) && isset($applianceCatalog[$row['key'] ?? '']))
        ->map(fn (array $row) => [
            'key' => (string) $row['key'],
            'variant' => (string) ($row['variant'] ?? ''),
            'quantity' => (int) ($row['quantity'] ?? 1),
            'hours_per_day' => (float) ($row['hours_per_day'] ?? 0),
        ])
        ->values();
    $initialStep = $stepsWithErrors->first() ?? 0;
    $furthestStep = ($isCreating && ! $errors->any()) ? 0 : count($wizardSteps) - 1;
    $advancedOpen = $errors->hasAny(['usable_area_percentage', 'panel_power_w', 'panel_area_m2', 'system_losses_percentage', 'start_date', 'end_date']);
    $coordinatesOpen = $errors->hasAny(['latitude', 'longitude']);
@endphp

@if ($errors->any())
    <div class="solar-alert solar-alert-danger">
        <p class="font-semibold">Revisa las etapas marcadas en rojo:</p>
        <ul class="mt-1 list-disc space-y-1 ps-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form
    method="POST"
    action="{{ $action }}"
    class="solar-page solar-wizard"
    data-project-wizard
    data-wizard-initial="{{ $initialStep }}"
    data-wizard-furthest="{{ $furthestStep }}"
>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <nav class="solar-wizard-nav" aria-label="Etapas del proyecto" data-wizard-nav hidden>
        <ol class="solar-wizard-steps">
            @foreach ($wizardSteps as $index => $step)
                <li>
                    <button
                        type="button"
                        class="solar-wizard-step"
                        data-wizard-goto="{{ $index }}"
                        @if ($stepsWithErrors->contains($index)) data-has-error @endif
                    >
                        <span class="solar-wizard-step-number">{{ $index + 1 }}</span>
                        <span class="solar-wizard-step-label">{{ $step['label'] }}</span>
                    </button>
                </li>
            @endforeach
        </ol>
        <p class="solar-wizard-progress" data-wizard-progress aria-live="polite"></p>
    </nav>

    {{-- 1 · Tu proyecto --}}
    <section class="solar-card-strong" data-wizard-step="project" data-wizard-label="Tu proyecto">
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Etapa 1 · Tu proyecto</p>
                <h2 class="solar-wizard-heading text-2xl text-[color:var(--solar-text)]" tabindex="-1">¿Cómo se llama tu proyecto?</h2>
                <p class="solar-subtitle mt-2">Un nombre que te ayude a reconocerlo, por ejemplo «Casa Riohacha» o «Tienda del barrio».</p>
            </div>
        </div>

        <div class="solar-form-grid mt-6">
            <label class="solar-field">
                <span class="solar-field-label">Nombre del proyecto</span>
                <input
                    name="name"
                    value="{{ old('name', $solarProject?->name) }}"
                    required
                    maxlength="255"
                    class="solar-input"
                >
            </label>

            <label class="solar-field">
                <span class="solar-field-label">Descripción <span class="text-[color:var(--solar-text-muted)]">(opcional)</span></span>
                <textarea
                    name="description"
                    rows="3"
                    class="solar-textarea"
                >{{ old('description', $solarProject?->description) }}</textarea>
            </label>
        </div>
    </section>

    {{-- 2 · Ubicación --}}
    <section class="solar-card" data-location-quote data-wizard-step="location" data-wizard-label="Ubicación">
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Etapa 2 · Ubicación</p>
                <h2 class="solar-wizard-heading text-2xl text-[color:var(--solar-text)]" tabindex="-1">¿Dónde se instalaría?</h2>
                <p class="solar-subtitle mt-2">Elige tu municipio en el listado o haz clic en el mapa. El precio de instalación se ajusta a cada municipio.</p>
            </div>
            <span class="solar-pill solar-pill-success">La Guajira</span>
        </div>

        <style>
            .solar-location-layout { display: grid; gap: 1rem; margin-top: 1.5rem; }
            @media (min-width: 1024px) { .solar-location-layout { grid-template-columns: minmax(0, 1.5fr) minmax(20rem, .8fr); } }
            .solar-location-map { min-height: 26rem; overflow: hidden; border: 1px solid var(--solar-border); border-radius: 1rem; background: var(--solar-surface-muted); }
            /* The browser draws a focus box around a clicked SVG polygon (and the map container); selection is shown with color instead.
               Keyboard focus on the map keeps its ring through :focus-visible. */
            .solar-location-map path.leaflet-interactive:focus { outline: none; }
            .solar-location-map.leaflet-container:focus:not(:focus-visible) { outline: none; }
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
                <span class="solar-field-label">Tipo de ubicación</span>
                <select name="location_type" required class="solar-input" data-location-type>
                    @foreach ($locationTypes as $value => $label)
                        <option value="{{ $value }}" @selected($selectedLocationType === $value)>{{ $label }}</option>
                    @endforeach
                </select>
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

        <details class="solar-wizard-advanced mt-4" @if ($coordinatesOpen) open @endif>
            <summary>Ajustar coordenadas manualmente</summary>
            <div class="solar-wizard-advanced-body solar-form-grid md:grid-cols-2">
                <label class="solar-field">
                    <span class="solar-field-label">Latitud</span>
                    <input type="number" step="0.000001" name="latitude" value="{{ old('latitude', $solarProject?->latitude) }}" class="solar-input" data-location-latitude>
                </label>

                <label class="solar-field">
                    <span class="solar-field-label">Longitud</span>
                    <input type="number" step="0.000001" name="longitude" value="{{ old('longitude', $solarProject?->longitude) }}" class="solar-input" data-location-longitude>
                </label>
            </div>
        </details>
    </section>

    {{-- 3 · Consumo (ADR-0002: por equipos, o con el kWh del recibo) --}}
    <section
        class="solar-card"
        data-wizard-step="consumption"
        data-wizard-label="Consumo"
        data-consumption-picker
    >
        @include('solar-projects.partials.appliance-icons')
        <script type="application/json" data-appliance-catalog>@json($applianceCatalog)</script>
        <script type="application/json" data-appliance-initial>@json($initialLoads)</script>

        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Etapa 3 · Consumo</p>
                <h2 class="solar-wizard-heading text-2xl text-[color:var(--solar-text)]" tabindex="-1">¿Qué quieres alimentar con energía solar?</h2>
                <p class="solar-subtitle mt-2">Elige tus equipos y cuánto los usas; calculamos tu consumo por ti. Si ya sabes tu consumo en kWh, también puedes escribirlo.</p>
            </div>
        </div>

        <input type="hidden" name="consumption_mode" value="{{ $consumptionMode }}" data-consumption-mode>

        <div class="solar-consumption-modes mt-6" role="group" aria-label="Cómo quieres indicar tu consumo">
            <button type="button" class="solar-consumption-mode" data-consumption-mode-btn="appliances" aria-pressed="{{ $consumptionMode === 'appliances' ? 'true' : 'false' }}">
                <strong>Con mis equipos</strong>
                <span>Recomendado · eliges neveras, aires, abanicos…</span>
            </button>
            <button type="button" class="solar-consumption-mode" data-consumption-mode-btn="bill" aria-pressed="{{ $consumptionMode === 'bill' ? 'true' : 'false' }}">
                <strong>Ya sé mi consumo</strong>
                <span>Escribo los kWh de mi recibo</span>
            </button>
        </div>

        <div class="mt-6" data-consumption-panel="appliances" @if ($consumptionMode !== 'appliances') hidden @endif>
            <div class="solar-appliance-toolbar">
                <div class="solar-appliance-segments" role="group" aria-label="Tipo de lugar">
                    <button type="button" class="solar-appliance-segment" data-appliance-segment="home" aria-pressed="true">Hogar</button>
                    <button type="button" class="solar-appliance-segment" data-appliance-segment="business" aria-pressed="false">Negocio</button>
                </div>
                <p class="solar-appliance-help">Toca un equipo para agregarlo. Puedes repetirlo, por ejemplo, dos aires de distinto tamaño.</p>
            </div>

            <div class="solar-appliance-grid" data-appliance-grid>
                @foreach ($applianceCatalog as $applianceKey => $appliance)
                    <button
                        type="button"
                        class="solar-appliance-card"
                        data-add-appliance="{{ $applianceKey }}"
                        data-segments="{{ implode(' ', $appliance['segments']) }}"
                        aria-label="Agregar {{ $appliance['label'] }}"
                    >
                        <svg viewBox="0 0 24 24" class="solar-appliance-card-icon" aria-hidden="true"><use href="#appliance-{{ $appliance['icon'] }}"></use></svg>
                        <span class="solar-appliance-card-label">{{ $appliance['label'] }}</span>
                        <span class="solar-appliance-card-count" data-appliance-count="{{ $applianceKey }}" hidden></span>
                    </button>
                @endforeach
            </div>

            <div class="solar-appliance-selection">
                <h3 class="solar-appliance-selection-title">Tus equipos</h3>
                <p class="solar-appliance-empty" data-appliance-empty>Aún no has agregado equipos.</p>
                <ul class="solar-appliance-loads" data-appliance-loads></ul>
                <p class="solar-appliance-error" data-appliance-error role="alert" hidden></p>
                <div data-appliance-hidden></div>
            </div>

            <div class="solar-appliance-total" aria-live="polite">
                <div>
                    <span class="solar-appliance-total-label">Consumo estimado</span>
                    <strong class="solar-appliance-total-value" data-appliance-total>0 kWh/mes</strong>
                </div>
                <span class="solar-appliance-total-daily" data-appliance-total-daily>≈ 0 kWh al día</span>
            </div>
            <p class="mt-2 text-xs text-[color:var(--solar-text-muted)]">Usamos consumos de referencia por tipo y tamaño de equipo. El consumo real cambia con la marca, la antigüedad y el uso.</p>
        </div>

        <div class="solar-form-grid mt-6 md:grid-cols-2" data-consumption-panel="bill" @if ($consumptionMode !== 'bill') hidden @endif>
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
                    @if ($consumptionMode === 'appliances') readonly @endif
                >
                <span class="text-xs text-[color:var(--solar-text-muted)]">
                    El sistema derivara automaticamente consumo diario aproximado y consumo anual.
                </span>
            </label>
        </div>

        <div class="solar-form-grid mt-6 md:grid-cols-2">
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
                <span class="text-xs text-[color:var(--solar-text-muted)]">Lo que pagas por cada kWh; aparece en tu recibo.</span>
            </label>

            <div class="flex items-end">
                <button type="button" class="solar-button-ghost solar-energy-guide-trigger" data-energy-guide-open>
                    ¿Dónde encuentro estos datos en mi recibo?
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

    {{-- 4 · Espacio disponible --}}
    <section class="solar-card" data-wizard-step="space" data-wizard-label="Espacio disponible">
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Etapa 4 · Espacio disponible</p>
                <h2 class="solar-wizard-heading text-2xl text-[color:var(--solar-text)]" tabindex="-1">¿Cuánto espacio tienes para los paneles?</h2>
                <p class="solar-subtitle mt-2">El área del techo o terreno donde podrían ir. Si no la sabes exacta, una aproximación sirve para empezar.</p>
            </div>
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
        </div>

        <details class="solar-wizard-advanced mt-6" @if ($advancedOpen) open @endif>
            <summary>Parámetros avanzados</summary>
            <div class="solar-wizard-advanced-body">
                <p class="solar-subtitle">Valores técnicos con los que estimamos el sistema. Ya traen valores típicos; un instalador puede ajustarlos.</p>

                <div class="solar-form-grid mt-4 md:grid-cols-2">
                    <label class="solar-field">
                        <span class="solar-field-label">Area utilizable %</span>
                        <input
                            type="number"
                            step="0.01"
                            min="1"
                            max="100"
                            name="usable_area_percentage"
                            value="{{ old('usable_area_percentage', $technicalParameter?->usable_area_percentage ?? ($defaults['usable_area_percentage'] ?? null)) }}"
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
                            value="{{ old('panel_power_w', $technicalParameter?->panel_power_w ?? ($defaults['panel_power_w'] ?? null)) }}"
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
                            value="{{ old('panel_area_m2', $technicalParameter?->panel_area_m2 ?? ($defaults['panel_area_m2'] ?? null)) }}"
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
                            value="{{ old('system_losses_percentage', $technicalParameter?->system_losses_percentage ?? ($defaults['system_losses_percentage'] ?? null)) }}"
                            required
                            class="solar-input"
                        >
                    </label>

                    <label class="solar-field">
                        <span class="solar-field-label">Fecha inicial del análisis</span>
                        <input
                            type="date"
                            name="start_date"
                            value="{{ old('start_date', $solarProject?->start_date?->format('Y-m-d') ?? ($defaults['start_date'] ?? null)) }}"
                            required
                            class="solar-input"
                        >
                    </label>

                    @unless ($isCreating)
                        <label class="solar-field">
                            <span class="solar-field-label">Fecha final del análisis</span>
                            <input
                                type="date"
                                name="end_date"
                                value="{{ old('end_date', $solarProject?->end_date?->format('Y-m-d')) }}"
                                required
                                class="solar-input"
                            >
                        </label>
                    @endunless
                </div>
            </div>
        </details>
    </section>

    {{-- 5 · Resumen y estimación --}}
    <section class="solar-card" data-wizard-step="summary" data-wizard-label="Resumen">
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Etapa 5 · Resumen</p>
                <h2 class="solar-wizard-heading text-2xl text-[color:var(--solar-text)]" tabindex="-1">Revisa tu proyecto</h2>
                <p class="solar-subtitle mt-2">Si algo no está bien, edítalo antes de guardar.</p>
            </div>
        </div>

        <dl class="solar-wizard-summary mt-4">
            <div class="solar-wizard-summary-row">
                <dt>Proyecto</dt>
                <dd><span data-summary="name">—</span> <button type="button" class="solar-wizard-edit" data-wizard-edit="0">Editar</button></dd>
            </div>
            <div class="solar-wizard-summary-row">
                <dt>Ubicación</dt>
                <dd><span data-summary="location">—</span> <button type="button" class="solar-wizard-edit" data-wizard-edit="1">Editar</button></dd>
            </div>
            <div class="solar-wizard-summary-row">
                <dt>Consumo y tarifa</dt>
                <dd><span data-summary="consumption">—</span> <button type="button" class="solar-wizard-edit" data-wizard-edit="2">Editar</button></dd>
            </div>
            <div class="solar-wizard-summary-row">
                <dt>Área disponible</dt>
                <dd><span data-summary="area">—</span> <button type="button" class="solar-wizard-edit" data-wizard-edit="3">Editar</button></dd>
            </div>
        </dl>
    </section>

    <section class="solar-card" data-project-simulator data-wizard-step="summary" data-wizard-companion>
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

    <div class="solar-wizard-actions">
        <a href="{{ $isCreating ? route('solar-projects.index') : route('solar-projects.show', $solarProject) }}" class="solar-button-ghost">
            Cancelar
        </a>

        <div class="solar-wizard-actions-end">
            <button type="button" class="solar-button-ghost" data-wizard-prev hidden>Anterior</button>
            <button type="button" class="solar-button" data-wizard-next hidden>Siguiente</button>
            <button type="submit" class="solar-button" data-wizard-submit>
                {{ $buttonText }}
            </button>
        </div>
    </div>
</form>

<script>
(() => {
    // Project wizard (ADR-0007): client-side stages over a single form.
    const form = document.querySelector('[data-project-wizard]');

    if (!form) {
        return;
    }

    const stepKeys = [...new Set(Array.from(form.querySelectorAll('[data-wizard-step]')).map((el) => el.dataset.wizardStep))];
    const sectionsOf = (index) => Array.from(form.querySelectorAll(`[data-wizard-step="${stepKeys[index]}"]`));
    const labelOf = (index) => sectionsOf(index)[0]?.dataset.wizardLabel ?? '';
    const nav = form.querySelector('[data-wizard-nav]');
    const indicators = Array.from(form.querySelectorAll('[data-wizard-goto]'));
    const progress = form.querySelector('[data-wizard-progress]');
    const prevButton = form.querySelector('[data-wizard-prev]');
    const nextButton = form.querySelector('[data-wizard-next]');
    const submitButton = form.querySelector('[data-wizard-submit]');
    const lastStep = stepKeys.length - 1;
    let current = Number(form.dataset.wizardInitial ?? 0);
    let furthest = Math.max(current, Number(form.dataset.wizardFurthest ?? 0));

    // Fields inside a hidden panel of a stage (e.g. the unused consumption mode) are not validated;
    // the stage section itself may be hidden, that one does not count.
    const isInHiddenPanel = (field) => {
        const hiddenAncestor = field.parentElement?.closest('[hidden]');
        return Boolean(hiddenAncestor) && ! hiddenAncestor.matches('[data-wizard-step]');
    };

    const fieldsOf = (index) => sectionsOf(index)
        .flatMap((section) => Array.from(section.querySelectorAll('input, select, textarea')))
        .filter((field) => field.willValidate && ! isInHiddenPanel(field));

    // Stages can register extra checks: form.wizardValidators[stepKey] = () => null | reportFn.
    form.wizardValidators = form.wizardValidators ?? {};

    const firstInvalidUntil = (index) => {
        for (let step = 0; step <= index; step++) {
            const field = fieldsOf(step).find((candidate) => !candidate.checkValidity());

            if (field) {
                return { step, field };
            }

            const report = form.wizardValidators[stepKeys[step]]?.();

            if (report) {
                return { step, report };
            }
        }

        return null;
    };

    const fieldValue = (name) => form.elements[name]?.value?.trim() ?? '';
    const selectedText = (name) => {
        const select = form.elements[name];
        return select && select.selectedIndex >= 0 ? select.options[select.selectedIndex].text.trim() : '';
    };
    const numberFormatter = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 2 });
    const formatNumber = (value) => (value === '' ? '' : numberFormatter.format(Number(value)));

    const fillSummary = () => {
        const set = (key, text) => {
            const target = form.querySelector(`[data-summary="${key}"]`);
            if (target) {
                target.textContent = text || '—';
            }
        };
        const municipality = fieldValue('municipality_id') ? selectedText('municipality_id') : '';
        const consumption = fieldValue('monthly_consumption_kwh');
        const rate = fieldValue('energy_rate_cop_kwh');
        const area = fieldValue('available_area_m2');

        set('name', fieldValue('name'));
        set('location', municipality ? `${municipality} · ${selectedText('location_type')}` : '');
        const applianceCount = form.querySelectorAll('[data-appliance-load]').length;
        const consumptionSource = fieldValue('consumption_mode') === 'appliances'
            ? `${applianceCount} ${applianceCount === 1 ? 'equipo' : 'equipos'} · `
            : '';
        set('consumption', consumption && rate ? `${consumptionSource}${formatNumber(consumption)} kWh/mes · ${formatNumber(rate)} COP/kWh` : '');
        set('area', area ? `${formatNumber(area)} m²` : '');
    };

    const show = (index, { focus = true } = {}) => {
        current = Math.max(0, Math.min(index, lastStep));
        furthest = Math.max(furthest, current);

        stepKeys.forEach((_key, step) => sectionsOf(step).forEach((section) => {
            section.hidden = step !== current;
        }));

        indicators.forEach((indicator, step) => {
            if (step === current) {
                indicator.setAttribute('aria-current', 'step');
            } else {
                indicator.removeAttribute('aria-current');
            }
            indicator.dataset.state = step === current ? 'current' : (step <= furthest ? 'done' : 'todo');
            indicator.disabled = step > furthest;
        });

        prevButton.hidden = current === 0;
        nextButton.hidden = current === lastStep;
        submitButton.hidden = current !== lastStep;
        progress.textContent = `Paso ${current + 1} de ${stepKeys.length} · ${labelOf(current)}`;

        if (current === lastStep) {
            fillSummary();
        }

        form.dispatchEvent(new CustomEvent('wizard:step-shown', { detail: { index: current, key: stepKeys[current] } }));

        if (focus) {
            nav.scrollIntoView({ behavior: 'smooth', block: 'start' });
            sectionsOf(current)[0]?.querySelector('.solar-wizard-heading')?.focus({ preventScroll: true });
        }
    };

    const revealInvalid = ({ step, field, report }) => {
        show(step);

        if (report) {
            report();
            return;
        }

        const details = field.closest('details');
        if (details) {
            details.open = true;
        }
        field.reportValidity();
    };

    const goTo = (target) => {
        if (target > current) {
            const invalid = firstInvalidUntil(target - 1);
            if (invalid) {
                revealInvalid(invalid);
                return;
            }
        }

        show(target);
    };

    nextButton.addEventListener('click', () => goTo(current + 1));
    prevButton.addEventListener('click', () => show(current - 1));
    indicators.forEach((indicator, step) => indicator.addEventListener('click', () => goTo(step)));
    form.querySelectorAll('[data-wizard-edit]').forEach((button) => {
        button.addEventListener('click', () => show(Number(button.dataset.wizardEdit)));
    });

    // Enter on an intermediate stage advances instead of submitting the whole form.
    form.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' || current === lastStep || event.target.tagName === 'TEXTAREA' || event.target.type === 'button' || event.target.type === 'submit') {
            return;
        }

        event.preventDefault();
        goTo(current + 1);
    });

    // Validate every stage on submit and open the first one with a problem.
    form.noValidate = true;
    form.addEventListener('submit', (event) => {
        const invalid = firstInvalidUntil(lastStep);

        if (invalid) {
            event.preventDefault();
            revealInvalid(invalid);
        }
    });

    nav.hidden = false;
    form.classList.add('is-wizard');
    show(current, { focus: false });
})();
</script>

<script>
(() => {
    // Appliance picker (ADR-0002): builds the consumption from appliances, variants, quantity and hours.
    // The server recomputes the same total from the catalog; this one only drives the live preview.
    const root = document.querySelector('[data-consumption-picker]');

    if (!root) {
        return;
    }

    const form = root.closest('form');
    const catalog = JSON.parse(root.querySelector('[data-appliance-catalog]').textContent);
    const initialLoads = JSON.parse(root.querySelector('[data-appliance-initial]').textContent);
    const modeInput = root.querySelector('[data-consumption-mode]');
    const monthlyInput = form.elements.monthly_consumption_kwh;
    const list = root.querySelector('[data-appliance-loads]');
    const hiddenBox = root.querySelector('[data-appliance-hidden]');
    const emptyState = root.querySelector('[data-appliance-empty]');
    const errorBox = root.querySelector('[data-appliance-error]');
    const totalOut = root.querySelector('[data-appliance-total]');
    const dailyOut = root.querySelector('[data-appliance-total-daily]');
    const cards = Array.from(root.querySelectorAll('[data-add-appliance]'));
    const kwhFormatter = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 1 });
    const DAYS_PER_MONTH = 30;
    let loads = [];
    let sequence = 0;

    const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
    const icon = (name, className = '') => `<svg viewBox="0 0 24 24" class="${className}" aria-hidden="true"><use href="#appliance-${escapeHtml(name)}"></use></svg>`;

    const defaultChoices = (key) => {
        const appliance = catalog[key];
        return appliance.groups.length ? String(appliance.default_variant).split('.') : [];
    };
    const choicesFromVariant = (key, variant) => {
        const appliance = catalog[key];
        const parts = String(variant ?? '').split('.');
        const defaults = defaultChoices(key);
        return appliance.groups.map((group, index) => (group.choices.some((choice) => choice.key === parts[index]) ? parts[index] : defaults[index]));
    };
    const variantOf = (load) => (catalog[load.key].groups.length ? load.choices.join('.') : 'default');
    const hoursPerDayOf = (load) => (catalog[load.key].usage === 'always' ? 24 : Math.min(24, Math.max(0, load.hoursPerDay)));
    const kwhOf = (load) => (Number(catalog[load.key].watts[variantOf(load)] ?? 0) * load.quantity * hoursPerDayOf(load) * DAYS_PER_MONTH) / 1000;
    const totalKwh = () => loads.reduce((sum, load) => sum + kwhOf(load), 0);

    // The drawing follows the chosen variant when the variant has its own (e.g. vertical freezer).
    const iconOf = (load) => {
        const appliance = catalog[load.key];
        const group = appliance.groups[0];
        const choice = group?.choices.find((candidate) => candidate.key === load.choices[0]);
        return choice?.icon ?? appliance.icon;
    };

    const shownHours = (load) => {
        const usage = catalog[load.key].usage;
        const hours = usage === 'week' ? load.hoursPerDay * 7 : load.hoursPerDay;
        return Math.round(hours * 100) / 100;
    };

    const rowHtml = (load) => {
        const appliance = catalog[load.key];
        const groups = appliance.groups.map((group, groupIndex) => `
            <div class="solar-appliance-group">
                <span class="solar-appliance-group-label">${escapeHtml(group.label)}</span>
                <div class="solar-appliance-chips" role="radiogroup" aria-label="${escapeHtml(group.label)}">
                    ${group.choices.map((choice) => `
                        <button type="button" class="solar-appliance-chip" role="radio"
                            aria-checked="${load.choices[groupIndex] === choice.key}"
                            data-group="${groupIndex}" data-choice="${escapeHtml(choice.key)}">
                            ${choice.icon || choice.scale ? icon(choice.icon ?? appliance.icon, `solar-appliance-chip-icon solar-appliance-scale-${choice.scale ?? 2}`) : ''}
                            <span>${escapeHtml(choice.label)}</span>
                        </button>`).join('')}
                </div>
            </div>`).join('');

        const usage = appliance.usage === 'always'
            ? '<p class="solar-appliance-always">Encendido las 24 horas</p>'
            : `<label class="solar-appliance-field">
                   <span>${appliance.usage === 'week' ? 'Horas a la semana' : 'Horas al día'}</span>
                   <input type="number" class="solar-input" min="0" max="${appliance.usage === 'week' ? 168 : 24}" step="0.5"
                       value="${shownHours(load)}" required data-hours>
               </label>`;

        return `
            <span class="solar-appliance-load-icon" data-load-icon>${icon(iconOf(load))}</span>
            <div class="solar-appliance-load-body">
                <div class="solar-appliance-load-head">
                    <strong>${escapeHtml(appliance.label)}</strong>
                    <button type="button" class="solar-appliance-remove" data-remove aria-label="Quitar ${escapeHtml(appliance.label)}">Quitar</button>
                </div>
                ${appliance.hint ? `<p class="solar-appliance-hint">${escapeHtml(appliance.hint)}</p>` : ''}
                ${groups}
                <div class="solar-appliance-controls">
                    <div class="solar-appliance-field">
                        <span>Cantidad</span>
                        <div class="solar-appliance-stepper">
                            <button type="button" data-step-quantity="-1" aria-label="Uno menos">−</button>
                            <input type="number" min="1" max="100" step="1" value="${load.quantity}" required data-quantity aria-label="Cantidad de ${escapeHtml(appliance.label)}">
                            <button type="button" data-step-quantity="1" aria-label="Uno más">+</button>
                        </div>
                    </div>
                    ${usage}
                </div>
            </div>
            <div class="solar-appliance-load-kwh"><strong data-load-kwh>0</strong><span>kWh/mes</span></div>`;
    };

    const findLoad = (element) => {
        const row = element.closest('[data-appliance-load]');
        return row ? { row, load: loads.find((candidate) => candidate.id === Number(row.dataset.applianceLoad)) } : {};
    };

    const renderRow = (load, row = document.createElement('li')) => {
        row.className = 'solar-appliance-load';
        row.dataset.applianceLoad = String(load.id);
        row.innerHTML = rowHtml(load);
        return row;
    };

    const writeHiddenInputs = () => {
        hiddenBox.innerHTML = modeInput.value !== 'appliances' ? '' : loads.map((load, index) => [
            ['key', load.key],
            ['variant', variantOf(load)],
            ['quantity', load.quantity],
            ['hours_per_day', Math.round(hoursPerDayOf(load) * 100) / 100],
        ].map(([field, value]) => `<input type="hidden" name="appliances[${index}][${field}]" value="${escapeHtml(value)}">`).join('')).join('');
    };

    const refresh = () => {
        loads.forEach((load) => {
            const row = list.querySelector(`[data-appliance-load="${load.id}"]`);
            row?.querySelector('[data-load-kwh]')?.replaceChildren(kwhFormatter.format(kwhOf(load)));
        });

        const total = totalKwh();
        totalOut.textContent = `${kwhFormatter.format(total)} kWh/mes`;
        dailyOut.textContent = `≈ ${kwhFormatter.format(total / DAYS_PER_MONTH)} kWh al día`;
        emptyState.hidden = loads.length > 0;

        cards.forEach((card) => {
            const count = loads.filter((load) => load.key === card.dataset.addAppliance).reduce((sum, load) => sum + load.quantity, 0);
            const badge = card.querySelector('[data-appliance-count]');
            badge.hidden = count === 0;
            badge.textContent = count > 0 ? `×${count}` : '';
            card.classList.toggle('is-selected', count > 0);
        });

        if (loads.length > 0 && total > 0) {
            errorBox.hidden = true;
        }

        if (modeInput.value === 'appliances') {
            monthlyInput.value = total > 0 ? total.toFixed(2) : '';
            monthlyInput.dispatchEvent(new Event('input', { bubbles: true }));
        }

        writeHiddenInputs();
    };

    const addLoad = (key, { variant = null, quantity = null, hoursPerDay = null } = {}) => {
        const appliance = catalog[key];
        const defaultHoursPerDay = appliance.usage === 'week' ? appliance.default_hours / 7 : appliance.default_hours;
        const load = {
            id: ++sequence,
            key,
            choices: variant ? choicesFromVariant(key, variant) : defaultChoices(key),
            quantity: Math.max(1, Math.min(100, Number(quantity ?? appliance.default_quantity) || 1)),
            hoursPerDay: hoursPerDay ?? defaultHoursPerDay,
        };
        loads.push(load);
        list.append(renderRow(load));
        return load;
    };

    // Catalog cards
    cards.forEach((card) => card.addEventListener('click', () => {
        const load = addLoad(card.dataset.addAppliance);
        refresh();
        const row = list.querySelector(`[data-appliance-load="${load.id}"]`);
        row.classList.add('is-new');
        row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        window.setTimeout(() => row.classList.remove('is-new'), 1200);
    }));

    // Row interactions (delegated)
    list.addEventListener('click', (event) => {
        const { row, load } = findLoad(event.target);
        if (!load) {
            return;
        }

        const chip = event.target.closest('[data-choice]');
        if (chip) {
            load.choices[Number(chip.dataset.group)] = chip.dataset.choice;
            renderRow(load, row);
            refresh();
            row.querySelector(`[data-group="${chip.dataset.group}"][data-choice="${CSS.escape(chip.dataset.choice)}"]`)?.focus();
            return;
        }

        const stepButton = event.target.closest('[data-step-quantity]');
        if (stepButton) {
            load.quantity = Math.max(1, Math.min(100, load.quantity + Number(stepButton.dataset.stepQuantity)));
            row.querySelector('[data-quantity]').value = load.quantity;
            refresh();
            return;
        }

        if (event.target.closest('[data-remove]')) {
            loads = loads.filter((candidate) => candidate !== load);
            row.remove();
            refresh();
        }
    });

    list.addEventListener('input', (event) => {
        const { load } = findLoad(event.target);
        if (!load) {
            return;
        }

        if (event.target.matches('[data-quantity]')) {
            const quantity = Math.round(Number(event.target.value));
            load.quantity = Number.isFinite(quantity) && quantity >= 1 ? Math.min(100, quantity) : 1;
        }

        if (event.target.matches('[data-hours]')) {
            const hours = Number(String(event.target.value).replace(',', '.'));
            const perDay = catalog[load.key].usage === 'week' ? hours / 7 : hours;
            load.hoursPerDay = Number.isFinite(perDay) ? Math.max(0, Math.min(24, perDay)) : 0;
        }

        refresh();
    });

    // Home / business filter
    const segmentButtons = Array.from(root.querySelectorAll('[data-appliance-segment]'));
    const setSegment = (segment) => {
        segmentButtons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.applianceSegment === segment)));
        cards.forEach((card) => {
            card.hidden = !card.dataset.segments.split(' ').includes(segment);
        });
    };
    segmentButtons.forEach((button) => button.addEventListener('click', () => setSegment(button.dataset.applianceSegment)));

    // Appliances vs. bill mode
    const modeButtons = Array.from(root.querySelectorAll('[data-consumption-mode-btn]'));
    const setMode = (mode) => {
        modeInput.value = mode;
        modeButtons.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.consumptionModeBtn === mode)));
        root.querySelectorAll('[data-consumption-panel]').forEach((panel) => {
            panel.hidden = panel.dataset.consumptionPanel !== mode;
        });
        // In appliance mode the kWh field is filled from the list, so it is not validated as user input.
        monthlyInput.readOnly = mode === 'appliances';
        if (mode === 'appliances') {
            errorBox.hidden = true;
        }
        refresh();
    };
    modeButtons.forEach((button) => button.addEventListener('click', () => setMode(button.dataset.consumptionModeBtn)));

    // Wizard check: appliance mode needs at least one appliance with some consumption.
    form.wizardValidators = form.wizardValidators ?? {};
    form.wizardValidators.consumption = () => {
        if (modeInput.value !== 'appliances' || (loads.length > 0 && totalKwh() > 0)) {
            return null;
        }

        return () => {
            errorBox.textContent = loads.length === 0
                ? 'Agrega al menos un equipo para calcular tu consumo.'
                : 'Tus equipos suman 0 kWh: revisa las horas de uso.';
            errorBox.hidden = false;
            errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        };
    };

    // Initial state (edit, or old input after a validation error)
    initialLoads.forEach((row) => addLoad(row.key, { variant: row.variant, quantity: row.quantity, hoursPerDay: Number(row.hours_per_day) }));
    const onlyBusiness = loads.some((load) => !catalog[load.key].segments.includes('home'));
    setSegment(onlyBusiness ? 'business' : 'home');
    setMode(modeInput.value === 'bill' ? 'bill' : 'appliances');
})();
</script>


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
    let leafletMap = null;

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

    // Hover lights the municipality up in a brighter gold, distinct from the burnt-orange selection.
    const hoverStyle = {
        color: '#f0a43a',
        fillColor: '#ffd166',
        fillOpacity: 0.6,
        weight: 2,
    };

    const selectedStyle = {
        color: '#a85b1e',
        fillColor: '#c87427',
        fillOpacity: 0.56,
        weight: 3,
    };

    const normalizeLayerStyle = (layer) => layer.setStyle(municipalityStyle);

    // Leaflet's bringToFront() re-inserts the SVG path in the DOM. Doing that to the path under the
    // cursor makes the browser lose track of it: mouseout never arrives (hover sticks) and the next
    // click lands on the container instead of the municipality. So the path under the cursor is
    // never moved; a clicked municipality is brought to front once the cursor leaves it.
    let selectedNeedsFront = false;

    const highlightLayer = (layer, { underCursor = false } = {}) => {
        if (selectedLayer && selectedLayer !== layer) {
            normalizeLayerStyle(selectedLayer);
        }
        selectedLayer = layer;
        layer.setStyle(selectedStyle);

        if (underCursor) {
            selectedNeedsFront = true;
        } else {
            selectedNeedsFront = false;
            layer.bringToFront();
        }
    };

    const hoverLayer = (layer) => {
        if (layer !== selectedLayer) {
            layer.setStyle(hoverStyle);
        }
    };

    const unhoverLayer = (layer) => {
        if (layer !== selectedLayer) {
            normalizeLayerStyle(layer);
            return;
        }

        if (selectedNeedsFront) {
            selectedNeedsFront = false;
            layer.bringToFront();
        }
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
        layer.on('mouseover', () => hoverLayer(layer));
        layer.on('mouseout', () => unhoverLayer(layer));
        layer.on('click', () => {
            highlightLayer(layer, { underCursor: true });
            selectMunicipality(name, daneCode);
        });
    };

    const initMap = async () => {
        try {
            const L = await loadLeaflet();
            const map = L.map('la-guajira-map', { scrollWheelZoom: false });
            leafletMap = map;
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

    // Leaflet cannot measure a hidden container: redraw when the wizard shows this stage.
    root.closest('form')?.addEventListener('wizard:step-shown', (event) => {
        if (event.detail.key !== 'location' || !leafletMap) {
            return;
        }

        leafletMap.invalidateSize();
        if (municipalityLayer) {
            leafletMap.fitBounds(municipalityLayer.getBounds());
        }
    });

    resetPrice();
    updatePrice();
    initMap();
})();
</script>
