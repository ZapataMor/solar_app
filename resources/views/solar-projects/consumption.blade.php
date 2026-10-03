{{--
    Consumption diary (ADR-0013): the project's appliances grouped by the spaces of its kind of
    property. "+ Agregar" opens a sheet with the catalog; rows are edited or removed from it.
--}}
@php
    use App\Domain\Property\PropertyType;

    $spaces = PropertyType::spaces($solarProject->property_type);

    // Validation errors of the sheet (without JavaScript): reopen it with what was sent.
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
    <div class="solar-project-detail" style="view-transition-name: project-{{ $solarProject->id }}">
        @include('solar-projects.partials.project-nav', ['solarProject' => $solarProject, 'active' => 'consumption'])

        <div
            class="solar-page solar-diary"
            data-consumption-diary
            data-unit-root
            data-unit="kwh"
            data-rate="{{ $diary['ratePerKwh'] }}"
        >
            @include('solar-projects.partials.appliance-icons')

            {{-- kWh or pesos per month: for clients who understand money better than kWh. --}}
            @include('solar-projects.partials.unit-toolbar', ['rate' => $diary['ratePerKwh']])

            @if ($errors->any() && $reopen === null)
                <div class="solar-alert solar-alert-danger" role="alert">{{ $errors->first() }}</div>
            @endif

            {{-- The consumption can also come from the bill (ADR-0020); the stage of the edit form changes it. --}}
            <p class="solar-diary-switch">
                ¿Prefieres usar los kWh de tu recibo en lugar de tus equipos?
                <a href="{{ route('solar-projects.edit', $solarProject) }}#paso-3" class="solar-diary-link">Cambiar cómo calculo mi consumo</a>
            </p>

            <div class="solar-diary-content" data-diary-content>
                @include('solar-projects.partials.consumption-diary-content')
            </div>
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
                            {{-- Hidden by an administrator (ADR-0017): only here so existing rows stay editable. --}}
                            @continue(($appliance['active'] ?? true) === false)
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
                    <div class="solar-diary-config">
                        {{--
                            The chosen appliance in 3D, as its options make it (ADR-0019). Only appliances with a
                            model show it (resources/js/appliance-scene); the diary script says which one and how.
                        --}}
                        <figure class="solar-appliance-model" data-appliance-scene data-appliance="" data-variant="" data-variant-label="">
                            <div class="solar-appliance-model__view">
                                <p class="solar-3d-loading" aria-hidden="true"><span class="solar-sync-spinner"></span>Preparando el equipo en 3D…</p>
                                <div class="solar-appliance-model__stage" data-appliance-scene-stage></div>
                            </div>
                            <figcaption>
                                <strong data-appliance-model-title></strong>
                                <span data-appliance-model-note></span>
                            </figcaption>
                        </figure>

                        <div class="solar-diary-config__fields">
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

                            <p class="solar-diary-preview" aria-live="polite">
                                ≈ <strong data-diary-kwh>0</strong> <span data-diary-preview-unit>kWh al mes</span>
                                <span class="solar-diary-preview__other" data-diary-preview-other></span>
                            </p>

                            {{-- Server-side errors: from the redirect (no JavaScript) or from the 422 of a save without reload. --}}
                            <ul class="solar-diary-errors" role="alert" data-diary-errors @if ($reopen === null) hidden @endif>
                                @if ($reopen !== null)
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                @endif
                            </ul>
                        </div>
                    </div>

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
        const root = document.querySelector('[data-consumption-diary]');
        const sheet = document.querySelector('[data-diary-sheet]');

        if (!root || !sheet || sheet.dataset.ready) {
            return;
        }
        sheet.dataset.ready = '1';

        const read = (selector) => JSON.parse(document.querySelector(selector)?.textContent ?? 'null');
        const catalog = read('[data-diary-catalog]');
        const spaces = read('[data-diary-spaces]');
        const reopen = read('[data-diary-reopen]');
        const content = root.querySelector('[data-diary-content]');
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
        const errorsBox = $('[data-diary-errors]');
        const submitButton = $('[data-diary-submit]');
        const model = $('[data-appliance-scene]');
        const kwhFormatter = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 1 });
        const moneyFormatter = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 });
        const money = (cop) => `$${moneyFormatter.format(Math.round(cop / 100) * 100)}`;
        const rate = Number(root.dataset.rate) || 0;
        const DAYS_PER_MONTH = 30;
        let showAll = false;
        let state = null; // { id, key, choices: [] }

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

        const showErrors = (messages) => {
            errorsBox.innerHTML = messages.map((message) => `<li>${escapeHtml(message)}</li>`).join('');
            errorsBox.hidden = messages.length === 0;
        };

        // The preview speaks the unit the viewer chose, and gives the other one in brackets.
        const refreshPreview = () => {
            const watts = Number(catalog[state.key].watts[variantOf()] ?? 0);
            const quantity = Math.max(1, Math.round(Number(quantityInput.value) || 1));
            const kwh = (watts * quantity * hoursPerDay() * DAYS_PER_MONTH) / 1000;
            const inMoney = root.dataset.unit === 'money' && rate > 0;

            $('[data-diary-kwh]').textContent = inMoney ? money(kwh * rate) : kwhFormatter.format(kwh);
            $('[data-diary-preview-unit]').textContent = inMoney ? 'al mes' : 'kWh al mes';
            $('[data-diary-preview-other]').textContent = rate > 0
                ? (inMoney ? `(${kwhFormatter.format(kwh)} kWh)` : `(unos ${money(kwh * rate)})`)
                : '';
            $('[data-diary-chosen-icon]').setAttribute('href', `#appliance-${iconOf()}`);
            showModel();
        };

        // The 3D model (ADR-0019, resources/js/appliance-scene) follows the chosen appliance and options.
        const showModel = () => {
            const groups = state?.key ? catalog[state.key].groups : [];
            // The chosen options ("18.000 BTU · Inverter"), or the appliance's name when it has none.
            model.dataset.variantLabel = groups
                .map((group, index) => group.choices.find((choice) => choice.key === state.choices[index])?.label)
                .filter(Boolean)
                .join(' · ') || (state?.key ? catalog[state.key].label : '');
            model.dataset.variant = state?.key ? variantOf() : '';
            model.dataset.appliance = state?.key ?? '';
        };

        // The kWh / pesos switch is wired by app.js; the preview follows it when it changes.
        root.addEventListener('unit:changed', () => state?.key && refreshPreview());

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

        const open = ({ space, item = null, keepErrors = false }) => {
            form.reset();
            if (!keepErrors) {
                showErrors([]);
            }
            state = { id: item?.id ?? null, key: null, choices: [] };
            showModel();
            showAll = false;
            search.value = '';
            filterCards();
            $('[data-diary-space]').value = item?.space && spaces[item.space] ? item.space : (spaces[space] ? space : 'other');
            $('[data-diary-appliance]').value = item?.id ?? '';
            $('[data-diary-method]').value = item?.id ? 'PUT' : 'POST';
            form.action = item?.id ? form.dataset.updateAction.replace('__ID__', item.id) : form.dataset.storeAction;
            submitButton.textContent = item?.id ? 'Guardar cambios' : 'Agregar';
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

        // Save without reloading: the server answers with the re-rendered diary (summary, ring, spaces).
        const send = async (targetForm) => {
            const response = await fetch(targetForm.action, {
                method: 'POST', // the _method field inside the form says PUT or DELETE
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(targetForm),
            });
            const payload = await response.json().catch(() => ({}));

            return { ok: response.ok, status: response.status, payload };
        };

        const applyUpdate = (payload) => {
            content.innerHTML = payload.html;
            window.solarToast?.(payload.message);

            const row = payload.appliance ? content.querySelector(`[data-diary-item="${payload.appliance}"]`) : null;
            const target = row ?? content.querySelector(`#espacio-${CSS.escape(payload.space ?? '')}`);
            target?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            row?.classList.add('is-updated');
        };

        // Delegated: the diary content is replaced after each save, and its buttons with it.
        content.addEventListener('click', (event) => {
            const add = event.target.closest('[data-diary-add]');
            if (add) {
                open({ space: add.dataset.diaryAdd });
                return;
            }

            const edit = event.target.closest('[data-diary-edit]');
            if (edit) {
                const item = JSON.parse(edit.dataset.diaryEdit);
                open({ space: item.space, item });
            }
        });

        content.addEventListener('submit', async (event) => {
            const removeForm = event.target.closest('[data-diary-remove]');
            if (!removeForm) {
                return; // e.g. "Calcular mi sistema": a normal submit that goes to the panel.
            }

            event.preventDefault();
            if (!window.confirm(`¿Quitar ${removeForm.dataset.diaryRemove} del proyecto?`)) {
                return;
            }

            content.setAttribute('aria-busy', 'true');
            try {
                const { ok, payload } = await send(removeForm);
                if (!ok || !payload.html) {
                    throw new Error('remove failed');
                }
                applyUpdate(payload);
            } catch (_error) {
                removeForm.submit(); // Fall back to the classic request: it reloads, but it works.
            } finally {
                content.removeAttribute('aria-busy');
            }
        });

        search.addEventListener('input', filterCards);
        showAllButton?.addEventListener('click', () => {
            showAll = true;
            filterCards();
        });
        cards.forEach((card) => card.addEventListener('click', () => choose(card.dataset.diaryPick)));
        $('[data-diary-change]').addEventListener('click', () => {
            model.dataset.appliance = '';
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

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (!state?.key) {
                search.focus();
                return;
            }

            $('[data-diary-key]').value = state.key;
            $('[data-diary-variant]').value = variantOf();
            $('[data-diary-hours-per-day]').value = Math.round(hoursPerDay() * 100) / 100;

            const label = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Guardando…';
            showErrors([]);

            try {
                const { ok, status, payload } = await send(form);

                if (status === 422) {
                    showErrors(Object.values(payload.errors ?? {}).flat());
                    return;
                }
                if (!ok || !payload.html) {
                    throw new Error(`HTTP ${status}`);
                }

                sheet.close();
                applyUpdate(payload);
            } catch (_error) {
                form.submit(); // Fall back to the classic request: it reloads, but it works.
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = label;
            }
        });

        // Close: buttons, Esc (native) and a click on the backdrop.
        form.querySelectorAll('[data-diary-close]').forEach((button) => button.addEventListener('click', () => sheet.close()));
        sheet.addEventListener('click', (event) => {
            if (event.target === sheet) {
                sheet.close();
            }
        });

        // The server rejected the sheet in a classic request: open it again with what was sent.
        if (reopen?.key && catalog[reopen.key]) {
            open({ space: reopen.space, item: reopen.id ? { ...reopen } : null, keepErrors: true });
            if (!reopen.id) {
                choose(reopen.key, reopen);
            }
        }
    })();
    </script>
</x-layouts::app>
