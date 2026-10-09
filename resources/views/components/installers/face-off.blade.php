{{--
    Dos cotizaciones enfrentadas (ADR-0029): la que el cliente está leyendo contra otra suya.

    Es una tabla de tres columnas con la etiqueta en el medio, como se lee un cara a cara: el valor
    de cada lado y, debajo, cuánto le saca al otro. La etiqueta central es `<th scope="row">`, así
    que el lector de pantalla sigue anunciando de qué fila es cada valor aunque no vaya primera.

    Solo trae lo que decide —el precio y lo que ese precio cubre—: la tabla entera, con garantías y
    condiciones, está a un clic (ADR-0028).

    Props: $faceOff (App\Actions\Installers\DescribeQuoteForClient) y $project.
--}}
@props(['faceOff', 'project'])

@php
    $money = fn (float $cop): string => '$'.number_format(round($cop, -3), 0, ',', '.');
    $kw = fn (float $value): string => number_format($value, 2, ',', '.');

    $mine = $faceOff['mine'];
    $rival = $faceOff['rival'];
    $sides = [$mine, $rival];

    $reasons = function (array $list): string {
        if (count($list) === 1) {
            return $list[0];
        }

        $last = array_pop($list);

        return implode(', ', $list).' y '.$last;
    };
@endphp

<div class="solar-face-off">
    <table class="solar-face-off__table">
        <caption class="sr-only">
            {{ $mine['name'] }} frente a {{ $rival['name'] }}, campo por campo.
        </caption>
        <thead>
            <tr>
                @foreach ($sides as $position => $side)
                    @if ($position === 1)
                        <td class="solar-face-off__vs"><span>vs</span></td>
                    @endif
                    <th scope="col" @class(['solar-face-off__side', 'is-expired' => $side['expired']])>
                        <span class="solar-face-off__name">{{ $side['name'] }}</span>
                        <span class="solar-face-off__price">{{ $money($side['amountCop']) }}</span>
                        <span class="solar-face-off__meta">
                            @if ($side['powerKw']) {{ $kw($side['powerKw']) }} kW · @endif
                            {{ $side['includesBattery'] ? 'con baterías' : 'sin baterías' }}
                        </span>
                        @if ($side['expired'])
                            <span class="solar-compare__expired">Precio vencido</span>
                        @endif
                        <span class="solar-face-off__score">
                            Gana <strong>{{ $faceOff['verdict'][$position]['rowsWon'] }}</strong>
                            de {{ $faceOff['verdict'][$position]['rowsCompared'] }} filas
                        </span>
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($faceOff['rows'] as $row)
                <tr data-face-off-row="{{ $row['key'] }}">
                    @foreach ($row['cells'] as $position => $cell)
                        @if ($position === 1)
                            <th scope="row" class="solar-face-off__label">{{ $row['label'] }}</th>
                        @endif
                        <td @class([
                            'solar-face-off__cell',
                            'is-best' => $cell['best'],
                            'is-missing' => $cell['missing'],
                            'is-expired' => $sides[$position]['expired'],
                        ])>
                            <x-installers.compare-cell :cell="$cell" :format="$row['format']" />
                            <x-installers.compare-delta :advantage="$cell['advantage']" :format="$row['format']" />
                            @if ($cell['best'])
                                {{-- Con forma y palabra, no solo con el fondo verde: el color no es
                                     la única marca, y el lector de pantalla también lo anuncia. --}}
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
    </table>

    {{-- Con más de dos cotizaciones, contra cuál enfrentarla. Son enlaces con su ancla, así que
         la página vuelve a abrirse en este mismo paso y funciona sin JavaScript. --}}
    @if (count($faceOff['rivals']) > 1)
        <nav class="solar-face-off__rivals" aria-label="Contra cuál comparar">
            <span>Compárala contra:</span>
            @foreach ($faceOff['rivals'] as $option)
                <a
                    href="{{ route('installers.quotes.show', ['quoteRequest' => request()->route('quoteRequest'), 'vs' => $option['id']]) }}#paso-compara"
                    @class(['is-current' => $option['current']])
                    @if ($option['current']) aria-current="true" @endif
                    wire:navigate
                >{{ $option['name'] }}</a>
            @endforeach
        </nav>
    @endif

    @if ($faceOff['recommendation'])
        @php($pick = $faceOff['recommendation'])
        <p class="solar-face-off__pick">
            <strong>Te recomendamos {{ $sides[$pick['index']]['name'] }}</strong>
            porque {{ $reasons($pick['reasons']) }}.
            @if ($pick['cheaperIndex'] !== null)
                {{ $sides[$pick['cheaperIndex']]['name'] }} te cuesta
                {{ $money(abs($pick['cheaperByCop'])) }} menos, pero {{ $pick['tradeoff'] }}.
            @endif
        </p>
    @endif
</div>
