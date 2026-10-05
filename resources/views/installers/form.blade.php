{{--
    Add or change an allied installer (ADR-0022). Administration only: the installer is a record, not
    a user, so nobody signs up here. The form is grouped the way the directory reads it: who they are,
    how they are contacted, and where they work.
    Params: $installer (Installer|null), $municipalities, $covered (list<int>).
--}}
@php
    $editing = $installer !== null;
    $chosen = array_map('intval', old('municipalities', $covered));
    $active = (bool) old('active', $installer?->active ?? true);
@endphp

<x-layouts::app :title="$editing ? 'Editar '.$installer->name : __('Agregar instalador')">
    @push('head')
        {{-- Leaflet's stylesheet; its script loads on demand from coverage-map.js. --}}
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    @endpush

    <div class="solar-page solar-installer-page">
        <div class="solar-page-header">
            <div>
                <a href="{{ route('installers.index') }}" class="solar-project-nav__back" wire:navigate>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                    Instaladores
                </a>
                <p class="solar-kicker mt-3">Administración</p>
                <h1 class="solar-title mt-0">{{ $editing ? 'Editar '.$installer->name : 'Agregar instalador' }}</h1>
                <p class="solar-subtitle mt-2 max-w-2xl">
                    Aparecerá para los clientes que tengan un proyecto en los municipios que cubra.
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

        <form method="POST" action="{{ $editing ? route('installers.update', $installer) : route('installers.store') }}" class="solar-installer-form">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <section class="solar-card solar-installer-section">
                <div class="solar-installer-section__head">
                    <h2>Quién es</h2>
                    <p>Lo que el cliente lee en la tarjeta del directorio.</p>
                </div>

                <div class="solar-installer-section__body">
                    <label class="solar-field">
                        <span class="solar-field-label">Nombre</span>
                        <input name="name" value="{{ old('name', $installer?->name) }}" required maxlength="120" class="solar-input" placeholder="Ej.: Sol de Riohacha">
                    </label>

                    <label class="solar-field">
                        <span class="solar-field-label">Una línea que lo describa <span class="solar-field-optional">· opcional</span></span>
                        <input name="tagline" value="{{ old('tagline', $installer?->tagline) }}" maxlength="160" class="solar-input" placeholder="Ej.: Instalación residencial y de pequeños negocios">
                        <span class="solar-field-hint">Va bajo el nombre, así que una frase corta basta.</span>
                    </label>

                    <label class="solar-field">
                        <span class="solar-field-label">Descripción <span class="solar-field-optional">· opcional</span></span>
                        <textarea name="description" rows="3" maxlength="1000" class="solar-textarea" placeholder="Qué hace, en qué se especializa, qué incluye.">{{ old('description', $installer?->description) }}</textarea>
                    </label>

                    <label class="solar-field solar-installer-form__narrow">
                        <span class="solar-field-label">Años de experiencia <span class="solar-field-optional">· opcional</span></span>
                        <input type="number" name="years_experience" value="{{ old('years_experience', $installer?->years_experience) }}" min="0" max="80" class="solar-input" inputmode="numeric" placeholder="8">
                    </label>
                </div>
            </section>

            <section class="solar-card solar-installer-section">
                <div class="solar-installer-section__head">
                    <h2>Cómo lo contactan</h2>
                    <p>Solo lo ve quien ya le pidió cotización: es el momento en que el cliente hace contacto.</p>
                </div>

                <div class="solar-installer-section__body">
                    <label class="solar-field">
                        <span class="solar-field-label">A quién se contacta <span class="solar-field-optional">· opcional</span></span>
                        <input name="contact_name" value="{{ old('contact_name', $installer?->contact_name) }}" maxlength="120" class="solar-input" placeholder="Ej.: Oficina comercial">
                    </label>

                    <div class="solar-installer-form__row">
                        <label class="solar-field">
                            <span class="solar-field-label">Teléfono</span>
                            <input name="phone" value="{{ old('phone', $installer?->phone) }}" maxlength="40" class="solar-input" placeholder="300 000 0000" inputmode="tel">
                            <span class="solar-field-hint">Con diez dígitos se ofrece también por WhatsApp.</span>
                        </label>

                        <label class="solar-field">
                            <span class="solar-field-label">Correo</span>
                            <input type="email" name="email" value="{{ old('email', $installer?->email) }}" maxlength="120" class="solar-input" placeholder="contacto@empresa.com">
                            <span class="solar-field-hint">Basta con uno de los dos, pero los dos ayudan.</span>
                        </label>
                    </div>
                </div>
            </section>

            <section class="solar-card solar-installer-section solar-installer-section--wide">
                <div class="solar-installer-section__head">
                    <h2>Dónde trabaja</h2>
                    <p>Un cliente solo lo ve si su proyecto está en uno de estos municipios.</p>
                </div>

                <div class="solar-installer-section__body">
                    <fieldset class="solar-field" data-installer-municipalities data-coverage-map>
                        <div class="solar-installer-municipalities__head">
                            <legend class="solar-field-label">Municipios que cubre</legend>
                            <p class="solar-installer-municipalities__count" data-municipality-count aria-live="polite">
                                {{ count($chosen) }} de {{ $municipalities->count() }}
                            </p>
                            <button type="button" class="solar-button-ghost solar-installer-municipalities__toggle" data-municipality-toggle>
                                Seleccionar todos
                            </button>
                        </div>

                        <div class="solar-coverage-layout">
                            {{-- The municipalities of the project form; here a click covers or uncovers one.
                                 coverage-map.js clicks the checkboxes beside it, which are the real field. --}}
                            <div>
                                <div class="solar-coverage-map" data-coverage-canvas></div>
                                <p class="solar-field-hint mt-2" data-coverage-note>
                                    Haz clic en el mapa o en la lista: lo que cubre queda en naranja. Son municipios
                                    enteros, no solo la cabecera; el de Riohacha llega hasta Camarones y Tomarrazón.
                                </p>
                            </div>

                            <div class="solar-installer-municipalities">
                                @foreach ($municipalities as $municipality)
                                    <label class="solar-installer-municipality">
                                    <input
                                        type="checkbox"
                                        name="municipalities[]"
                                        value="{{ $municipality->id }}"
                                        data-name="{{ $municipality->name }}"
                                        data-dane-code="{{ \App\Domain\Property\MunicipalityBoundaries::daneCode($municipality->name) }}"
                                        @checked(in_array($municipality->id, $chosen, true))
                                        class="sr-only"
                                    >
                                    <span>{{ $municipality->name }}</span>
                                    @if ($municipality->zone)
                                        <small>{{ $municipality->zone }}</small>
                                    @endif
                                </label>
                                @endforeach
                            </div>
                        </div>
                    </fieldset>
                </div>
            </section>

            <section class="solar-card solar-installer-section">
                <div class="solar-installer-section__head">
                    <h2>Estado</h2>
                    <p>Ocultarlo no borra nada: conserva las solicitudes que ya recibió.</p>
                </div>

                <div class="solar-installer-section__body">
                    <input type="hidden" name="active" value="0">
                    <label class="solar-installer-toggle">
                        <input type="checkbox" name="active" value="1" @checked($active)>
                        <span>
                            <strong>Se ofrece a los clientes</strong>
                            <small>Aparece en el directorio de quien tenga un proyecto en su zona.</small>
                        </span>
                    </label>
                </div>
            </section>

            <div class="solar-installer-form__actions">
                <button type="submit" class="solar-button">{{ $editing ? 'Guardar cambios' : 'Agregar instalador' }}</button>
                <a href="{{ route('installers.index') }}" class="solar-button-ghost" wire:navigate>Cancelar</a>
            </div>
        </form>
    </div>
</x-layouts::app>
