{{--
    Administration: general values of the system (ADR-0015). Each one shows what applies today, what is
    scheduled, a form to record a new value from a date on, and the history.
    Params: $referenceValues (DescribeReferenceValues).
--}}
@php
    // "$890 por kWh", "20 %".
    $format = function (float $value, $definition): string {
        $number = number_format($value, $definition->decimals, ',', '.');

        return match ($definition->unit) {
            '$/kWh' => '$'.$number,
            '%' => $number.' %',
            default => $number.' '.$definition->unit,
        };
    };
    $unitText = fn ($definition): string => $definition->unit === '$/kWh' ? 'por kWh' : '';
    $date = fn ($value): string => \Illuminate\Support\Carbon::parse($value)->locale('es')->isoFormat('D [de] MMMM [de] YYYY');
    $today = now()->toDateString();
@endphp

<x-layouts::app :title="__('Valores de referencia')">
    <div class="solar-page solar-reference">
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Administración</p>
                <h1 class="solar-title mt-0">Valores de referencia</h1>
                <p class="solar-subtitle mt-2 max-w-3xl">
                    Valores generales que usa el sistema cuando el cliente no indica los suyos. Cada cambio guarda desde
                    cuándo rige; los anteriores quedan en el historial.
                </p>
            </div>
        </div>

        @foreach ($referenceValues as $item)
            @php
                $definition = $item['definition'];
                $current = $item['current'];
                $hasErrors = old('key') === $definition->key && $errors->any();
            @endphp

            <section class="solar-card solar-reference-value" id="valor-{{ $definition->key }}" aria-labelledby="valor-{{ $definition->key }}-titulo">
                <div class="solar-reference-value__head">
                    <div>
                        <h2 id="valor-{{ $definition->key }}-titulo">{{ $definition->label }}</h2>
                        <p>{{ $definition->description }}</p>
                    </div>
                    <p class="solar-reference-value__now" data-test="current-{{ $definition->key }}">
                        <strong>{{ $format($current->value, $definition) }}</strong>
                        <span>{{ $unitText($definition) }}</span>
                    </p>
                </div>

                <ul class="solar-reference-value__facts">
                    @if ($current->isDefault())
                        <li>Valor por defecto del sistema: todavía no se ha registrado uno.</li>
                    @else
                        <li>Rige desde el {{ $date($current->validFrom->format('Y-m-d')) }}</li>
                        <li>Fuente: {{ $current->source ?: 'sin fuente' }}</li>
                    @endif
                    @if ($item['businessRate'] !== null)
                        <li>Un negocio paga {{ $format($item['businessRate'], $definition) }} con la contribución</li>
                    @endif
                    @if ($item['projectsUsingIt'] !== null)
                        <li>{{ $item['projectsUsingIt'] === 1 ? 'Lo usa 1 proyecto' : 'Lo usan '.$item['projectsUsingIt'].' proyectos' }} sin tarifa propia</li>
                    @endif
                </ul>

                @foreach ($item['scheduled'] as $scheduled)
                    <p class="solar-reference-value__scheduled">
                        Programado: <strong>{{ $format((float) $scheduled->value, $definition) }}</strong> desde el {{ $date($scheduled->valid_from) }}.
                        Los proyectos se marcarán para recalcular ese día.
                    </p>
                @endforeach

                <details class="solar-reference-value__panel" @if ($hasErrors) open @endif>
                    <summary>Registrar un nuevo valor</summary>

                    @if ($hasErrors)
                        <div class="solar-alert solar-alert-danger mt-3" role="alert">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('reference-values.store') }}" class="solar-reference-form">
                        @csrf
                        <input type="hidden" name="key" value="{{ $definition->key }}">

                        <label class="solar-field">
                            <span class="solar-field-label">Nuevo valor ({{ $definition->unit }})</span>
                            <input
                                type="number"
                                name="value"
                                step="any"
                                min="{{ $definition->min }}"
                                max="{{ $definition->max }}"
                                value="{{ $hasErrors ? old('value') : '' }}"
                                placeholder="{{ number_format($current->value, $definition->decimals, '.', '') }}"
                                required
                                class="solar-input"
                                inputmode="decimal"
                            >
                        </label>

                        <label class="solar-field">
                            <span class="solar-field-label">Rige desde</span>
                            <input type="date" name="valid_from" value="{{ $hasErrors ? old('valid_from') : $today }}" required class="solar-input">
                            <span class="text-xs text-[color:var(--solar-text-muted)]">Una fecha futura lo deja programado.</span>
                        </label>

                        <label class="solar-field solar-reference-form__wide">
                            <span class="solar-field-label">Fuente</span>
                            <input
                                name="source"
                                maxlength="255"
                                value="{{ $hasErrors ? old('source') : '' }}"
                                placeholder="Ej.: tarifas publicadas por Air-e para octubre de 2026"
                                class="solar-input"
                            >
                        </label>

                        <label class="solar-field solar-reference-form__wide">
                            <span class="solar-field-label">Nota (opcional)</span>
                            <textarea name="notes" rows="2" maxlength="1000" class="solar-input">{{ $hasErrors ? old('notes') : '' }}</textarea>
                        </label>

                        <div class="solar-reference-form__wide">
                            <button type="submit" class="solar-button">Guardar valor</button>
                        </div>
                    </form>
                </details>

                @if ($item['history']->isNotEmpty())
                    <details class="solar-reference-value__panel">
                        <summary>Historial ({{ $item['history']->count() }})</summary>
                        <div class="solar-reference-table-wrap">
                            <table class="solar-reference-table">
                                <thead>
                                    <tr><th>Rige desde</th><th>Valor</th><th>Fuente</th><th>Registró</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($item['history'] as $row)
                                        <tr @if ($loop->first) aria-current="true" @endif>
                                            <td>{{ $date($row->valid_from) }}</td>
                                            <td>{{ $format((float) $row->value, $definition) }}</td>
                                            <td>
                                                {{ $row->source ?: '—' }}
                                                @if ($row->notes)<span class="solar-reference-table__note">{{ $row->notes }}</span>@endif
                                            </td>
                                            <td>{{ $row->user?->name ?? 'Sistema' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
            </section>
        @endforeach
    </div>
</x-layouts::app>
