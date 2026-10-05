{{--
    Allied installers (ADR-0021): the client picks one of their projects and asks for a quote over the
    estimate that is already calculated. The 3D illustration (ADR-0012) shows that project's roof; it is
    the same <x-solar-scene> of the landing and the form, so no new scene is loaded.

    Params: $projects, $project, $quotable, $municipalityName, $scene, $installers
    (App\Actions\Installers\DescribeInstallerDirectory).
--}}
@php
    $money = fn (float $cop): string => '$'.number_format(round($cop, -3), 0, ',', '.');
    $kwh = fn (float $value): string => number_format($value, $value < 10 ? 1 : 0, ',', '.');
    $date = fn ($value): string => \Illuminate\Support\Carbon::parse($value)->locale('es')->isoFormat('D [de] MMMM');
@endphp

<x-layouts::app :title="__('Instaladores')">
    @push('head')
        @vite('resources/js/solar-scene/scene.js')
    @endpush

    <div class="solar-page solar-installers">
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Centro solar</p>
                <h1 class="solar-title mt-0">Instaladores de tu zona</h1>
                <p class="solar-subtitle mt-2 max-w-3xl">
                    Elige uno de tus proyectos y pídele cotización a los instaladores que cubren su municipio.
                    Llegas con tu consumo, tu techo y un presupuesto de referencia ya calculados.
                </p>
            </div>
            @can('administer-platform')
                <a href="{{ route('installers.create') }}" class="solar-button" wire:navigate>Agregar instalador</a>
            @endcan
        </div>

        {{-- The network is not real yet (ADR-0021); this notice goes away with the first real ally. --}}
        <p class="solar-installers-notice" role="note">
            <strong>Datos de ejemplo.</strong> Estamos armando la red de instaladores aliados de La Guajira.
            Los de esta lista sirven para probar el flujo: todavía no son empresas reales.
        </p>

        @if ($errors->any())
            <div class="solar-alert solar-alert-danger" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        {{-- Without a project there is nothing to quote, but the list is still worth reading (and an
             administrator keeps it from here, with or without projects of their own). --}}
        @if ($projects->isEmpty())
            <section class="solar-card solar-installers-empty">
                <h2 class="text-lg font-semibold text-[color:var(--solar-text)]">Primero, tu proyecto</h2>
                <p class="solar-subtitle mt-2">
                    El instalador cotiza sobre una estimación: cuánto consumes, cuánto techo tienes y dónde estás.
                    Crea tu proyecto y vuelve aquí.
                </p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ route('solar-projects.create') }}" class="solar-button" wire:navigate>Crear un proyecto</a>
                    <a href="{{ route('guides.energy-bill') }}" class="solar-button-ghost" wire:navigate>Guía del recibo</a>
                </div>
            </section>
        @else
            <section class="solar-card solar-installers-project">
                <div class="solar-installers-project__data">
                    <form method="GET" action="{{ route('installers.index') }}" class="solar-field" data-autosubmit>
                        <label class="solar-field-label" for="proyecto">Cotizar este proyecto</label>
                        <select name="proyecto" id="proyecto" class="solar-input">
                            @foreach ($projects as $option)
                                <option value="{{ $option->id }}" @selected($project?->is($option))>{{ $option->name }}</option>
                            @endforeach
                        </select>
                        <noscript><button type="submit" class="solar-button-ghost mt-2">Cambiar</button></noscript>
                    </form>

                    <dl class="solar-installers-figures">
                        <div>
                            <dt>Municipio</dt>
                            <dd>{{ $municipalityName ?? 'Sin definir' }}</dd>
                        </div>
                        <div>
                            <dt>Consumo</dt>
                            <dd>{{ $quotable ? $kwh($project->monthlyConsumption()).' kWh/mes' : 'Sin definir' }}</dd>
                        </div>
                        @if ($scene)
                            <div>
                                <dt>Paneles</dt>
                                <dd>{{ $scene['panels'] ?: '—' }}</dd>
                            </div>
                        @endif
                        @if ($project->estimated_installation_cost > 0)
                            <div>
                                <dt>Referencia</dt>
                                <dd>{{ $money((float) $project->estimated_installation_cost) }}</dd>
                            </div>
                        @endif
                    </dl>

                    @unless ($quotable)
                        <p class="solar-installers-warning">
                            Este proyecto todavía no tiene consumo, así que no hay nada que cotizar.
                            <a href="{{ route('solar-projects.consumption', $project) }}" wire:navigate>Defínelo en la pestaña Consumo</a>.
                        </p>
                    @endunless
                </div>

                @if ($scene)
                    <x-solar-scene
                        mode="preview"
                        class="solar-installers-scene"
                        :property-type="$project->property_type"
                        :panels="$scene['panels']"
                        :panels-fit="$scene['panelsFit']"
                        :roof-area="$scene['roofArea']"
                        :panel-area="$scene['panelArea']"
                        :label="'Ilustración del techo de '.$project->name.' con sus paneles'"
                        note="Lo que verá el instalador"
                    />
                @endif
            </section>
        @endif

        <div class="solar-installers-grid">
            @forelse ($installers as $installer)
            <article @class(['solar-card', 'solar-installer-card', 'is-requested' => $installer['requested'], 'is-hidden' => ! $installer['active']]) data-test="installer-{{ $installer['id'] }}">
                        <header class="solar-installer-card__head">
                            <div>
                                <h2>
                                    {{ $installer['name'] }}
                                    @unless ($installer['active'])
                                        <span class="solar-installer-badge is-muted">Oculto</span>
                                    @endunless
                                </h2>
                                @if ($installer['tagline'])
                                    <p class="solar-installer-card__tagline">{{ $installer['tagline'] }}</p>
                                @endif
                            </div>
                            @if ($installer['yearsExperience'])
                                <span class="solar-installer-years">{{ $installer['yearsExperience'] }} años</span>
                            @endif
                        </header>

                        @if ($installer['description'])
                            <p class="solar-installer-card__text">{{ $installer['description'] }}</p>
                        @endif

                        <p class="solar-installer-card__coverage">
                            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.6"/></svg>
                            {{ $installer['coverage'] }}
                        </p>

                        @if ($installer['requested'])
                            <div class="solar-installer-card__contact">
                                <p class="solar-installer-badge">{{ $installer['statusLabel'] }} · {{ $date($installer['requestedAt']) }}</p>
                                @if ($installer['contactName'])
                                    <p class="solar-installer-card__who">{{ $installer['contactName'] }}</p>
                                @endif
                                <div class="solar-installer-card__links">
                                    @if ($installer['whatsapp'])
                                        <a href="https://wa.me/{{ $installer['whatsapp'] }}" target="_blank" rel="noopener">WhatsApp</a>
                                    @endif
                                    @if ($installer['phone'])
                                        <a href="tel:{{ preg_replace('/\s+/', '', $installer['phone']) }}">{{ $installer['phone'] }}</a>
                                    @endif
                                    @if ($installer['email'])
                                        <a href="mailto:{{ $installer['email'] }}">{{ $installer['email'] }}</a>
                                    @endif
                                </div>
                            </div>
                        @elseif ($quotable && $installer['active'])
                            <form method="POST" action="{{ route('installers.quote-requests.store', $installer['id']) }}" class="solar-installer-card__action">
                                @csrf
                                <input type="hidden" name="solar_project_id" value="{{ $project->id }}">
                                <button type="submit" class="solar-button">Pedir cotización</button>
                                <span>Verás sus datos de contacto al pedirla.</span>
                            </form>
                        @else
                            <p class="solar-installer-card__action is-blocked">
                                @if (! $installer['active'])
                                    No se ofrece a los clientes.
                                @elseif ($project === null)
                                    Crea un proyecto para pedirle cotización.
                                @else
                                    Define el consumo del proyecto para pedirle cotización.
                                @endif
                            </p>
                        @endif

                        @can('administer-platform')
                            <div class="solar-installer-card__admin">
                                <span class="solar-installer-card__requests">
                                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l2 3h6l2-3h4"/><path d="M5 6h14l2 6v6H3v-6z"/></svg>
                                    {{ match ($installer['requests']) { 0 => 'Sin solicitudes', 1 => '1 solicitud', default => $installer['requests'].' solicitudes' } }}
                                </span>
                                <a href="{{ route('installers.edit', $installer['id']) }}" class="solar-installer-edit" wire:navigate>
                                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>
                                    Editar<span class="sr-only"> {{ $installer['name'] }}</span>
                                </a>
                            </div>
                        @endcan
                    </article>
                @empty
                    <section class="solar-card solar-installers-empty">
                        <h2 class="text-lg font-semibold text-[color:var(--solar-text)]">
                            {{ $municipalityName !== null ? 'Todavía nadie cubre '.$municipalityName : 'Aún no hay instaladores' }}
                        </h2>
                        <p class="solar-subtitle mt-2">
                            Seguimos sumando instaladores aliados. Tu proyecto queda guardado: cuando alguno cubra tu
                            municipio, aparecerá aquí.
                        </p>
                    </section>
                @endforelse
        </div>
    </div>
</x-layouts::app>
