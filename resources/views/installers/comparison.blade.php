{{--
    Las cotizaciones de un proyecto, lado a lado (ADR-0028, ADR-0029).

    Es la primera tabla ancha de la app, y es una tabla de verdad: las filas son los campos del
    ADR-0027 y las columnas los instaladores, así que el mismo campo se lee de una vez en todas las
    ofertas. El cliente elige cuáles entran, y arriba la app recomienda una y dice por qué, con
    razones que se pueden comprobar fila por fila.

    Cosas que no hay que deshacer:

    - Las etiquetas de fila son `<th scope="row">` y quedan pegadas a la izquierda mientras las
      columnas se desplazan: un valor sin su etiqueta no compara nada. El encabezado no se queda
      fijo a propósito —un contenedor que se desplaza de lado no puede además fijar su cabecera—,
      así que el nombre del instalador vuelve al pie, junto a la forma de escribirle.
    - Lo mejor de cada fila se marca con una forma y una palabra, nunca solo con color, y lleva su
      texto para el lector de pantalla. La ventaja (`+13`, `−7,6 M`) dice cuánto le saca a la mejor
      de las demás, como el cara a cara del diseño.
    - Una celda vacía dice *No lo dice* (`<x-installers.compare-cell>`): una cotización que no
      declara garantías es información.

    Params: App\Actions\Installers\CompareProjectQuotes.
--}}
@php
    use App\Domain\Installers\QuoteComparison;

    $money = fn (float $cop): string => '$'.number_format(round($cop, -3), 0, ',', '.');
    $kwh = fn (float $value): string => number_format($value, $value < 10 ? 1 : 0, ',', '.');
    $kw = fn (float $value): string => number_format($value, 2, ',', '.');
    $date = fn ($value): string => \Illuminate\Support\Carbon::parse($value)->locale('es')->isoFormat('D [de] MMMM');
    $span = count($columns) + 1;

    // "es la más barata, cubre el RETIE y se paga en menos tiempo".
    $reasons = function (array $list): string {
        if (count($list) === 1) {
            return $list[0];
        }

        $last = array_pop($list);

        return implode(', ', $list).' y '.$last;
    };

    $chosenIds = collect($columns)->pluck('quoteRequestId')->all();
    $recommended = $recommendation !== null ? $columns[$recommendation['index']] : null;

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
                    {{ count($columns) }} de {{ count($available) }} cotizaciones para {{ $project->name }}
                    @if ($municipalityName) · {{ $municipalityName }} @endif
                    · {{ $kwh($monthlyKwh) }} kWh/mes
                    @if ($requiredPowerKw) · tu consumo pide {{ $kw($requiredPowerKw) }} kW @endif
                </p>
            </div>
        </div>

        {{-- La recomendación: una, con nombre, y con el porqué en frases que están en la tabla. --}}
        @if ($recommended)
            <section class="solar-card solar-compare-pick">
                <div class="solar-compare-pick__head">
                    <p class="solar-compare-pick__label">Te recomendamos</p>
                    <h2 class="solar-compare-pick__name">{{ $recommended['installerName'] }}</h2>
                    <p class="solar-compare-pick__price">
                        {{ $money($recommended['amountCop']) }}
                        @if ($recommended['vatIncluded'] === false)
                            <span class="solar-compare-pick__vat">más IVA</span>
                        @endif
                    </p>
                </div>

                <div class="solar-compare-pick__body">
                    <p class="solar-compare-pick__why">Porque {{ $reasons($recommendation['reasons']) }}.</p>

                    @if ($recommendation['cheaperIndex'] !== null)
                        {{-- Lo primero que el cliente va a notar es que no es la más barata: lo
                             decimos nosotros antes, con la diferencia y el porqué. --}}
                        <p class="solar-compare-pick__against">
                            {{ $columns[$recommendation['cheaperIndex']]['installerName'] }} te cuesta
                            <strong>{{ $money(abs($recommendation['cheaperByCop'])) }} menos</strong>, pero
                            {{ $recommendation['tradeoff'] }}.
                        </p>
                    @endif

                    <p class="solar-compare-pick__note">
                        Es una recomendación, no la última palabra: cada razón está en su fila.
                    </p>
                </div>
            </section>
        @endif

        @unless ($calculated)
            {{-- Lo único que no está en ninguna fila: sin ahorro calculado, la del retorno
                 desaparece, y una fila que falta sin explicación se lee como un error. --}}
            <p class="solar-compare-missing">
                Falta calcular tu proyecto: sin su ahorro no sabemos en cuánto se paga cada precio.
                <a href="{{ route('solar-projects.show', $project) }}" wire:navigate>Calcular</a>
            </p>
        @endunless

        {{-- Cuáles entran. Son casillas de un GET normal: la comparación vive en la URL, así que se
             puede compartir y el botón de atrás funciona. --}}
        @if (count($available) > QuoteComparison::MINIMUM)
            <form method="GET" action="{{ route('installers.quotes.compare', $project) }}" class="solar-card solar-compare-picker" data-autosubmit>
                <p class="solar-compare-picker__label">Cuáles comparar</p>
                <div class="solar-compare-picker__options">
                    @foreach ($available as $option)
                        <label class="solar-compare-picker__option">
                            <input type="checkbox" name="cotizaciones[]" value="{{ $option['id'] }}" @checked($option['chosen'])>
                            <span>
                                {{ $option['name'] }}
                                <small>
                                    {{ $money($option['amountCop']) }}
                                    @if ($option['expired']) · vencida @endif
                                </small>
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="solar-compare-picker__hint">Con menos de dos marcadas se comparan todas.</p>
                <noscript><button type="submit" class="solar-button-ghost">Actualizar la comparación</button></noscript>
            </form>
        @endif

        <section class="solar-card solar-compare-card">
            @if ($faceOff)
                {{-- Dos cotizaciones se leen enfrentadas, con la etiqueta en el medio (ADR-0029). --}}
                <x-installers.face-off
                    :groups="$groups"
                    :columns="$columns"
                    :verdict="$verdict"
                    :recommendation="$recommendation"
                />
            @else
            {{-- El teclado tiene que poder llegar al desplazamiento, así que el contenedor se enfoca. --}}
            <div class="solar-compare__scroll" tabindex="0" role="region" aria-label="Tabla comparativa de cotizaciones; se desplaza de lado">
                <table class="solar-compare" data-quote-comparison>
                    <caption class="sr-only">
                        Cotizaciones recibidas para {{ $project->name }}, una por columna, con lo mejor de cada fila marcado.
                    </caption>
                    <thead>
                        <tr>
                            <th scope="col" class="solar-compare__corner">Qué comparas</th>
                            @foreach ($columns as $index => $column)
                                <th scope="col" @class([
                                    'solar-compare__installer',
                                    'is-expired' => $column['expired'],
                                    'is-recommended' => $recommendation !== null && $recommendation['index'] === $index,
                                ])>
                                    @if ($recommendation !== null && $recommendation['index'] === $index)
                                        <span class="solar-compare__recommended">Recomendada</span>
                                    @endif
                                    <span class="solar-compare__installer-name">{{ $column['installerName'] }}</span>
                                    <span class="solar-compare__installer-meta">
                                        @if ($column['yearsExperience']) {{ $column['yearsExperience'] }} años · @endif
                                        {{ $column['statusLabel'] }}
                                    </span>
                                    {{-- Pegado al nombre, encima del precio: con el dato tres grupos más
                                         abajo, el total de esta columna se compara como si fuera final. --}}
                                    @if ($column['vatIncluded'] === false)
                                        <span class="solar-compare__vat">IVA aparte</span>
                                    @endif
                                    @if ($column['expired'])
                                        <span class="solar-compare__expired">Precio vencido el {{ $date($column['validUntil']) }}</span>
                                    @endif

                                    {{-- El veredicto de esta columna: dos cuentas, no un puntaje. --}}
                                    <span class="solar-compare__score">
                                        @if ($verdict[$index]['expired'])
                                            {{-- "Gana 0 de 15" se leería como mala cotización, y lo
                                                 que pasa es que su precio ya no compite. --}}
                                            <span>No compite: el precio venció</span>
                                        @else
                                            <span>Gana <strong>{{ $verdict[$index]['rowsWon'] }}</strong> de {{ $verdict[$index]['rowsCompared'] }} filas</span>
                                        @endif
                                        <span>Declara <strong>{{ $verdict[$index]['declared'] }}</strong> de {{ $verdict[$index]['declarable'] }} datos</span>
                                    </span>

                                    @if (count($columns) > QuoteComparison::MINIMUM)
                                        <a
                                            class="solar-compare__drop"
                                            href="{{ route('installers.quotes.compare', ['solarProject' => $project, 'cotizaciones' => array_values(array_diff($chosenIds, [$column['quoteRequestId']]))]) }}"
                                            wire:navigate
                                        >
                                            <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                                            <span class="sr-only">Quitar {{ $column['installerName'] }} de la comparación</span>
                                        </a>
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
                                            <x-installers.compare-delta :advantage="$cell['advantage']" :format="$row['format']" />
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

                    {{-- El nombre vuelve donde termina la decisión: hablando con ellos (ADR-0005). --}}
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
            @endif

            @if ($referenceCop !== null)
                <p class="solar-inbox-note mt-3">
                    Nuestro presupuesto de referencia es {{ $money($referenceCop) }}: una regla de medida, no una
                    cotización.
                </p>
            @endif
        </section>

        {{-- Lo que nadie declaró no merece una fila vacía en cada columna, pero sí merece decirse
             una vez: son las preguntas que al cliente le quedan por hacer. --}}
        @if ($silent !== [])
            <section class="solar-card solar-compare-silent">
                <h2 class="solar-quote-heading">Lo que ninguna dice</h2>
                <p class="solar-subtitle mt-2">
                    Ninguna lo declara: no hay nada que comparar, pero sí qué preguntar.
                </p>
                <ul class="solar-compare-silent__list">
                    @foreach ($silent as $label)
                        <li>{{ $label }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <p class="solar-compare-footer">
            Comparar no es aceptar: el acuerdo lo cierras con el instalador.
        </p>
    </div>
</x-layouts::app>
