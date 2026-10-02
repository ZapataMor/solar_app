{{--
    Add or change an appliance of the catalog (ADR-0017). app.js adds and removes option rows and
    adapts the hours to the kind of use.
    Params: $appliance (CatalogAppliance|null).
--}}
@php
    use App\Domain\Consumption\ApplianceCatalog;

    $editing = $appliance !== null;
    $variants = old('variants', $appliance?->variants ?? [['key' => null, 'label' => '', 'watts' => '']]);
    $segments = old('segments', $appliance?->segments ?? [ApplianceCatalog::SEGMENT_HOME]);
    $usage = old('usage', $appliance?->usage ?? 'day');
    $icon = old('icon', $appliance?->icon ?? 'plug');
    $hours = old('default_hours', $appliance && $appliance->usage !== 'always' ? rtrim(rtrim(number_format($appliance->default_hours, 2, '.', ''), '0'), '.') : '');
@endphp

<x-layouts::app :title="$editing ? 'Editar '.$appliance->label : __('Agregar equipo')">
    @include('solar-projects.partials.appliance-icons')

    <div class="solar-page solar-page-narrow solar-catalog-form-page">
        <div class="solar-page-header">
            <div>
                <a href="{{ route('appliance-catalog.index') }}" class="solar-project-nav__back" wire:navigate>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                    Catálogo de equipos
                </a>
                <h1 class="solar-title mt-2">{{ $editing ? 'Editar '.$appliance->label : 'Agregar equipo' }}</h1>
                <p class="solar-subtitle mt-2">
                    @if ($editing)
                        Si cambias la potencia, los proyectos que lo usan recalculan su consumo y quedan marcados para recalcular.
                    @else
                        Aparecerá en el diario de consumo de todos los proyectos, junto a los equipos del sistema.
                    @endif
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

        <form method="POST" action="{{ $editing ? route('appliance-catalog.update', $appliance) : route('appliance-catalog.store') }}" class="solar-card solar-catalog-form" data-catalog-form>
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <label class="solar-field">
                <span class="solar-field-label">Nombre</span>
                <input name="label" value="{{ old('label', $appliance?->label) }}" required maxlength="80" class="solar-input" placeholder="Ej.: Tostadora">
            </label>

            <fieldset class="solar-field">
                <legend class="solar-field-label">Dibujo</legend>
                <div class="solar-catalog-icons">
                    @foreach (ApplianceCatalog::ICONS as $name => $iconLabel)
                        <label class="solar-catalog-icon" title="{{ $iconLabel }}">
                            <input type="radio" name="icon" value="{{ $name }}" @checked($icon === $name) class="sr-only">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#appliance-{{ $name }}"></use></svg>
                            <span class="sr-only">{{ $iconLabel }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset class="solar-field">
                <legend class="solar-field-label">Para</legend>
                <div class="solar-catalog-checks">
                    <label><input type="checkbox" name="segments[]" value="{{ ApplianceCatalog::SEGMENT_HOME }}" @checked(in_array(ApplianceCatalog::SEGMENT_HOME, $segments, true))> Hogar</label>
                    <label><input type="checkbox" name="segments[]" value="{{ ApplianceCatalog::SEGMENT_BUSINESS }}" @checked(in_array(ApplianceCatalog::SEGMENT_BUSINESS, $segments, true))> Negocio</label>
                </div>
                <span class="text-xs text-[color:var(--solar-text-muted)]">Las instituciones ven los de ambos.</span>
            </fieldset>

            <div class="solar-catalog-form__row">
                <label class="solar-field">
                    <span class="solar-field-label">Uso</span>
                    <select name="usage" class="solar-input" data-catalog-usage>
                        <option value="day" @selected($usage === 'day')>Horas al día</option>
                        <option value="week" @selected($usage === 'week')>Horas a la semana (uso ocasional)</option>
                        <option value="always" @selected($usage === 'always')>Encendido todo el día</option>
                    </select>
                </label>
                <label class="solar-field" data-catalog-hours @if ($usage === 'always') hidden @endif>
                    <span class="solar-field-label" data-catalog-hours-label>{{ $usage === 'week' ? 'Horas a la semana' : 'Horas al día' }}</span>
                    <input type="number" name="default_hours" value="{{ $hours }}" step="0.25" min="0.25" class="solar-input" inputmode="decimal">
                </label>
                <label class="solar-field">
                    <span class="solar-field-label">Cantidad habitual</span>
                    <input type="number" name="default_quantity" value="{{ old('default_quantity', $appliance?->default_quantity ?? 1) }}" min="1" max="100" required class="solar-input">
                </label>
            </div>

            <label class="solar-field">
                <span class="solar-field-label">Ayuda para el cliente <span class="solar-field-optional">(opcional)</span></span>
                <input name="hint" value="{{ old('hint', $appliance?->hint) }}" maxlength="255" class="solar-input" placeholder="Ej.: Se usa unos minutos en el desayuno.">
            </label>

            <fieldset class="solar-field">
                <legend class="solar-field-label">Opciones y potencia</legend>
                <p class="text-xs text-[color:var(--solar-text-muted)]">
                    Una sola fila si el equipo no tiene variantes. Con varias, el cliente elige una; la primera es la que propone el diario.
                </p>
                <label class="solar-field solar-catalog-option-label">
                    <span>Las opciones son de</span>
                    <input name="option_label" value="{{ old('option_label', $appliance?->option_label) }}" maxlength="40" class="solar-input" placeholder="Tipo, Tamaño, Capacidad…">
                </label>

                <table class="solar-catalog-options">
                    <thead><tr><th>Opción</th><th>Potencia (W)</th><th><span class="sr-only">Quitar</span></th></tr></thead>
                    <tbody data-catalog-options>
                        @foreach ($variants as $index => $variant)
                            <tr data-catalog-option>
                                <td>
                                    <input type="hidden" name="variants[{{ $index }}][key]" value="{{ $variant['key'] ?? '' }}">
                                    <input name="variants[{{ $index }}][label]" value="{{ $variant['label'] ?? '' }}" maxlength="40" class="solar-input" placeholder="Ej.: 2 ranuras" aria-label="Nombre de la opción">
                                </td>
                                <td><input type="number" name="variants[{{ $index }}][watts]" value="{{ $variant['watts'] ?? '' }}" step="any" min="0.1" max="20000" class="solar-input" placeholder="850" aria-label="Potencia en vatios" inputmode="decimal"></td>
                                <td><button type="button" class="solar-diary-link solar-diary-link--danger" data-catalog-remove-option>Quitar</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <template data-catalog-option-template>
                    <tr data-catalog-option>
                        <td>
                            <input type="hidden" name="variants[__INDEX__][key]" value="">
                            <input name="variants[__INDEX__][label]" maxlength="40" class="solar-input" placeholder="Ej.: 4 ranuras" aria-label="Nombre de la opción">
                        </td>
                        <td><input type="number" name="variants[__INDEX__][watts]" step="any" min="0.1" max="20000" class="solar-input" placeholder="1200" aria-label="Potencia en vatios" inputmode="decimal"></td>
                        <td><button type="button" class="solar-diary-link solar-diary-link--danger" data-catalog-remove-option>Quitar</button></td>
                    </tr>
                </template>
                <button type="button" class="solar-diary-link" data-catalog-add-option>+ Agregar opción</button>
            </fieldset>

            @if ($editing)
                <label class="solar-catalog-checks">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" @checked(old('active', $appliance->active))>
                    Se ofrece en el diario de consumo
                    <span class="text-xs text-[color:var(--solar-text-muted)]">(si lo ocultas, los proyectos que ya lo usan lo conservan)</span>
                </label>
            @endif

            <div class="solar-catalog-form__actions">
                <a href="{{ route('appliance-catalog.index') }}" class="solar-button-ghost" wire:navigate>Cancelar</a>
                <button type="submit" class="solar-button">{{ $editing ? 'Guardar cambios' : 'Agregar al catálogo' }}</button>
            </div>
        </form>
    </div>
</x-layouts::app>
