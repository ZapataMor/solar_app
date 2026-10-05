{{--
    The installer's inbox (ADR-0023): the list of quote requests their company received. Only what
    says which one to open; the request itself lives in installers/quote-request.blade.php.

    Params: $installer, $requests, $open, $won, $contractsCop (App\Actions\Installers\DescribeInstallerInbox).
--}}
@php
    use App\Domain\Property\PropertyType;

    $money = fn (float $cop): string => '$'.number_format(round($cop, -3), 0, ',', '.');
    $kwh = fn (float $value): string => number_format($value, $value < 10 ? 1 : 0, ',', '.');
    $date = fn ($value): string => \Illuminate\Support\Carbon::parse($value)->locale('es')->isoFormat('D [de] MMMM');
@endphp

<x-layouts::app :title="__('Solicitudes')">
    <div class="solar-page solar-inbox">
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">{{ $installer->name }}</p>
                <h1 class="solar-title mt-0">Solicitudes de cotización</h1>
                <p class="solar-subtitle mt-2 max-w-3xl">
                    Clientes que te pidieron cotización desde el directorio. Cada uno llega con su consumo, sus
                    equipos y su techo ya registrados.
                </p>
            </div>
        </div>

        @if ($requests)
            <dl class="solar-inbox-summary">
                <div>
                    <dt>Sin responder</dt>
                    <dd>{{ $open }}</dd>
                </div>
                <div>
                    <dt>Negocios cerrados</dt>
                    <dd>{{ $won }}</dd>
                </div>
                <div>
                    <dt>Valor de los contratos</dt>
                    <dd>{{ $contractsCop > 0 ? $money($contractsCop) : '—' }}</dd>
                </div>
            </dl>
        @endif

        <div class="solar-inbox-list">
            @forelse ($requests as $request)
                <a
                    href="{{ route('installer-inbox.show', $request['id']) }}"
                    @class(['solar-card', 'solar-inbox-row', 'is-open' => $request['open']])
                    data-test="quote-request-{{ $request['id'] }}"
                    wire:navigate
                >
                    <span class="solar-inbox-row__main">
                        <span class="solar-inbox-card__status" data-status="{{ $request['status'] }}">{{ $request['statusLabel'] }}</span>
                        <strong>{{ $request['projectName'] }}</strong>
                        <span class="solar-inbox-row__meta">
                            {{ $request['clientName'] ?? 'Sin nombre' }} · {{ PropertyType::label($request['propertyType']) }}
                            @if ($request['municipality']) · {{ $request['municipality'] }} @endif
                        </span>
                    </span>

                    <span class="solar-inbox-row__figures">
                        <span>
                            <small>Consumo</small>
                            {{ $kwh($request['monthlyKwh']) }} kWh/mes
                        </span>
                        <span>
                            <small>Pedida</small>
                            {{ $date($request['requestedAt']) }}
                        </span>
                        @if ($request['contractValueCop'])
                            <span class="is-won">
                                <small>Cerrada en</small>
                                {{ $money($request['contractValueCop']) }}
                            </span>
                        @endif
                    </span>

                    <svg viewBox="0 0 24 24" class="solar-inbox-row__chevron" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>
                </a>
            @empty
                <section class="solar-card solar-inbox-empty">
                    <h2 class="text-lg font-semibold text-[color:var(--solar-text)]">Todavía no te han pedido cotización</h2>
                    <p class="solar-subtitle mt-2">
                        Apareces en el directorio para los clientes con un proyecto en los municipios que cubres.
                        Cuando alguno te escriba, su solicitud llega aquí con toda su estimación.
                    </p>
                </section>
            @endforelse
        </div>
    </div>
</x-layouts::app>
