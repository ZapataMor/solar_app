{{--
    Every quote of one project, side by side (ADR-0028).

    It is the first wide table of the app, and it is a real table: the rows are the fields of
    ADR-0027 and the columns are the installers, so the same field is read across every offer at
    once. Three things that should not be undone:

    - The row labels are `<th scope="row">` and they stay stuck to the left while the columns scroll:
      a value without its label compares nothing. The header is not sticky on purpose — a container
      that scrolls sideways cannot also keep its header on the page — so the installer's name comes
      back in the footer, next to the way to write to them.
    - The best of each row is marked with a shape and a word, never with colour alone, and it carries
      its own text for a screen reader. The app marks the best of each row and never the best quote.
    - An empty cell says *No lo dice* (`<x-installers.compare-cell>`), because a quote that does not
      declare warranties is information.

    Params: App\Actions\Installers\CompareProjectQuotes.
--}}
@php
    $money = fn (float $cop): string => '$'.number_format(round($cop, -3), 0, ',', '.');
    $kwh = fn (float $value): string => number_format($value, $value < 10 ? 1 : 0, ',', '.');
    $kw = fn (float $value): string => number_format($value, 2, ',', '.');
    $date = fn ($value): string => \Illuminate\Support\Carbon::parse($value)->locale('es')->isoFormat('D [de] MMMM');
    $span = count($columns) + 1;
@endphp

<x-layouts::app :title="'Comparar cotizaciones de '.$project->name">
    <div class="solar-page solar-quote-compare-page">
        <div class="solar-page-header">
            <div>
                <a href="{{ route('installers.index', ['proyecto' => $project->id]) }}" class="solar-project-nav__back" wire:navigate>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
                    Instaladores
                </a>
                <p class="solar-kicker mt-3">Centro solar</p>
                <h1 class="solar-title mt-0">Compara tus cotizaciones</h1>
                <p class="solar-subtitle mt-2 max-w-3xl">
                    {{ count($columns) }} cotizaciones para {{ $project->name }}
                    @if ($municipalityName) · {{ $municipalityName }} @endif
                    · {{ $kwh($monthlyKwh) }} kWh/mes
                    @if ($requiredPowerKw) · tu consumo pide {{ $kw($requiredPowerKw) }} kW @endif
                </p>
            </div>
        </div>

        {{-- The app compares; it does not recommend. ADR-0005 charges for the closing, so a single
             score ordering the quotes would be the platform tipping a decision it profits from. --}}
        <p class="solar-compare-intro">
            Marcamos <strong>lo mejor de cada fila</strong>, no la mejor cotización: el retorno más rápido, la
            garantía más larga, el plazo más corto. Cuál te conviene depende de lo que valores.
        </p>

        {{-- What has to be read before the totals: two quotes with different scope are not two
             prices for the same thing. --}}
        @if ($caveats['mixedLegalization'] || $caveats['mixedBattery'] || $caveats['expired'] > 0 || ($caveats['powerSpreadKw'] ?? 0) >= 1)
            <div class="solar-compare-caveats" role="note">
                @if ($caveats['mixedLegalization'])
                    <p>
                        <strong>No todas legalizan la instalación.</strong> Unas cubren el RETIE y el trámite con el
                        operador de red, y otras los dejan afuera. Ese trámite y el medidor bidireccional son varios
                        millones de pesos: la más barata puede terminar costando más.
                    </p>
                @endif
                @if ($caveats['mixedBattery'])
                    <p>
                        <strong>Unas llevan baterías y otras no.</strong> Las baterías pueden ser buena parte del
                        precio. Compara primero el precio por kW.
                    </p>
                @endif
                @if (($caveats['powerSpreadKw'] ?? 0) >= 1)
                    <p>
                        <strong>Te proponen sistemas de distinto tamaño</strong>, con hasta
                        {{ $kw($caveats['powerSpreadKw']) }} kW de diferencia. El total no se compara solo: mira el
                        precio por kW y la producción.
                    </p>
                @endif
                @if ($caveats['expired'] > 0)
                    <p>
                        {{ $caveats['expired'] === 1 ? 'Una cotización ya venció' : $caveats['expired'].' cotizaciones ya vencieron' }}.
                        {{ $caveats['expired'] === 1 ? 'Sigue' : 'Siguen' }} en la tabla porque
                        {{ $caveats['expired'] === 1 ? 'la pediste' : 'las pediste' }}, pero su precio ya no es un
                        precio: {{ $caveats['expired'] === 1 ? 'no entra' : 'no entran' }} en lo mejor de cada fila.
                    </p>
                @endif
            </div>
        @endif

        @unless ($calculated)
            <p class="solar-compare-caveats" role="note">
                <strong>Falta el cálculo de tu proyecto.</strong> Sin él no sabemos en cuánto tiempo se paga cada
                precio, y esa es la fila que más decide.
                <a href="{{ route('solar-projects.show', $project) }}" wire:navigate>Calcular mi proyecto</a>.
            </p>
        @endunless

        <section class="solar-card solar-compare-card">
            {{-- Keyboard users need to reach the scroll, so the container is focusable and named. --}}
            <div class="solar-compare__scroll" tabindex="0" role="region" aria-label="Tabla comparativa de cotizaciones; se desplaza de lado">
                <table class="solar-compare" data-quote-comparison>
                    <caption class="sr-only">
                        Cotizaciones recibidas para {{ $project->name }}, una por columna, con lo mejor de cada fila marcado.
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col" class="solar-compare__corner">Qué comparas</th>
                            @foreach ($columns as $column)
                                <th scope="col" @class(['solar-compare__installer', 'is-expired' => $column['expired']])>
                                    <span class="solar-compare__installer-name">{{ $column['installerName'] }}</span>
                                    <span class="solar-compare__installer-meta">
                                        @if ($column['yearsExperience']) {{ $column['yearsExperience'] }} años · @endif
                                        {{ $column['statusLabel'] }}
                                    </span>
                                    @if ($column['expired'])
                                        <span class="solar-compare__expired">Precio vencido el {{ $date($column['validUntil']) }}</span>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    @foreach ($groups as $group)
                        <tbody>
                            <tr class="solar-compare__group">
                                {{-- El texto va en un span pegado a la izquierda: la celda ocupa toda
                                     la tabla, y sin esto el título del grupo se pierde al desplazar. --}}
                                <th scope="rowgroup" colspan="{{ $span }}">
                                    <span class="solar-compare__group-text">{{ $group['title'] }}</span>
                                    @if ($group['note'])
                                        <small>{{ $group['note'] }}</small>
                                    @endif
                                </th>
                            </tr>
                            @foreach ($group['rows'] as $row)
                                <tr data-compare-row="{{ $row['key'] }}">
                                    <th scope="row" class="solar-compare__label">
                                        {{ $row['label'] }}
                                        @if ($row['hint'])
                                            <small>{{ $row['hint'] }}</small>
                                        @endif
                                    </th>
                                    @foreach ($row['cells'] as $index => $cell)
                                        <td @class([
                                            'solar-compare__cell',
                                            'is-best' => $cell['best'],
                                            'is-missing' => $cell['missing'],
                                            'is-expired' => $columns[$index]['expired'],
                                        ])>
                                            <x-installers.compare-cell :cell="$cell" :format="$row['format']" />
                                            @if ($cell['best'])
                                                <span class="solar-compare__best">
                                                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12.5l5 5L20 6.5"/></svg>
                                                    Lo mejor<span class="sr-only"> de esta fila: {{ $row['label'] }}</span>
                                                </span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach

                    {{-- The name comes back where the decision ends: talking to them (ADR-0005). --}}
                    <tfoot>
                        <tr>
                            <th scope="row" class="solar-compare__label">Hablar y leerla completa</th>
                            @foreach ($columns as $column)
                                {{-- El contenido va en un div: un `display: flex` sobre el <td> lo saca
                                     de la tabla y las tres columnas se apilan en una. --}}
                                <td class="solar-compare__cell">
                                    <div class="solar-compare__actions">
                                        <span class="solar-compare__actions-name">{{ $column['installerName'] }}</span>
                                        <a href="{{ route('installers.quotes.show', $column['quoteRequestId']) }}" wire:navigate>
                                            Ver completa<span class="sr-only"> la cotización de {{ $column['installerName'] }}</span>
                                        </a>
                                        @if ($column['whatsapp'])
                                            <a href="https://wa.me/{{ $column['whatsapp'] }}" target="_blank" rel="noopener">
                                                WhatsApp<span class="sr-only"> de {{ $column['installerName'] }}</span>
                                            </a>
                                        @elseif ($column['phone'])
                                            <a href="tel:{{ preg_replace('/\s+/', '', $column['phone']) }}">{{ $column['phone'] }}</a>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    </tfoot>
                </table>
            </div>

            @if ($referenceCop !== null)
                <p class="solar-inbox-note mt-3">
                    Nuestro presupuesto de referencia para este proyecto es {{ $money($referenceCop) }}. Es una regla
                    de medida, no una cotización: una más alta puede incluir trabajos que la referencia no contempla.
                </p>
            @endif
        </section>

        {{-- What nobody declared does not deserve an empty row in every column, but it does deserve
             to be said once: these are the questions the client still has to ask. --}}
        @if ($silent !== [])
            <section class="solar-card solar-compare-silent">
                <h2 class="solar-quote-heading">Lo que ninguna dice</h2>
                <p class="solar-subtitle mt-2">
                    Ninguna de las {{ count($columns) }} cotizaciones declara esto. No está en la tabla porque no hay
                    nada que comparar, pero sí hay qué preguntar.
                </p>
                <ul class="solar-compare-silent__list">
                    @foreach ($silent as $label)
                        <li>{{ $label }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <p class="solar-compare-footer">
            Comparar no es aceptar. Cuando decidas, escríbele al instalador: el acuerdo se cierra con él, no aquí.
        </p>
    </div>
</x-layouts::app>
