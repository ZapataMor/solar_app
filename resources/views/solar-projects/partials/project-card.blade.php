@php
    $calculationResult = $solarProject->calculationResult;
    $coverage = $calculationResult ? (float) $calculationResult->coverage_percentage : null;
    $annualSavings = $calculationResult ? (float) $calculationResult->estimated_annual_savings_cop : null;
    $hasWeatherContext = ($solarProject->weather_data_count ?? 0) > 0 || ($solarProject->weather_station_readings_count ?? 0) > 0;
    $description = filled($solarProject->description)
        ? \Illuminate\Support\Str::limit(trim($solarProject->description), 96)
        : 'Escenario solar para consumo, cobertura y ahorro en Riohacha.';

    if ($coverage === null) {
        $coverageLabel = 'Sin calculo';
        $coverageTone = 'warn';
        $coverageDetail = 'Pendiente';
        $statusLabel = 'Pendiente de simulacion';
        $statusTone = 'warn';
        $riskLabel = $hasWeatherContext ? 'Medio' : 'Alto';
        $riskTone = $hasWeatherContext ? 'warn' : 'danger';
    } elseif ($coverage >= 100) {
        $coverageLabel = 'Alta';
        $coverageTone = 'success';
        $coverageDetail = number_format($coverage, 1, ',', '.') . '%';
        $statusLabel = 'Cobertura favorable';
        $statusTone = 'success';
        $riskLabel = 'Bajo';
        $riskTone = 'success';
    } elseif ($coverage >= 70) {
        $coverageLabel = 'Media';
        $coverageTone = 'warn';
        $coverageDetail = number_format($coverage, 1, ',', '.') . '%';
        $statusLabel = 'Cobertura funcional';
        $statusTone = 'warn';
        $riskLabel = 'Medio';
        $riskTone = 'warn';
    } else {
        $coverageLabel = 'Baja';
        $coverageTone = 'danger';
        $coverageDetail = number_format($coverage, 1, ',', '.') . '%';
        $statusLabel = 'Cobertura limitada';
        $statusTone = 'danger';
        $riskLabel = 'Alto';
        $riskTone = 'danger';
    }

    $iaActive = filled(config('services.openai.api_key')) && $calculationResult !== null;
    $typeLabel = filled($solarProject->description) ? 'Escenario personalizado' : 'Proyecto base';
    $badgeClass = fn ($tone) => match ($tone) {
        'success' => 'solar-pill-success',
        'warn' => 'solar-pill-warn',
        'danger' => 'solar-pill-danger',
        default => '',
    };
@endphp

<article class="solar-project-card" x-data="{ confirmDeleteOpen: false }">
    <div class="solar-project-card__glow" aria-hidden="true"></div>

    <div class="solar-project-card__top">
        <div>
            <p class="solar-kicker">Proyecto solar</p>
            <h3 class="solar-project-card__title">{{ $solarProject->name }}</h3>
            <p class="solar-project-card__type">{{ $typeLabel }}</p>
        </div>

        <span class="solar-pill {{ $badgeClass($statusTone) }}">
            {{ $statusLabel }}
        </span>
    </div>

    <p class="solar-project-card__summary">{{ $description }}</p>

    <dl class="solar-project-card__meta">
        <div>
            <dt>Ubicacion</dt>
            <dd>{{ $solarProject->location_name }}</dd>
        </div>
        <div>
            <dt>Cobertura energetica</dt>
            <dd>{{ $coverageDetail }}</dd>
        </div>
        <div>
            <dt>Ahorro anual</dt>
            <dd>{{ $annualSavings !== null ? '$ ' . number_format($annualSavings, 0, ',', '.') . ' COP' : 'Pendiente' }}</dd>
        </div>
        <div>
            <dt>Ultima actualizacion</dt>
            <dd>{{ $solarProject->updated_at?->format('d M Y') }}</dd>
        </div>
    </dl>

    <div class="solar-project-card__badges">
        <span class="solar-pill {{ $badgeClass($coverageTone) }}">
            Cobertura {{ $coverageLabel }}
        </span>
        <span class="solar-pill {{ $badgeClass($riskTone) }}">
            Riesgo {{ $riskLabel }}
        </span>
        <span class="solar-pill {{ $badgeClass($iaActive ? 'success' : 'warn') }}">
            IA {{ $iaActive ? 'Activa' : 'Inactiva' }}
        </span>
    </div>

    <div class="solar-project-card__actions">
        <a href="{{ route('solar-projects.show', $solarProject) }}" class="solar-button solar-project-card__action">
            Ver dashboard
        </a>
        <a href="{{ route('solar-projects.edit', $solarProject) }}" class="solar-button-secondary solar-project-card__action">
            Editar
        </a>
        <form method="POST" action="{{ route('solar-projects.destroy', $solarProject) }}" x-ref="deleteForm">
            @csrf
            @method('DELETE')
            <button
                type="button"
                class="solar-button-danger solar-project-card__action w-full"
                x-on:click="confirmDeleteOpen = true"
            >
                Eliminar
            </button>

            <template x-teleport="body">
                <div
                    x-cloak
                    x-show="confirmDeleteOpen"
                    x-on:keydown.escape.window="confirmDeleteOpen = false"
                    class="solar-confirm-overlay"
                >
                    <div
                        class="solar-confirm-card"
                        x-show="confirmDeleteOpen"
                        x-transition.opacity.scale.90.duration.180ms
                        x-on:click.stop
                    >
                        <p class="solar-kicker">Confirmar eliminacion</p>
                        <h4 class="solar-confirm-card__title">¿Eliminar {{ $solarProject->name }}?</h4>
                        <p class="solar-confirm-card__copy">
                            Se borrara el proyecto y perderas su acceso desde el listado. Esta accion no se puede deshacer.
                        </p>

                        <div class="solar-confirm-card__actions">
                            <button
                                type="button"
                                class="solar-button-ghost"
                                x-on:click="confirmDeleteOpen = false"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                class="solar-button-danger"
                                x-on:click="$refs.deleteForm.requestSubmit()"
                            >
                                Si, eliminar
                            </button>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="solar-confirm-backdrop"
                        aria-label="Cerrar confirmacion"
                        x-on:click="confirmDeleteOpen = false"
                    ></button>
                </div>
            </template>
        </form>
    </div>
</article>
