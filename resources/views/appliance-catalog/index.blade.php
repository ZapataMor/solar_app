{{--
    Administration: the appliance catalog as a reference of consumption (ADR-0017).
    Params: $appliances (DescribeApplianceCatalog), $sort, $segment.
--}}
@php
    use App\Actions\Catalog\DescribeApplianceCatalog;

    $number = fn (float $value, int $decimals = 0): string => number_format($value, $decimals, ',', '.');
    // "2,4" kWh, "192" kWh: one decimal only for small values.
    $kwh = fn (float $value): string => $number($value, $value < 10 ? 1 : 0);
    $money = fn (float $cop): string => '$'.number_format(round($cop, -2), 0, ',', '.');
@endphp

<x-layouts::app :title="__('Catálogo de equipos')">
    @include('solar-projects.partials.appliance-icons')

    <div class="solar-page solar-catalog">
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Administración</p>
                <h1 class="solar-title mt-0">Catálogo de equipos</h1>
                <p class="solar-subtitle mt-2 max-w-3xl">
                    Cuánto consume cada equipo del diario de consumo. Los del sistema vienen con la app; los que
                    agregues aparecen en el diario de todos los proyectos.
                </p>
            </div>
            <a href="{{ route('appliance-catalog.create') }}" class="solar-button" wire:navigate>Agregar equipo</a>
        </div>

        <form method="GET" action="{{ route('appliance-catalog.index') }}" class="solar-catalog-controls" data-autosubmit>
            <label class="solar-catalog-controls__sort">
                <span>Ordenar por</span>
                <select name="orden" class="solar-input">
                    @foreach (DescribeApplianceCatalog::SORTS as $value => $label)
                        <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="solar-catalog-controls__segments" role="group" aria-label="Para qué lugar">
                @foreach (DescribeApplianceCatalog::SEGMENTS as $value => $label)
                    <label @class(['solar-catalog-chip', 'is-active' => $segment === $value])>
                        <input type="radio" name="para" value="{{ $value }}" @checked($segment === $value) class="sr-only">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            <noscript><button type="submit" class="solar-button-ghost">Aplicar</button></noscript>
        </form>

        <p class="solar-catalog-note">
            <strong>Consumo típico:</strong> una unidad de la opción que el diario propone, con sus horas de uso
            habituales, a la tarifa de referencia. La potencia muestra el rango de las opciones y su promedio:
            entre opciones de un mismo equipo la diferencia puede ser grande.
        </p>

        <div class="solar-card solar-catalog-table-wrap">
            <table class="solar-catalog-table">
                <thead>
                    <tr>
                        <th scope="col">Equipo</th>
                        <th scope="col">Para</th>
                        <th scope="col">Uso habitual</th>
                        <th scope="col" class="is-number">Potencia</th>
                        <th scope="col" class="is-number">Consumo típico</th>
                        <th scope="col" class="is-number">Proyectos</th>
                        <th scope="col"><span class="sr-only">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($appliances as $appliance)
                        <tr @class(['is-hidden' => ! $appliance['active']]) data-test="catalog-row-{{ $appliance['key'] }}">
                            <th scope="row">
                                <div class="solar-catalog-name">
                                    <svg viewBox="0 0 24 24" class="solar-catalog-name__icon" aria-hidden="true"><use href="#appliance-{{ $appliance['icon'] }}"></use></svg>
                                    <div>
                                        <strong>{{ $appliance['label'] }}</strong>
                                        @unless ($appliance['builtIn'])
                                            <span class="solar-catalog-badge">Agregado</span>
                                        @endunless
                                        @unless ($appliance['active'])
                                            <span class="solar-catalog-badge is-muted">Oculto</span>
                                        @endunless
                                        @if (count($appliance['variants']) > 1)
                                            <details class="solar-catalog-variants">
                                                <summary>{{ count($appliance['variants']) }} opciones</summary>
                                                <ul>
                                                    @foreach ($appliance['variants'] as $variant)
                                                        <li @class(['is-default' => $variant['isDefault']])>
                                                            <span>{{ $variant['label'] }}@if ($variant['isDefault']) <em>(típica)</em>@endif</span>
                                                            <span>{{ $number($variant['watts']) }} W · {{ $kwh($variant['monthlyKwh']) }} kWh/mes</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </details>
                                        @endif
                                    </div>
                                </div>
                            </th>
                            <td>{{ $appliance['segments'] }}</td>
                            <td>{{ $appliance['usage'] }}</td>
                            <td class="is-number">
                                @if ($appliance['minWatts'] === $appliance['maxWatts'])
                                    {{ $number($appliance['maxWatts']) }} W
                                @else
                                    {{ $number($appliance['minWatts']) }}–{{ $number($appliance['maxWatts']) }} W
                                    <span class="solar-catalog-sub">promedio {{ $number($appliance['averageWatts']) }} W</span>
                                @endif
                            </td>
                            <td class="is-number">
                                <strong>{{ $kwh($appliance['typicalKwh']) }} kWh/mes</strong>
                                <span class="solar-catalog-sub">≈ {{ $money($appliance['typicalCop']) }} al mes</span>
                            </td>
                            <td class="is-number">{{ $appliance['projects'] }}</td>
                            <td>
                                @if ($appliance['id'])
                                    <a href="{{ route('appliance-catalog.edit', $appliance['id']) }}" class="solar-catalog-edit" wire:navigate>Editar<span class="sr-only"> {{ $appliance['label'] }}</span></a>
                                @else
                                    <span class="solar-catalog-sub" title="Viene con la app">Del sistema</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="solar-catalog-empty">No hay equipos para ese lugar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts::app>
