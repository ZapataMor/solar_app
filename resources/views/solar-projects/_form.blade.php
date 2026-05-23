@php
    $technicalParameter = $solarProject?->technicalParameter;
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
                <p class="solar-subtitle mt-2">Riohacha se mantiene como ubicacion fija para asegurar coherencia geografica y comparabilidad de los escenarios.</p>
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
                <span class="solar-field-label">Ubicacion</span>
                <input
                    value="{{ \App\Models\SolarProject::LOCATION_NAME }}"
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

            <label class="solar-field">
                <span class="solar-field-label">Consumo anual en kWh</span>
                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    name="annual_consumption_kwh"
                    value="{{ old('annual_consumption_kwh', $solarProject?->annual_consumption_kwh) }}"
                    required
                    class="solar-input"
                >
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
        </div>
    </section>

    <section class="solar-card">
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Dimensionamiento</p>
                <h2 class="text-2xl text-[color:var(--solar-text)]">Parametros tecnicos</h2>
                <p class="solar-subtitle mt-2">Estos valores alimentan la lectura de capacidad, cobertura y desempeno anual del sistema fotovoltaico.</p>
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
                <span class="solar-field-label">Performance ratio</span>
                <input
                    type="number"
                    step="0.001"
                    min="0"
                    max="1"
                    name="performance_ratio"
                    value="{{ old('performance_ratio', $technicalParameter?->performance_ratio) }}"
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

    <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="solar-button">
            {{ $buttonText }}
        </button>

        <a href="{{ route('solar-projects.index') }}" class="solar-button-ghost">
            Cancelar
        </a>
    </div>
</form>
