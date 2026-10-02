{{--
    The portfolio as a table, in a modal: the same projects as the cards (this page and search), to
    compare cost, payback and profitability at a glance.
    Params: $solarProjects, $projectSummaries, $portfolioQuery, $search.
--}}
@php
    $money = fn (float $cop): string => '$'.number_format(round($cop, -3), 0, ',', '.');
@endphp

<dialog class="solar-portfolio-table" data-portfolio-table aria-labelledby="portfolio-table-title">
    <div class="solar-portfolio-table__head">
        <div>
            <h2 id="portfolio-table-title">Tus proyectos en una tabla</h2>
            <p>
                @if ($search !== '')
                    Los que coinciden con "{{ $search }}".
                @endif
                @if ($solarProjects->hasPages())
                    Mostrando {{ $solarProjects->count() }} de {{ $solarProjects->total() }} (esta página).
                @endif
                Rentable: se paga en {{ \App\Domain\Solar\Profitability::GOOD_PAYBACK_YEARS }} años o menos; retorno medio, hasta {{ \App\Domain\Solar\Profitability::FAIR_PAYBACK_YEARS }}.
            </p>
        </div>
        <button type="button" class="solar-portfolio-table__close" data-portfolio-table-close aria-label="Cerrar la tabla">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>
    </div>

    @if ($solarProjects->isEmpty())
        <p class="solar-portfolio-table__empty">No hay proyectos para mostrar.</p>
    @else
        <div class="solar-portfolio-table__scroll">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Proyecto</th>
                        <th scope="col">Consumo</th>
                        <th scope="col" class="is-number">Costo</th>
                        <th scope="col">Se paga en</th>
                        <th scope="col">Rentabilidad</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($solarProjects as $solarProject)
                        @php
                            $summary = $projectSummaries[$solarProject->id] ?? null;
                            $profitability = $summary['profitability'] ?? null;
                            $consumption = $solarProject->monthlyConsumption();
                        @endphp
                        <tr>
                            <th scope="row">
                                <a href="{{ route('solar-projects.system', ['solarProject' => $solarProject, ...($portfolioQuery ?? [])]) }}">{{ $solarProject->name }}</a>
                                <span>{{ \App\Domain\Property\PropertyType::label($solarProject->property_type) }} · {{ $solarProject->municipality?->name ?? $solarProject->location_name }}</span>
                            </th>
                            <td>{{ $consumption > 0 ? number_format($consumption, 0, ',', '.').' kWh/mes' : '—' }}</td>
                            <td class="is-number">{{ ($summary['costCop'] ?? null) !== null ? $money($summary['costCop']) : '—' }}</td>
                            <td>{{ ($summary['calculated'] ?? false) ? $summary['paybackText'] : 'Sin calcular' }}</td>
                            <td>
                                @if ($profitability)
                                    <span class="solar-profit-badge solar-profit-badge--{{ $profitability->level }}" title="{{ $profitability->description() }}">{{ $profitability->label() }}</span>
                                @else
                                    <span class="solar-profit-badge">Sin calcular</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</dialog>
