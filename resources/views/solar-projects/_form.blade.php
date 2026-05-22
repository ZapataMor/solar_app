@php
    $technicalParameter = $solarProject?->technicalParameter;
@endphp

@if ($errors->any())
    <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200">
        <ul class="list-disc space-y-1 ps-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}" class="space-y-8">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <section class="space-y-5">
        <div>
            <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Información del proyecto</h2>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Riohacha se asigna automáticamente como ubicación fija.</p>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <label class="space-y-2">
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Nombre del proyecto</span>
                <input
                    name="name"
                    value="{{ old('name', $solarProject?->name) }}"
                    required
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50"
                >
            </label>

            <label class="space-y-2">
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Ubicación</span>
                <input
                    value="{{ \App\Models\SolarProject::LOCATION_NAME }}"
                    disabled
                    class="w-full rounded-lg border border-zinc-300 bg-zinc-100 px-3 py-2 text-sm text-zinc-700 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                >
            </label>

            <label class="space-y-2 md:col-span-2">
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Descripción</span>
                <textarea
                    name="description"
                    rows="3"
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50"
                >{{ old('description', $solarProject?->description) }}</textarea>
            </label>

            <label class="space-y-2">
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Fecha inicial</span>
                <input
                    type="date"
                    name="start_date"
                    value="{{ old('start_date', $solarProject?->start_date?->format('Y-m-d')) }}"
                    required
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50"
                >
            </label>

            <label class="space-y-2">
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Fecha final</span>
                <input
                    type="date"
                    name="end_date"
                    value="{{ old('end_date', $solarProject?->end_date?->format('Y-m-d')) }}"
                    required
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50"
                >
            </label>

            <label class="space-y-2">
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Consumo anual en kWh</span>
                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    name="annual_consumption_kwh"
                    value="{{ old('annual_consumption_kwh', $solarProject?->annual_consumption_kwh) }}"
                    required
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50"
                >
            </label>

            <label class="space-y-2">
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Tarifa energética en COP/kWh</span>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="energy_rate_cop_kwh"
                    value="{{ old('energy_rate_cop_kwh', $solarProject?->energy_rate_cop_kwh) }}"
                    required
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50"
                >
            </label>
        </div>
    </section>

    <section class="space-y-5">
        <h2 class="text-base font-semibold text-zinc-900 dark:text-zinc-50">Parámetros técnicos</h2>

        <div class="grid gap-5 md:grid-cols-2">
            <label class="space-y-2">
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Área total disponible en m2</span>
                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    name="available_area_m2"
                    value="{{ old('available_area_m2', $technicalParameter?->available_area_m2) }}"
                    required
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50"
                >
            </label>

            <label class="space-y-2">
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Área utilizable (%)</span>
                <input
                    type="number"
                    step="0.01"
                    min="1"
                    max="100"
                    name="usable_area_percentage"
                    value="{{ old('usable_area_percentage', $technicalParameter?->usable_area_percentage) }}"
                    required
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50"
                >
            </label>

            <label class="space-y-2">
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Potencia del panel en W</span>
                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    name="panel_power_w"
                    value="{{ old('panel_power_w', $technicalParameter?->panel_power_w) }}"
                    required
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50"
                >
            </label>

            <label class="space-y-2">
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Área del panel en m2</span>
                <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    name="panel_area_m2"
                    value="{{ old('panel_area_m2', $technicalParameter?->panel_area_m2) }}"
                    required
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50"
                >
            </label>

            <label class="space-y-2">
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Performance ratio</span>
                <input
                    type="number"
                    step="0.001"
                    min="0"
                    max="1"
                    name="performance_ratio"
                    value="{{ old('performance_ratio', $technicalParameter?->performance_ratio) }}"
                    required
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50"
                >
            </label>

            <label class="space-y-2">
                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Pérdidas del sistema (%)</span>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    name="system_losses_percentage"
                    value="{{ old('system_losses_percentage', $technicalParameter?->system_losses_percentage) }}"
                    required
                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50"
                >
            </label>
        </div>
    </section>

    <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-700 dark:bg-zinc-50 dark:text-zinc-900 dark:hover:bg-zinc-200">
            {{ $buttonText }}
        </button>

        <a href="{{ route('solar-projects.index') }}" class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">
            Cancelar
        </a>
    </div>
</form>
