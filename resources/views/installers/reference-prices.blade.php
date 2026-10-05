{{--
    The prices the app quotes with, read by the installer who receives those quotes (ADR-0023).
    Read-only: an administrator keeps them (ADR-0015).

    Params: App\Actions\Installers\DescribeReferencePrices.
--}}
@php
    $money = fn (float $cop): string => '$'.number_format(round($cop, -3), 0, ',', '.');
    // A tariff is hundreds of pesos: rounding it to the nearest thousand says $1.000 for $890.
    $pesos = fn (float $cop): string => '$'.number_format(round($cop), 0, ',', '.');
@endphp

<x-layouts::app :title="__('Precios de referencia')">
    <div class="solar-page solar-prices">
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Recursos</p>
                <h1 class="solar-title mt-0">Precios de referencia</h1>
                <p class="solar-subtitle mt-2 max-w-3xl">
                    Con estos números la app calcula el presupuesto que el cliente ya vio antes de escribirte:
                    el precio por kW instalado de su municipio, por la potencia que pide su consumo. No es una
                    cotización; la tuya la pones tú.
                </p>
            </div>
        </div>

        <dl class="solar-inbox-summary">
            <div>
                <dt>Tarifa de la energía</dt>
                <dd>{{ $pesos($energyRate) }}<span>/kWh en vivienda</span></dd>
            </div>
            <div>
                <dt>Tarifa comercial</dt>
                <dd>{{ $pesos($commercialRate) }}<span>/kWh con contribución</span></dd>
            </div>
        </dl>

        <p class="solar-catalog-note">
            El ahorro de un proyecto se calcula con la tarifa de su tipo de lugar: un negocio paga la
            contribución, una vivienda no. Los precios por kW ya incluyen el transporte hasta el municipio.
        </p>

        <div class="solar-card solar-catalog-table-wrap">
            <table class="solar-catalog-table">
                <thead>
                    <tr>
                        <th scope="col">Municipio</th>
                        @foreach ($locationTypes as $label)
                            <th scope="col" class="is-number">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($municipalities as $municipality)
                        <tr @class(['is-covered' => in_array($municipality['name'], $covered, true)])>
                            <th scope="row">
                                <strong>{{ $municipality['name'] }}</strong>
                                @if (in_array($municipality['name'], $covered, true))
                                    <span class="solar-installer-badge">Lo cubres</span>
                                @endif
                                @if ($municipality['zone'])
                                    <span class="solar-catalog-sub">{{ $municipality['zone'] }}</span>
                                @endif
                            </th>
                            @foreach ($locationTypes as $type => $label)
                                <td class="is-number">
                                    @if (isset($municipality['prices'][$type]))
                                        {{ $money($municipality['prices'][$type]) }}<span class="solar-catalog-sub">por kW</span>
                                    @else
                                        <span class="solar-catalog-sub">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts::app>
