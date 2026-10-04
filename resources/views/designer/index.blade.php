{{--
    Development only (gate `design-3d`): the place where the 3D models are designed.
    First model: how a hybrid solar system with batteries works through a day, in a cutaway house
    (resources/js/system-scene). The picture carries the readout (the four numbers up on the left, the clock,
    what the system is doing); the time runs under it, and the scenes, conditions and equipment sit on the
    right (controls.js). The existing models (solar-scene, appliance-scene, station-scene) are not here.
--}}
@php
    $presets = [
        'noon' => ['Mediodía', 'Sol fuerte: los paneles producen de sobra y lo que sobra va a la red.'],
        'sunset' => ['Atardecer', 'Se va el sol: los paneles ya no alcanzan y las baterías empiezan a ayudar.'],
        'night' => ['Noche', 'Sin sol: la casa funciona con la energía guardada en las baterías.'],
        'empty' => ['Baterías vacías', 'De madrugada, con las baterías en su reserva: la casa toma la electricidad de la red.'],
        'blackout' => ['Apagón', 'Baterías vacías y la red caída: la casa se queda sin electricidad.'],
    ];
    $devices = ['lamp' => 'Foco', 'tv' => 'Televisor', 'fridge' => 'Nevera'];
    $stats = ['pv' => 'Paneles', 'load' => 'Casa', 'battery' => 'Baterías', 'grid' => 'Red'];
    $legend = [
        '#ffd34d' => 'Luz del sol',
        '#ffb020' => 'Corriente continua',
        '#38bdf8' => 'Corriente alterna',
        '#4ade80' => 'Sale a la red',
        '#c084fc' => 'Entra de la red',
    ];
@endphp

<x-layouts::app :title="__('Diseñador 3D')">
    @push('head')
        @vite('resources/js/system-scene/scene.js')
    @endpush

    <div class="solar-page">
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Desarrollo</p>
                <h1 class="solar-title mt-0">Diseñador 3D</h1>
                <p class="solar-subtitle mt-2 max-w-3xl">
                    Aquí se diseñan los modelos 3D de la app. Solo se ve en modo desarrollo.
                </p>
            </div>
        </div>

        <section class="solar-card">
            <h2 class="text-lg font-semibold text-[color:var(--solar-text)]">Cómo funciona un sistema solar</h2>
            <p class="solar-subtitle mt-1 max-w-3xl">
                Una casa en corte: los equipos en el cuarto técnico, los consumos en la sala. Cambia la hora, las nubes
                o la red y mira qué hace el sistema; señala cualquier parte para saber qué es y qué está haciendo ahora.
            </p>

            <figure class="solar-system-scene mt-4" data-system-scene aria-label="Animación de un sistema solar con baterías en una casa en corte">
                <div class="solar-system-scene__main">
                    <div class="solar-system-scene__stage" data-system-stage>
                        <p class="solar-system-scene__fallback">Cargando la animación 3D… Si no aparece, tu navegador no tiene WebGL.</p>

                        <dl class="solar-system-hud" aria-label="La energía de la casa ahora">
                            @foreach ($stats as $key => $label)
                                <div class="solar-system-hud__item" data-system-stat="{{ $key }}">
                                    <dt>{{ $label }}</dt>
                                    <dd>
                                        <strong data-system-stat-value="{{ $key }}">—</strong>
                                        <span data-system-stat-note="{{ $key }}"></span>
                                    </dd>
                                </div>
                            @endforeach
                        </dl>

                        <div class="solar-system-scene__clock" aria-hidden="true">
                            <svg class="is-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" /></svg>
                            <svg class="is-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" /></svg>
                            <strong data-system-clock>10:30 a. m.</strong>
                            <span data-system-phase>Mañana</span>
                        </div>

                        <p class="solar-system-scene__status" data-system-status role="status" aria-live="polite"></p>

                        <div class="solar-system-scene__tip" data-system-tip hidden>
                            <strong data-system-tip-title></strong>
                            <span data-system-tip-text></span>
                            <em data-system-tip-live hidden></em>
                        </div>
                    </div>

                    <div class="solar-system-transport">
                        <button type="button" class="solar-button solar-system-play" data-system-play aria-pressed="false">
                            <svg class="is-play" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
                            <svg class="is-pause" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 5h4v14H6zM14 5h4v14h-4z" /></svg>
                            <span data-system-play-label>Ver un día completo</span>
                        </button>
                        <input type="range" min="0" max="24" step="0.05" value="10.5" data-system-time aria-label="Hora del día">
                        <output data-system-clock>10:30 a. m.</output>
                    </div>
                </div>

                <aside class="solar-system-panel" aria-label="Controles de la animación">
                    <div class="solar-system-panel__group">
                        <h3 class="solar-system-panel__title">Escenas</h3>
                        <div class="solar-system-panel__scenes" role="group" aria-label="Escenas">
                            @foreach ($presets as $key => [$label, $hint])
                                <button type="button" class="solar-system-chip" data-system-preset="{{ $key }}" aria-pressed="false" title="{{ $hint }}">{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div class="solar-system-panel__group">
                        <h3 class="solar-system-panel__title">Condiciones</h3>
                        <div class="solar-system-chips">
                            <label class="solar-system-chip solar-system-chip--toggle"><input type="checkbox" data-system-toggle="clouds"><span>Nublado</span></label>
                            <label class="solar-system-chip solar-system-chip--toggle"><input type="checkbox" data-system-toggle="grid-down"><span>Red caída</span></label>
                        </div>
                        <label class="solar-system-slider">
                            <span>Carga de las baterías</span>
                            <input type="range" min="10" max="100" step="1" value="72" data-system-battery>
                            <output data-system-battery-value>72 %</output>
                        </label>
                    </div>

                    <div class="solar-system-panel__group">
                        <h3 class="solar-system-panel__title">Equipos encendidos</h3>
                        <div class="solar-system-chips">
                            @foreach ($devices as $key => $label)
                                <label class="solar-system-chip solar-system-chip--toggle"><input type="checkbox" data-system-toggle="{{ $key }}" checked><span>{{ $label }}</span></label>
                            @endforeach
                        </div>
                    </div>

                    <ul class="solar-system-legend" aria-label="Qué es cada color">
                        @foreach ($legend as $color => $label)
                            <li><i style="background: {{ $color }}"></i>{{ $label }}</li>
                        @endforeach
                    </ul>
                </aside>
            </figure>
        </section>
    </div>
</x-layouts::app>
