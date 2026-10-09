{{--
    Dos cotizaciones enfrentadas (ADR-0029): la forma que toma la pantalla de comparación cuando
    quedan exactamente dos.

    Es el mismo contenido de la tabla de columnas, con la etiqueta en el medio, que es como se lee
    un cara a cara: el valor de cada lado y, debajo, cuánto le saca al otro. La etiqueta central es
    `<th scope="row">`, así que el lector de pantalla sigue anunciando de qué fila es cada valor
    aunque no vaya primera.

    Con tres o más la etiqueta vuelve a la izquierda y las columnas se desplazan: enfrentar solo
    funciona de a dos.

    Props: $groups y $columns (App\Actions\Installers\CompareProjectQuotes), $verdict y
    $recommendation para marcar cuál se recomienda.
--}}
@props(['groups', 'columns', 'verdict', 'recommendation' => null])

@php
    $money = fn (float $cop): string => '$'.number_format(round($cop, -3), 0, ',', '.');
    $kw = fn (float $value): string => number_format($value, 2, ',', '.');
    $date = fn ($value): string => \Illuminate\Support\Carbon::parse($value)->locale('es')->isoFormat('D [de] MMMM');
@endphp

<div class="solar-face-off">
    <table class="solar-face-off__table">
        <caption class="sr-only">
            {{ $columns[0]['installerName'] }} frente a {{ $columns[1]['installerName'] }}, campo por campo.
        </caption>
        <thead>
            <tr>
                @foreach ($columns as $position => $column)
                    @if ($position === 1)
                        <td class="solar-face-off__vs"><span>vs</span></td>
                    @endif
                    <th scope="col" @class([
                        'solar-face-off__side',
                        'is-expired' => $column['expired'],
                        'is-recommended' => $recommendation !== null && $recommendation['index'] === $position,
                    ])>
                        @if ($recommendation !== null && $recommendation['index'] === $position)
                            <span class="solar-compare__recommended">Recomendada</span>
                        @endif
                        <span class="solar-face-off__name">{{ $column['installerName'] }}</span>
                        <span class="solar-face-off__price">{{ $money($column['amountCop']) }}</span>
                        <span class="solar-face-off__meta">
                            @if ($column['yearsExperience']) {{ $column['yearsExperience'] }} años · @endif
                            {{ $column['statusLabel'] }}
                            @if ($column['vatIncluded'] === false) · IVA aparte @endif
                        </span>
                        @if ($column['expired'])
                            <span class="solar-compare__expired">Precio vencido el {{ $date($column['validUntil']) }}</span>
                        @endif
                        <span class="solar-face-off__score">
                            @if ($verdict[$position]['expired'])
                                No compite: el precio venció
                            @else
                                Gana <strong>{{ $verdict[$position]['rowsWon'] }}</strong>
                                de {{ $verdict[$position]['rowsCompared'] }} filas
                            @endif
                        </span>
                        <span class="solar-face-off__score">
                            Declara <strong>{{ $verdict[$position]['declared'] }}</strong>
                            de {{ $verdict[$position]['declarable'] }} datos
                        </span>
                    </th>
                @endforeach
            </tr>
        </thead>

        @foreach ($groups as $group)
            <tbody>
                <tr class="solar-face-off__group">
                    <th scope="rowgroup" colspan="3">
                        {{ $group['title'] }}
                        @if ($group['note'])
                            <small>{{ $group['note'] }}</small>
                        @endif
                    </th>
                </tr>
                @foreach ($group['rows'] as $row)
                    <tr data-face-off-row="{{ $row['key'] }}">
                        @foreach ($row['cells'] as $position => $cell)
                            @if ($position === 1)
                                <th scope="row" class="solar-face-off__label">
                                    {{ $row['label'] }}
                                    @if ($row['hint'])
                                        <small>{{ $row['hint'] }}</small>
                                    @endif
                                </th>
                            @endif
                            <td @class([
                                'solar-face-off__cell',
                                'is-best' => $cell['best'],
                                'is-missing' => $cell['missing'],
                                'is-expired' => $columns[$position]['expired'],
                            ])>
                                <x-installers.compare-cell :cell="$cell" :format="$row['format']" />
                                <x-installers.compare-delta :advantage="$cell['advantage']" :format="$row['format']" />
                                @if ($cell['best'])
                                    {{-- Con forma y palabra, no solo con el fondo verde: el color no
                                         es la única marca, y el lector de pantalla lo anuncia. --}}
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

        <tfoot>
            <tr>
                @foreach ($columns as $position => $column)
                    @if ($position === 1)
                        <th scope="row" class="solar-face-off__label">Hablar y leerla completa</th>
                    @endif
                    <td class="solar-face-off__cell">
                        <div class="solar-face-off__actions">
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
