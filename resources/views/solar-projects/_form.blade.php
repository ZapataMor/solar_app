@php
    $technicalParameter = $solarProject?->technicalParameter;
    $investmentCostPerKwpCop = 5000000;
    $referenceDailyHsp = 5.8;
    $ambientContextUrl = route('solar-projects.simulator.ambient-context');
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
        performanceRatio: form.querySelector('[name="performance_ratio"]'),
        systemLossesPct: form.querySelector('[name="system_losses_percentage"]'),
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
    };

    const scaleButtons = Array.from(form.querySelectorAll('[data-sim-scale-btn]'));

    const sourceCopy = () => `${ambientSourceLabel}: ${numberFormatter.format(ambientDailyHsp)} HSP/dia. Sin degradacion anual ni costos O&M.`;

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
        const endDate = fields.endDate?.value;

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
        const performanceRatio = toNumber(fields.performanceRatio?.value);
        const systemLossesPct = toNumber(fields.systemLossesPct?.value);

        if (!monthlyConsumption || !energyRate || !availableArea || !usableAreaPct || !panelPowerW || !panelArea || !performanceRatio) {
            setDefaultState();
            return;
        }

        const usableArea = availableArea * (usableAreaPct / 100);
        const panels = panelArea > 0 ? Math.floor(usableArea / panelArea) : 0;
        const capacityKwp = (panels * panelPowerW) / 1000;
        const lossFactor = 1 - (systemLossesPct / 100);
        const monthlyGeneration = capacityKwp * ambientDailyHsp * 30 * performanceRatio * lossFactor;
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

    Object.values(fields).forEach((field) => {
        if (field) {
            field.addEventListener('input', update);
        }
    });

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
