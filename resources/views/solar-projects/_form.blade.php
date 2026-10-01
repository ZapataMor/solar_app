@php
    use App\Domain\Property\PropertyType;

    $technicalParameter = $solarProject?->technicalParameter;
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
    $selectedPropertyType = old('property_type', $solarProject?->property_type);
    $selectedMunicipalityId = old('municipality_id', $solarProject?->municipality_id);
    $selectedLocationType = old('location_type', $solarProject?->location_type ?? 'urbana');
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

    // Roof size shortcuts (m²) for clients who do not know the exact area.
    $roofPresets = [
        ['area' => 20, 'label' => 'Pequeño', 'hint' => 'unos 20 m²'],
        ['area' => 40, 'label' => 'Mediano', 'hint' => 'unos 40 m²'],
        ['area' => 80, 'label' => 'Grande', 'hint' => '80 m² o más'],
    ];

    // Guided stages (ADR-0013) and the fields each one owns, used to reopen the stage with server errors.
    $wizardSteps = [
        ['key' => 'property', 'label' => 'Tu lugar', 'fields' => ['property_type']],
        ['key' => 'location', 'label' => 'Ubicación', 'fields' => ['municipality_id', 'location_type', 'latitude', 'longitude']],
        ['key' => 'roof', 'label' => 'Techo', 'fields' => ['available_area_m2', 'usable_area_percentage', 'panel_power_w', 'panel_area_m2', 'system_losses_percentage', 'start_date', 'end_date']],
        ['key' => 'details', 'label' => 'Tu proyecto', 'fields' => ['energy_rate_cop_kwh', 'name']],
    ];
    $errorKeys = collect($errors->keys());
    $stepsWithErrors = collect($wizardSteps)
        ->keys()
        ->filter(fn (int $index) => $errorKeys->contains(fn (string $key) => in_array($key, $wizardSteps[$index]['fields'], true)))
        ->values();
    $initialStep = $stepsWithErrors->first() ?? 0;
    $furthestStep = ($isCreating && ! $errors->any()) ? 0 : count($wizardSteps) - 1;
    $advancedOpen = $errors->hasAny(['usable_area_percentage', 'panel_power_w', 'panel_area_m2', 'system_losses_percentage', 'start_date', 'end_date']);
    $coordinatesOpen = $errors->hasAny(['latitude', 'longitude']);
    $lastStepNumber = count($wizardSteps);
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
    @if ($errors->any()) data-wizard-has-errors @endif
    @if ($isCreating) data-draft-key="natalia:project-draft:{{ auth()->id() }}" @endif
>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    @if ($isCreating)
        <div class="solar-alert solar-alert-success solar-draft-notice" data-draft-notice hidden>
            <p>Recuperamos el proyecto que estabas creando.</p>
            <button type="button" class="solar-button-ghost" data-draft-discard>Empezar de cero</button>
        </div>
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

    {{-- 1 · Tu lugar --}}
    <section class="solar-card-strong" data-wizard-step="property" data-wizard-label="Tu lugar">
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Paso 1 de {{ $lastStepNumber }} · Tu lugar</p>
                <h2 class="solar-wizard-heading text-2xl text-[color:var(--solar-text)]" tabindex="-1">¿Para qué lugar quieres energía solar?</h2>
                <p class="solar-subtitle mt-2">Con esto te mostramos los espacios y equipos que tienen sentido para ti.</p>
            </div>
        </div>

        <fieldset class="solar-property-options mt-6">
            <legend class="sr-only">Tipo de lugar</legend>
            @foreach (PropertyType::ALL as $propertyType)
                <label class="solar-property-option">
                    <input
                        type="radio"
                        name="property_type"
                        value="{{ $propertyType }}"
                        required
                        data-property-option="{{ PropertyType::optionLabel($propertyType) }}"
                        @checked($selectedPropertyType === $propertyType)
                    >
                    <span class="solar-property-option__icon" aria-hidden="true">
                        @switch($propertyType)
                            @case(PropertyType::HOUSE)
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/></svg>
                                @break
                            @case(PropertyType::BUSINESS)
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18l-1.5-5h-15z"/><path d="M4 9v12h16V9"/><path d="M3 9c0 1.7 1.3 3 3 3s3-1.3 3-3c0 1.7 1.3 3 3 3s3-1.3 3-3c0 1.7 1.3 3 3 3s3-1.3 3-3"/><path d="M9 21v-5h6v5"/></svg>
                                @break
                            @default
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2 8l10 5 10-5z"/><path d="M6 10.2V16c0 1.7 2.7 3 6 3s6-1.3 6-3v-5.8"/><path d="M22 8v6"/></svg>
                        @endswitch
                    </span>
                    <span class="solar-property-option__text">
                        <strong>{{ PropertyType::optionLabel($propertyType) }}</strong>
                        <span>{{ PropertyType::hint($propertyType) }}</span>
                    </span>
                </label>
            @endforeach
        </fieldset>
    </section>

    {{-- 2 · Ubicación --}}
    <section class="solar-card" data-location-quote data-wizard-step="location" data-wizard-label="Ubicación">
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Paso 2 de {{ $lastStepNumber }} · Ubicación</p>
                <h2 class="solar-wizard-heading text-2xl text-[color:var(--solar-text)]" tabindex="-1">¿Dónde está?</h2>
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
        </div>

        <div class="solar-location-layout">
            <div id="la-guajira-map" class="solar-location-map"></div>
            <aside class="solar-location-summary">
                <div class="solar-location-row"><span>Municipio</span><strong data-price-municipality>--</strong></div>
                <div class="solar-location-row"><span>Zona</span><strong data-price-zone>--</strong></div>
                <div class="solar-location-row"><span>Tipo de ubicación</span><strong data-price-location-type>--</strong></div>
                <div class="solar-location-row"><span>Precio por kW instalado</span><strong data-price-final>--</strong></div>
                <p class="solar-location-message" data-price-message>Selecciona tu municipio para ver el precio de instalación de la zona.</p>
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

    {{-- 3 · Techo --}}
    <section class="solar-card" data-wizard-step="roof" data-wizard-label="Techo" data-roof>
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Paso 3 de {{ $lastStepNumber }} · Techo</p>
                <h2 class="solar-wizard-heading text-2xl text-[color:var(--solar-text)]" tabindex="-1">¿Cuánto espacio tienes en el techo?</h2>
                <p class="solar-subtitle mt-2">El área del techo o terreno donde podrían ir los paneles. Si no la sabes exacta, elige el tamaño más parecido.</p>
            </div>
        </div>

        <div class="solar-roof-presets mt-6" role="group" aria-label="Tamaño aproximado del techo">
            @foreach ($roofPresets as $preset)
                <button type="button" class="solar-roof-preset" data-roof-preset="{{ $preset['area'] }}" aria-pressed="false">
                    <strong>{{ $preset['label'] }}</strong>
                    <span>{{ $preset['hint'] }}</span>
                </button>
            @endforeach
        </div>

        <div class="solar-form-grid mt-4 md:grid-cols-2">
            <label class="solar-field">
                <span class="solar-field-label">Área disponible en m²</span>
                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    name="available_area_m2"
                    value="{{ old('available_area_m2', $technicalParameter?->available_area_m2) }}"
                    required
                    class="solar-input"
                    data-roof-area
                >
                <span class="text-xs text-[color:var(--solar-text-muted)]" data-roof-hint aria-live="polite"></span>
            </label>
        </div>

        <details class="solar-wizard-advanced mt-6" @if ($advancedOpen) open @endif>
            <summary>Parámetros avanzados</summary>
            <div class="solar-wizard-advanced-body">
                <p class="solar-subtitle">Valores técnicos con los que estimamos el sistema. Ya traen valores típicos; un instalador puede ajustarlos.</p>

                <div class="solar-form-grid mt-4 md:grid-cols-2">
                    <label class="solar-field">
                        <span class="solar-field-label">Área utilizable %</span>
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
                        <span class="solar-field-label">Área del panel en m²</span>
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
                        <span class="solar-field-label">Pérdidas del sistema %</span>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            max="99"
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

    {{-- 4 · Tu proyecto: tarifa, nombre y resumen --}}
    <section class="solar-card" data-wizard-step="details" data-wizard-label="Tu proyecto">
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Paso 4 de {{ $lastStepNumber }} · Tu proyecto</p>
                <h2 class="solar-wizard-heading text-2xl text-[color:var(--solar-text)]" tabindex="-1">Últimos datos</h2>
                <p class="solar-subtitle mt-2">
                    @if ($isCreating)
                        Después de crearlo agregarás tus equipos, espacio por espacio, para calcular tu sistema.
                    @else
                        Tus equipos se cambian en la pestaña Consumo.
                    @endif
                </p>
            </div>
        </div>

        <div class="solar-form-grid mt-6 md:grid-cols-2">
            <label class="solar-field">
                <span class="solar-field-label">¿Cuánto pagas por cada kWh?</span>
                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    name="energy_rate_cop_kwh"
                    value="{{ old('energy_rate_cop_kwh', $solarProject?->energy_rate_cop_kwh) }}"
                    required
                    class="solar-input"
                    inputmode="decimal"
                >
                <span class="text-xs text-[color:var(--solar-text-muted)]">Tarifa en pesos por kWh; aparece en tu recibo de energía.</span>
            </label>

            <div class="flex items-end">
                <button type="button" class="solar-button-ghost solar-energy-guide-trigger" data-energy-guide-open>
                    ¿Dónde la encuentro en mi recibo?
                </button>
            </div>

            <label class="solar-field md:col-span-2">
                <span class="solar-field-label">Nombre del proyecto</span>
                <input
                    name="name"
                    value="{{ old('name', $solarProject?->name) }}"
                    required
                    maxlength="255"
                    class="solar-input"
                    data-project-name
                >
                <span class="text-xs text-[color:var(--solar-text-muted)]">Te sugerimos uno; puedes cambiarlo.</span>
            </label>
        </div>

        <dl class="solar-wizard-summary mt-6">
            <div class="solar-wizard-summary-row">
                <dt>Lugar</dt>
                <dd><span data-summary="property">—</span> <button type="button" class="solar-wizard-edit" data-wizard-edit="0">Editar</button></dd>
            </div>
            <div class="solar-wizard-summary-row">
                <dt>Ubicación</dt>
                <dd><span data-summary="location">—</span> <button type="button" class="solar-wizard-edit" data-wizard-edit="1">Editar</button></dd>
            </div>
            <div class="solar-wizard-summary-row">
                <dt>Techo</dt>
                <dd><span data-summary="area">—</span> <button type="button" class="solar-wizard-edit" data-wizard-edit="2">Editar</button></dd>
            </div>
        </dl>
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
                    En la segunda hoja de tu recibo de energía encontrarás este apartado: ahí aparece tu tarifa en pesos por kWh.
                </h3>
                <button type="button" class="solar-button-ghost solar-energy-guide-close" data-energy-guide-close aria-label="Cerrar guía del recibo">
                    Cerrar
                </button>
            </div>

            <div class="solar-energy-guide-body">
                <img
                    src="{{ asset('images/guia-recibo-energia.jpeg') }}"
                    alt="Guía visual del recibo de energía donde se encuentran la tarifa y el consumo en kWh"
                    class="solar-energy-guide-image"
                >
            </div>
        </div>
    </div>

    <div class="solar-wizard-actions">
        <a href="{{ $isCreating ? route('solar-projects.index') : route('solar-projects.show', $solarProject) }}" class="solar-button-ghost" data-wizard-cancel>
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
    // New-project draft: what the user typed survives a reload. It runs before the other scripts
    // so the map and the summary start from the restored values.
    const form = document.querySelector('[data-project-wizard][data-draft-key]');

    if (!form) {
        return;
    }

    const key = form.dataset.draftKey;
    const MAX_AGE_MS = 7 * 24 * 60 * 60 * 1000;
    const notice = form.querySelector('[data-draft-notice]');
    const read = () => {
        try {
            const draft = JSON.parse(localStorage.getItem(key) ?? 'null');
            return draft && Date.now() - draft.savedAt < MAX_AGE_MS ? draft : null;
        } catch (_error) {
            return null;
        }
    };
    const clear = () => {
        try {
            localStorage.removeItem(key);
        } catch (_error) {
            // Storage unavailable: nothing to clear.
        }
    };

    const snapshot = () => {
        const fields = {};

        Array.from(form.elements).forEach((field) => {
            if (!field.name || ['_token', '_method'].includes(field.name) || field.disabled) {
                return;
            }

            // A radio group (kind of property) only counts its checked option.
            if (field.type === 'radio') {
                if (field.checked) {
                    fields[field.name] = field.value;
                }
                return;
            }

            fields[field.name] = field.value;
        });

        return { fields };
    };

    const pristine = JSON.stringify(snapshot());
    let saveTimer = null;

    const save = () => {
        const current = snapshot();

        try {
            if (JSON.stringify(current) === pristine) {
                localStorage.removeItem(key);
            } else {
                localStorage.setItem(key, JSON.stringify({ ...current, savedAt: Date.now() }));
            }
        } catch (_error) {
            // Private mode or full storage: the form still works, it just is not remembered.
        }
    };
    const scheduleSave = () => {
        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(save, 300);
    };

    // Server validation errors bring back the submitted values (old input), which are fresher.
    const draft = form.hasAttribute('data-wizard-has-errors') ? null : read();

    if (draft) {
        Object.entries(draft.fields ?? {}).forEach(([name, value]) => {
            const field = form.elements[name];
            if (field) {
                field.value = value; // Also checks the right radio of a RadioNodeList.
            }
        });

        if (notice) {
            notice.hidden = false;
        }
    }

    ['input', 'change', 'click'].forEach((type) => form.addEventListener(type, scheduleSave));

    notice?.querySelector('[data-draft-discard]')?.addEventListener('click', (event) => {
        event.stopPropagation();
        window.clearTimeout(saveTimer);
        clear();
        window.location.replace(window.location.pathname);
    });

    form.querySelector('[data-wizard-cancel]')?.addEventListener('click', (event) => {
        event.stopPropagation();
        window.clearTimeout(saveTimer);
        clear();
    });

    // Once the form is really sent (the wizard did not stop it), the draft is no longer needed.
    form.addEventListener('submit', (event) => queueMicrotask(() => {
        if (!event.defaultPrevented) {
            window.clearTimeout(saveTimer);
            clear();
        }
    }));
})();
</script>

<script>
(() => {
    // Project wizard (ADR-0007, stages of ADR-0013): client-side stages over a single form.
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

    // Fields inside a hidden panel of a stage are not validated; the stage section itself may be hidden.
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
    const propertyOption = () => form.querySelector('[name="property_type"]:checked')?.dataset.propertyOption ?? '';

    const fillSummary = () => {
        const set = (key, text) => {
            const target = form.querySelector(`[data-summary="${key}"]`);
            if (target) {
                target.textContent = text || '—';
            }
        };
        const municipality = fieldValue('municipality_id') ? selectedText('municipality_id') : '';
        const area = fieldValue('available_area_m2');

        set('property', propertyOption());
        set('location', municipality ? `${municipality} · ${selectedText('location_type')}` : '');
        set('area', area ? `${formatNumber(area)} m²` : '');
    };

    // The name is suggested from the answers ("Mi casa en Maicao") until the user writes their own.
    const nameInput = form.querySelector('[data-project-name]');
    const suggestName = () => {
        const municipality = fieldValue('municipality_id') ? selectedText('municipality_id') : '';
        const suggestion = [propertyOption(), municipality].filter(Boolean).join(' en ');
        const untouched = nameInput.value.trim() === '' || nameInput.value === nameInput.dataset.suggested;

        if (suggestion && untouched) {
            nameInput.value = suggestion;
            nameInput.dataset.suggested = suggestion;
            nameInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
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
            suggestName();
            fillSummary();
        }

        // The stage lives in the URL (#paso-3), so a reload reopens it.
        const url = `${window.location.pathname}${window.location.search}${current > 0 ? `#paso-${current + 1}` : ''}`;
        if (url !== `${window.location.pathname}${window.location.search}${window.location.hash}`) {
            history.replaceState(history.state, '', url);
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

    // Creating: choosing the kind of place is the whole first question, so a tap moves on by itself.
    // Only real pointer clicks (detail > 0): arrow keys also select radios and must not jump ahead.
    if (form.hasAttribute('data-draft-key')) {
        form.querySelectorAll('.solar-property-option').forEach((option) => option.addEventListener('click', (event) => {
            if (current === 0 && event.detail > 0) {
                window.setTimeout(() => goTo(1), 250);
            }
        }));
    }

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

    // Reopen the stage from the URL once every stage registered its validators (called at the end of
    // this partial). Server errors win; on a new project the stages before it must be complete.
    const requestedStep = Number(window.location.hash.match(/^#paso-(\d+)$/)?.[1] ?? 0) - 1;

    form.wizardStart = () => {
        if (form.hasAttribute('data-wizard-has-errors')) {
            show(current, { focus: false });
            return;
        }

        const firstGap = firstInvalidUntil(lastStep - 1);
        furthest = Math.max(furthest, firstGap ? firstGap.step : lastStep);
        show(requestedStep > 0 ? Math.min(requestedStep, furthest) : current, { focus: false });
    };

    show(current, { focus: false });
})();
</script>

<script>
(() => {
    // Roof stage: size shortcuts fill the area, and a hint says how many panels would fit.
    const root = document.querySelector('[data-roof]');

    if (!root) {
        return;
    }

    const form = root.closest('form');
    const areaInput = root.querySelector('[data-roof-area]');
    const hint = root.querySelector('[data-roof-hint]');
    const presets = Array.from(root.querySelectorAll('[data-roof-preset]'));
    const number = (name) => Number(String(form.elements[name]?.value ?? '').replace(',', '.')) || 0;

    const refresh = () => {
        const area = number('available_area_m2');
        presets.forEach((preset) => preset.setAttribute('aria-pressed', String(Number(preset.dataset.roofPreset) === area)));

        const panelArea = number('panel_area_m2');
        const panels = panelArea > 0 ? Math.floor((area * number('usable_area_percentage') / 100) / panelArea) : 0;
        hint.textContent = area > 0 && panels > 0
            ? `Ahí caben unos ${panels} ${panels === 1 ? 'panel' : 'paneles'} (un panel ocupa unos ${String(panelArea).replace('.', ',')} m²).`
            : '';
    };

    presets.forEach((preset) => preset.addEventListener('click', () => {
        areaInput.value = preset.dataset.roofPreset;
        areaInput.dispatchEvent(new Event('input', { bubbles: true }));
    }));
    form.addEventListener('input', refresh);
    refresh();
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
    const root = document.querySelector('[data-location-quote]');

    if (!root) {
        return;
    }

    const endpointTemplate = @json($solarPriceUrlTemplate);
    const geoJsonUrl = '/maps/la_guajira_municipios.geojson';
    const municipalitySelect = root.querySelector('[data-location-municipality]');
    const locationTypeSelect = root.querySelector('[data-location-type]');
    const latitudeInput = root.querySelector('[data-location-latitude]');
    const longitudeInput = root.querySelector('[data-location-longitude]');
    const moneyFormatter = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
    const locationTypeLabels = @json($locationTypes);
    let municipalityLayer = null;
    let selectedLayer = null;
    let leafletMap = null;

    const out = {
        municipality: root.querySelector('[data-price-municipality]'),
        zone: root.querySelector('[data-price-zone]'),
        locationType: root.querySelector('[data-price-location-type]'),
        final: root.querySelector('[data-price-final]'),
        message: root.querySelector('[data-price-message]'),
    };

    const currentOption = () => municipalitySelect.options[municipalitySelect.selectedIndex] ?? null;
    const selectedMunicipalityName = () => currentOption()?.dataset.name ?? '';
    const selectedMunicipalityId = () => municipalitySelect.value;

    const resetPrice = (message = 'Selecciona tu municipio para ver el precio de instalación de la zona.') => {
        out.municipality.textContent = selectedMunicipalityName() || '--';
        out.zone.textContent = currentOption()?.dataset.zone || '--';
        out.locationType.textContent = locationTypeLabels[locationTypeSelect.value] || '--';
        out.final.textContent = '--';
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

    // Price per installed kW of the municipality; the total comes once the appliances are known (ADR-0013).
    const updatePrice = async () => {
        if (!selectedMunicipalityId()) {
            resetPrice();
            return;
        }

        syncCoordinatesFromOption();
        const url = new URL(endpointTemplate.replace('__MUNICIPALITY__', selectedMunicipalityId()), window.location.origin);
        url.searchParams.set('location_type', locationTypeSelect.value);

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
            out.final.textContent = moneyFormatter.format(payload.final_price_per_kw);
            out.message.textContent = 'El costo total lo calculamos cuando agregues tus equipos.';
        } catch (_error) {
            resetPrice('No hay precio disponible para esa ubicación.');
        }
    };

    const normalizeText = (value) => String(value || '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
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
        // Tell the rest of the form (draft, suggested name) that the municipality changed.
        municipalitySelect.dispatchEvent(new Event('input', { bubbles: true }));
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

    // Paints on the map the municipality chosen in the select (or clears the paint when there is none).
    function highlightSelectedMunicipality() {
        if (!municipalityLayer) {
            return;
        }

        const selectedCode = currentOption()?.dataset.daneCode || '';
        const selectedName = selectedMunicipalityId() ? normalizeText(selectedMunicipalityName()) : '';
        let match = null;

        municipalityLayer.eachLayer((layer) => {
            if (match || !selectedName) {
                return;
            }

            if (
                normalizeText(municipalityNameFromFeature(layer.feature)) === selectedName
                || (selectedCode && daneCodeFromFeature(layer.feature) === selectedCode)
            ) {
                match = layer;
            }
        });

        if (match) {
            highlightLayer(match);
        } else if (selectedLayer) {
            normalizeLayerStyle(selectedLayer);
            selectedLayer = null;
        }
    }

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

            municipalityLayer = L.geoJSON(geoJson, {
                style: municipalityStyle,
                onEachFeature: onEachMunicipality,
            }).addTo(map);
            map.fitBounds(municipalityLayer.getBounds());
            // Editing, a restored draft or old input: the municipality is already chosen.
            highlightSelectedMunicipality();
        } catch (_error) {
            console.error(_error);
            out.message.textContent = 'No fue posible cargar los límites reales de los municipios de La Guajira. Verifique que el archivo public/maps/la_guajira_municipios.geojson exista y sea un GeoJSON válido.';
        }
    };

    municipalitySelect.addEventListener('change', () => {
        latitudeInput.value = '';
        longitudeInput.value = '';
        syncCoordinatesFromOption();
        highlightSelectedMunicipality();
        updatePrice();
    });
    locationTypeSelect.addEventListener('change', updatePrice);

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

<script>
    // Every stage registered its validators by now: reopen the stage from the URL (#paso-N).
    document.querySelector('[data-project-wizard]')?.wizardStart?.();
</script>
