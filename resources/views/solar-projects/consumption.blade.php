{{--
    Consumption diary (ADR-0013): the project's appliances grouped by the spaces of its kind of
    property. "+ Agregar" opens a sheet with the catalog; rows are edited or removed from it.
--}}
@php
    use App\Domain\Property\PropertyType;
    use App\Domain\Solar\CalculationFreshness;

    $spaces = PropertyType::spaces($solarProject->property_type);
    // "374", "14,5", "36" (never "36,0").
    $kwh = fn (float $value): string => number_format($value, $value >= 100 || fmod(round($value, 1), 1.0) === 0.0 ? 0 : 1, ',', '.');
    $percent = fn (float $value): string => number_format($value, 0, ',', '.').' %';

    // Ring: one color per space, in diary order ("Otros" keeps the neutral one).
    $spaceColors = [];
    $stops = [];
    $start = 0.0;
    foreach ($diary['spaces'] as $index => $space) {
        $spaceColors[$space['key']] = $space['key'] === PropertyType::OTHER_SPACE ? 'var(--diary-other)' : 'var(--diary-c'.($index + 1).')';
        if ($space['share'] > 0) {
            $end = $start + $space['share'];
            $stops[] = $spaceColors[$space['key']].' '.round($start, 2).'% '.round($end, 2).'%';
            $start = $end;
        }
    }
    $ringBackground = $stops === [] ? 'var(--solar-border)' : 'conic-gradient('.implode(', ', $stops).')';
    $ringDescription = collect($diary['spaces'])
        ->filter(fn ($space) => $space['kwh'] > 0)
        ->map(fn ($space) => $space['label'].' '.$percent($space['share']))
        ->implode(', ');

    $propertyLabel = mb_strtolower(PropertyType::label($solarProject->property_type));
    $status = $calculationFreshness->status;

    // Validation errors of the sheet: reopen it with what was sent.
    $reopen = $errors->any() && old('key') !== null ? [
        'id' => old('_appliance') ? (int) old('_appliance') : null,
        'space' => old('space'),
        'key' => old('key'),
        'variant' => old('variant'),
        'quantity' => (int) old('quantity', 1),
        'hoursPerDay' => (float) old('hours_per_day', 0),
    ] : null;
@endphp

<x-layouts::app :title="__('Consumo').' · '.$solarProject->name">
    <div class="solar-project-detail">
        @include('solar-projects.partials.project-nav', ['solarProject' => $solarProject, 'active' => 'consumption'])

        <div class="solar-page solar-diary" data-consumption-diary>
            @include('solar-projects.partials.appliance-icons')

            @if (session('status'))
                <div class="solar-alert solar-alert-success" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any() && $reopen === null)
                <div class="solar-alert solar-alert-danger" role="alert">{{ $errors->first() }}</div>
            @endif

            {{-- Summary: total, ring by space and the biggest consumer --}}
            <section class="solar-card-strong solar-diary-summary" aria-labelledby="diary-total">
                <div class="solar-diary-ring" style="--ring: {{ $ringBackground }}" role="img"
                     aria-label="{{ $ringDescription !== '' ? 'Consumo por espacio: '.$ringDescription : 'Todavía no hay equipos' }}">
                    <div class="solar-diary-ring__center">
                        <strong>{{ $kwh($diary['totalKwh']) }}</strong>
                        <span>kWh/mes</span>
                    </div>
                </div>

                <div class="solar-diary-summary__body">
                    <p class="solar-kicker">Consumo de tu {{ $propertyLabel }}</p>
                    <h1 id="diary-total" class="solar-diary-total">
                        @if ($diary['applianceCount'] > 0)
                            {{ $kwh($diary['totalKwh']) }} kWh al mes
                        @else
                            Recorre tu {{ $propertyLabel }} y agrega tus equipos
                        @endif
                    </h1>
                    <p class="solar-diary-summary__meta">
                        @if ($diary['applianceCount'] > 0)
                            ≈ {{ $kwh($diary['dailyKwh']) }} kWh al día · {{ $diary['applianceCount'] }} {{ $diary['applianceCount'] === 1 ? 'equipo' : 'equipos' }}
                        @else
                            Espacio por espacio, como un diario: con tus equipos calculamos cuántos paneles necesitas.
                        @endif
                    </p>

                    @if ($diary['applianceCount'] > 0)
                        <ul class="solar-diary-legend">
                            @foreach ($diary['spaces'] as $space)
                                @continue($space['kwh'] <= 0)
                                <li>
                                    <span class="solar-diary-dot" style="--dot: {{ $spaceColors[$space['key']] }}" aria-hidden="true"></span>
                                    {{ $space['label'] }}
                                    <strong>{{ $kwh($space['kwh']) }} kWh</strong>
                                    <span>{{ $percent($space['share']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($diary['biggest'])
                        <p class="solar-diary-biggest">
                            Lo que más consume: <strong>{{ $diary['biggest']['label'] }}</strong>,
                            {{ $kwh($diary['biggest']['kwh']) }} kWh al mes ({{ $percent($diary['biggest']['share']) }} del total).
                        </p>
                    @endif
                </div>

                <div class="solar-diary-summary__cta">
                    @if ($calculationFreshness->needsRecalculation())
                        <form method="POST" action="{{ route('solar-projects.calculate', $solarProject) }}">
                            @csrf
                            <input type="hidden" name="then" value="panel">
                            <button type="submit" class="solar-button solar-recalc-anchor" data-test="diary-calculate">
                                {{ $status === CalculationFreshness::PENDING ? 'Calcular mi sistema' : 'Recalcular mi sistema' }}
                                <span class="solar-recalc-badge solar-recalc-badge--floating" aria-hidden="true">!</span>
                            </button>
                        </form>
                        <p>{{ $status === CalculationFreshness::PENDING ? 'Tus equipos están listos para calcular.' : ($calculationFreshness->reasons[0] ?? 'Tus datos cambiaron desde el último cálculo.') }}</p>
                    @elseif ($status === CalculationFreshness::FRESH)
                        <a href="{{ route('solar-projects.show', $solarProject) }}" class="solar-button-ghost" wire:navigate>Ver mis resultados</a>
                        <p>El cálculo está al día con tus equipos.</p>
                    @endif
                </div>
            </section>

            @if ($usesBillConsumption)
                <div class="solar-alert solar-alert-warning" role="note">
                    Este proyecto usa un consumo de <strong>{{ $kwh($solarProject->monthlyConsumption()) }} kWh al mes</strong> tomado del recibo.
                    Cuando agregues tu primer equipo, el cálculo pasará a basarse en tus equipos, que reflejan mejor tu uso real.
                </div>
            @endif

            {{-- Spaces --}}
            @foreach ($diary['spaces'] as $space)
                <section class="solar-card solar-diary-space" id="espacio-{{ $space['key'] }}" aria-labelledby="espacio-{{ $space['key'] }}-titulo">
                    <header class="solar-diary-space__header">
                        <span class="solar-diary-dot" style="--dot: {{ $spaceColors[$space['key']] }}" aria-hidden="true"></span>
                        <h2 id="espacio-{{ $space['key'] }}-titulo">{{ $space['label'] }}</h2>
                        @if ($space['kwh'] > 0)
                            <span class="solar-diary-space__kwh">{{ $kwh($space['kwh']) }} kWh/mes</span>
                        @endif
                        <button type="button" class="solar-diary-add" data-diary-add="{{ $space['key'] }}">
                            <span aria-hidden="true">+</span> Agregar<span class="sr-only"> equipo a {{ $space['label'] }}</span>
                        </button>
                    </header>

                    @if ($space['items'] === [])
                        <p class="solar-diary-empty">Sin equipos todavía.</p>
                    @else
                        <ul class="solar-diary-items">
                            @foreach ($space['items'] as $item)
                                <li class="solar-diary-item">
                                    <svg viewBox="0 0 24 24" class="solar-diary-item__icon" aria-hidden="true"><use href="#appliance-{{ $item['icon'] }}"></use></svg>
                                    <div class="solar-diary-item__body">
                                        <strong>{{ $item['label'] }}</strong>
                                        <span>
                                            @if ($item['variantLabel'] !== ''){{ $item['variantLabel'] }} · @endif{{ $item['quantity'] }} × {{ $item['usageText'] }}
                                        </span>
                                    </div>
                                    <span class="solar-diary-item__kwh"><strong>{{ $kwh($item['kwh']) }}</strong> kWh/mes</span>
                                    <div class="solar-diary-item__actions">
                                        <button type="button" class="solar-diary-link" data-diary-edit='@json($item)'>Editar<span class="sr-only"> {{ $item['label'] }}</span></button>
                                        <form method="POST" action="{{ route('solar-projects.appliances.destroy', [$solarProject, $item['id']]) }}" data-diary-remove="{{ $item['label'] }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="solar-diary-link solar-diary-link--danger">Quitar<span class="sr-only"> {{ $item['label'] }}</span></button>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach

            <p class="solar-diary-footnote">Usamos consumos de referencia por tipo y tamaño de equipo. El consumo real cambia con la marca, la antigüedad y el uso.</p>
        </div>

        {{-- Sheet: pick an appliance, then its options, quantity and hours --}}
        <dialog class="solar-diary-sheet" data-diary-sheet aria-labelledby="diary-sheet-title">
            <form
                method="POST"
                action="{{ route('solar-projects.appliances.store', $solarProject) }}"
                class="solar-diary-sheet__form"
                data-diary-form
                data-store-action="{{ route('solar-projects.appliances.store', $solarProject) }}"
                data-update-action="{{ route('solar-projects.appliances.update', [$solarProject, '__ID__']) }}"
            >
                @csrf
                <input type="hidden" name="_method" value="POST" data-diary-method>
                <input type="hidden" name="_appliance" value="" data-diary-appliance>
                <input type="hidden" name="key" value="" data-diary-key>
                <input type="hidden" name="variant" value="" data-diary-variant>
                <input type="hidden" name="hours_per_day" value="" data-diary-hours-per-day>

                <header class="solar-diary-sheet__header">
                    <h2 id="diary-sheet-title" data-diary-title>Agregar equipo</h2>
                    <button type="button" class="solar-diary-sheet__close" data-diary-close aria-label="Cerrar">×</button>
                </header>

                <div class="solar-diary-sheet__step" data-diary-step="pick">
                    <input type="search" class="solar-input" placeholder="Buscar: nevera, aire, bombillos…" aria-label="Buscar equipo" data-diary-search autocomplete="off">
                    <div class="solar-appliance-grid solar-diary-grid" data-diary-grid>
                        @foreach ($applianceCatalog as $applianceKey => $appliance)
                            @php($isPrimary = array_intersect($appliance['segments'], $primarySegments) !== [])
                            <button
                                type="button"
                                class="solar-appliance-card"
                                data-diary-pick="{{ $applianceKey }}"
                                data-primary="{{ $isPrimary ? '1' : '0' }}"
                                data-search="{{ \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($appliance['label'].' '.($appliance['hint'] ?? ''))) }}"
                                @unless ($isPrimary) hidden @endunless
                            >
                                <svg viewBox="0 0 24 24" class="solar-appliance-card-icon" aria-hidden="true"><use href="#appliance-{{ $appliance['icon'] }}"></use></svg>
                                <span class="solar-appliance-card-label">{{ $appliance['label'] }}</span>
                            </button>
                        @endforeach
                    </div>
                    <p class="solar-diary-noresults" data-diary-noresults hidden>No encontramos ese equipo. Prueba con otro nombre o elige el más parecido.</p>
                    @if (count(array_filter($applianceCatalog, fn ($appliance) => array_intersect($appliance['segments'], $primarySegments) === [])) > 0)
                        <button type="button" class="solar-diary-link" data-diary-show-all>Ver todos los equipos</button>
                    @endif
                </div>

                <div class="solar-diary-sheet__step" data-diary-step="config" hidden>
                    <div class="solar-diary-chosen">
                        <svg viewBox="0 0 24 24" class="solar-diary-chosen__icon" aria-hidden="true"><use href="" data-diary-chosen-icon></use></svg>
                        <div>
                            <strong data-diary-chosen-label></strong>
                            <p class="solar-appliance-hint" data-diary-chosen-hint hidden></p>
                        </div>
                        <button type="button" class="solar-diary-link" data-diary-change>Cambiar equipo</button>
                    </div>

                    <div data-diary-groups></div>

                    <div class="solar-appliance-controls">
                        <div class="solar-appliance-field">
                            <span>Cantidad</span>
                            <div class="solar-appliance-stepper">
                                <button type="button" data-diary-step-quantity="-1" aria-label="Uno menos">−</button>
                                <input type="number" name="quantity" min="1" max="100" step="1" value="1" required aria-label="Cantidad" data-diary-quantity>
                                <button type="button" data-diary-step-quantity="1" aria-label="Uno más">+</button>
                            </div>
                        </div>
                        <label class="solar-appliance-field" data-diary-hours-field>
                            <span data-diary-hours-label>Horas al día</span>
                            <input type="number" class="solar-input" min="0" max="24" step="0.5" value="1" required data-diary-hours>
                        </label>
                        <p class="solar-appliance-always" data-diary-always hidden>Encendido las 24 horas</p>
                    </div>

                    <label class="solar-field mt-4">
                        <span class="solar-field-label">Espacio</span>
                        <select name="space" class="solar-input" required data-diary-space>
                            @foreach ($spaces as $spaceKey => $spaceLabel)
                                <option value="{{ $spaceKey }}">{{ $spaceLabel }}</option>
                            @endforeach
                        </select>
                    </label>

                    <p class="solar-diary-preview" aria-live="polite">≈ <strong data-diary-kwh>0</strong> kWh al mes</p>

                    @if ($reopen !== null)
                        <ul class="solar-diary-errors" role="alert">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <footer class="solar-diary-sheet__footer">
                        <button type="button" class="solar-button-ghost" data-diary-close>Cancelar</button>
                        <button type="submit" class="solar-button" data-diary-submit>Agregar</button>
                    </footer>
                </div>
            </form>
        </dialog>

        <script type="application/json" data-diary-catalog>@json($applianceCatalog)</script>
        <script type="application/json" data-diary-spaces>@json($spaces)</script>
        <script type="application/json" data-diary-reopen>@json($reopen)</script>
    </div>

    <script>
    (() => {
        const sheet = document.querySelector('[data-diary-sheet]');

        if (!sheet || sheet.dataset.ready) {
            return;
        }
        sheet.dataset.ready = '1';

        const read = (selector) => JSON.parse(document.querySelector(selector)?.textContent ?? 'null');
        const catalog = read('[data-diary-catalog]');
        const spaces = read('[data-diary-spaces]');
        const reopen = read('[data-diary-reopen]');
        const form = sheet.querySelector('[data-diary-form]');
        const $ = (selector) => form.querySelector(selector);
        const pickStep = $('[data-diary-step="pick"]');
        const configStep = $('[data-diary-step="config"]');
        const search = $('[data-diary-search]');
        const cards = Array.from(form.querySelectorAll('[data-diary-pick]'));
        const showAllButton = $('[data-diary-show-all]');
        const noResults = $('[data-diary-noresults]');
        const quantityInput = $('[data-diary-quantity]');
        const hoursInput = $('[data-diary-hours]');
        const kwhFormatter = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 1 });
        const DAYS_PER_MONTH = 30;
        let showAll = false;
        let state = null; // { id, key, choices: [], usage }

        const normalize = (text) => String(text).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
        const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));

        const variantOf = () => (catalog[state.key].groups.length ? state.choices.join('.') : 'default');
        const hoursPerDay = () => {
            const usage = catalog[state.key].usage;
            if (usage === 'always') {
                return 24;
            }
            const hours = Math.max(0, Number(String(hoursInput.value).replace(',', '.')) || 0);
            return Math.min(24, usage === 'week' ? hours / 7 : hours);
        };
        const iconOf = () => {
            const group = catalog[state.key].groups[0];
            return group?.choices.find((choice) => choice.key === state.choices[0])?.icon ?? catalog[state.key].icon;
        };

        const refreshPreview = () => {
            const watts = Number(catalog[state.key].watts[variantOf()] ?? 0);
            const quantity = Math.max(1, Math.round(Number(quantityInput.value) || 1));
            $('[data-diary-kwh]').textContent = kwhFormatter.format((watts * quantity * hoursPerDay() * DAYS_PER_MONTH) / 1000);
            $('[data-diary-chosen-icon]').setAttribute('href', `#appliance-${iconOf()}`);
        };

        const filterCards = () => {
            const term = normalize(search.value);
            let visible = 0;
            cards.forEach((card) => {
                const matches = term === ''
                    ? (showAll || card.dataset.primary === '1')
                    : card.dataset.search.includes(term);
                card.hidden = !matches;
                visible += matches ? 1 : 0;
            });
            noResults.hidden = visible > 0;
            if (showAllButton) {
                showAllButton.hidden = showAll || term !== '';
            }
        };

        const renderGroups = () => {
            const appliance = catalog[state.key];
            $('[data-diary-groups]').innerHTML = appliance.groups.map((group, groupIndex) => `
                <div class="solar-appliance-group">
                    <span class="solar-appliance-group-label">${escapeHtml(group.label)}</span>
                    <div class="solar-appliance-chips" role="radiogroup" aria-label="${escapeHtml(group.label)}">
                        ${group.choices.map((choice) => `
                            <button type="button" class="solar-appliance-chip" role="radio"
                                aria-checked="${state.choices[groupIndex] === choice.key}"
                                data-group="${groupIndex}" data-choice="${escapeHtml(choice.key)}">
                                ${choice.icon || choice.scale ? `<svg viewBox="0 0 24 24" class="solar-appliance-chip-icon solar-appliance-scale-${choice.scale ?? 2}" aria-hidden="true"><use href="#appliance-${escapeHtml(choice.icon ?? appliance.icon)}"></use></svg>` : ''}
                                <span>${escapeHtml(choice.label)}</span>
                            </button>`).join('')}
                    </div>
                </div>`).join('');
        };

        const showConfig = () => {
            const appliance = catalog[state.key];
            $('[data-diary-chosen-label]').textContent = appliance.label;
            const hint = $('[data-diary-chosen-hint]');
            hint.textContent = appliance.hint ?? '';
            hint.hidden = !appliance.hint;

            const always = appliance.usage === 'always';
            $('[data-diary-hours-field]').hidden = always;
            $('[data-diary-always]').hidden = !always;
            hoursInput.required = !always;
            hoursInput.max = appliance.usage === 'week' ? 168 : 24;
            $('[data-diary-hours-label]').textContent = appliance.usage === 'week' ? 'Horas a la semana' : 'Horas al día';

            renderGroups();
            pickStep.hidden = true;
            configStep.hidden = false;
            refreshPreview();
            (configStep.querySelector('[role="radio"]') ?? quantityInput).focus();
        };

        const choose = (key, { variant = null, quantity = null, hoursPerDay: perDay = null } = {}) => {
            const appliance = catalog[key];
            const defaults = appliance.groups.length ? String(appliance.default_variant).split('.') : [];
            const parts = String(variant ?? '').split('.');
            state = {
                ...state,
                key,
                choices: appliance.groups.map((group, index) => (group.choices.some((choice) => choice.key === parts[index]) ? parts[index] : defaults[index])),
            };
            quantityInput.value = quantity ?? appliance.default_quantity;
            const storedPerDay = perDay ?? (appliance.usage === 'week' ? appliance.default_hours / 7 : appliance.default_hours);
            hoursInput.value = Math.round((appliance.usage === 'week' ? storedPerDay * 7 : storedPerDay) * 10) / 10;
            showConfig();
        };

        const open = ({ space, item = null }) => {
            form.reset();
            state = { id: item?.id ?? null, key: null, choices: [] };
            showAll = false;
            search.value = '';
            filterCards();
            $('[data-diary-space]').value = item?.space && spaces[item.space] ? item.space : (spaces[space] ? space : 'other');
            $('[data-diary-appliance]').value = item?.id ?? '';
            $('[data-diary-method]').value = item?.id ? 'PUT' : 'POST';
            form.action = item?.id ? form.dataset.updateAction.replace('__ID__', item.id) : form.dataset.storeAction;
            $('[data-diary-submit]').textContent = item?.id ? 'Guardar cambios' : 'Agregar';
            $('[data-diary-title]').textContent = item?.id
                ? `Editar ${catalog[item.key]?.label ?? 'equipo'}`
                : `Agregar a ${spaces[space] ?? 'tu proyecto'}`;
            $('[data-diary-change]').hidden = Boolean(item?.id);

            sheet.showModal();

            if (item?.key && catalog[item.key]) {
                choose(item.key, { variant: item.variant, quantity: item.quantity, hoursPerDay: item.hoursPerDay });
            } else {
                configStep.hidden = true;
                pickStep.hidden = false;
                search.focus();
            }
        };

        // Open from a space ("+ Agregar") or from a row ("Editar").
        document.querySelectorAll('[data-diary-add]').forEach((button) => {
            button.addEventListener('click', () => open({ space: button.dataset.diaryAdd }));
        });
        document.querySelectorAll('[data-diary-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                const item = JSON.parse(button.dataset.diaryEdit);
                open({ space: item.space, item });
            });
        });

        search.addEventListener('input', filterCards);
        showAllButton?.addEventListener('click', () => {
            showAll = true;
            filterCards();
        });
        cards.forEach((card) => card.addEventListener('click', () => choose(card.dataset.diaryPick)));
        $('[data-diary-change]').addEventListener('click', () => {
            configStep.hidden = true;
            pickStep.hidden = false;
            search.focus();
        });

        configStep.addEventListener('click', (event) => {
            const chip = event.target.closest('[data-choice]');
            if (chip) {
                state.choices[Number(chip.dataset.group)] = chip.dataset.choice;
                chip.closest('[role="radiogroup"]').querySelectorAll('[data-choice]').forEach((other) => {
                    other.setAttribute('aria-checked', String(other === chip));
                });
                refreshPreview();
                return;
            }

            const stepButton = event.target.closest('[data-diary-step-quantity]');
            if (stepButton) {
                quantityInput.value = Math.max(1, Math.min(100, (Math.round(Number(quantityInput.value)) || 1) + Number(stepButton.dataset.diaryStepQuantity)));
                refreshPreview();
            }
        });
        quantityInput.addEventListener('input', refreshPreview);
        hoursInput.addEventListener('input', refreshPreview);

        form.addEventListener('submit', (event) => {
            if (!state?.key) {
                event.preventDefault();
                search.focus();
                return;
            }
            $('[data-diary-key]').value = state.key;
            $('[data-diary-variant]').value = variantOf();
            $('[data-diary-hours-per-day]').value = Math.round(hoursPerDay() * 100) / 100;
        });

        // Close: buttons, Esc (native) and a click on the backdrop.
        form.querySelectorAll('[data-diary-close]').forEach((button) => button.addEventListener('click', () => sheet.close()));
        sheet.addEventListener('click', (event) => {
            if (event.target === sheet) {
                sheet.close();
            }
        });

        document.querySelectorAll('[data-diary-remove]').forEach((removeForm) => {
            removeForm.addEventListener('submit', (event) => {
                if (!window.confirm(`¿Quitar ${removeForm.dataset.diaryRemove} del proyecto?`)) {
                    event.preventDefault();
                }
            });
        });

        // The server rejected the sheet: open it again with what was sent.
        if (reopen?.key && catalog[reopen.key]) {
            open({ space: reopen.space, item: reopen.id ? { ...reopen } : null });
            if (!reopen.id) {
                choose(reopen.key, reopen);
            }
        }
    })();
    </script>
</x-layouts::app>
