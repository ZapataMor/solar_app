{{-- Are the sources being brought up to date? One card per source and the scheduler's heartbeat (ADR-0016). --}}
@php
    $scheduler = $syncHealth['scheduler'];
    $sources = $syncHealth['sources'];
    $problemSources = collect($sources)->filter(fn (array $source) => $source['problem']);
@endphp

<section class="solar-sync-health" aria-labelledby="sync-health-title" data-sync-health>
    <header class="solar-sync-health__header">
        <h2 id="sync-health-title" class="solar-sync-health__title">Salud de la sincronización</h2>
        <p class="solar-sync-health__scheduler" data-status="{{ $scheduler['status'] }}">
            <span class="solar-sync-dot" aria-hidden="true"></span>
            <span><strong>{{ $scheduler['text'] }}.</strong> {{ $scheduler['detail'] }}</span>
        </p>
    </header>

    @if ($syncHealth['problems'] > 0)
        <div class="solar-alert solar-alert-danger" role="alert" data-sync-alert>
            @if ($scheduler['problem'])
                <p>{{ $scheduler['text'] }}: {{ $scheduler['detail'] }}</p>
            @endif
            @foreach ($problemSources as $source)
                <p>{{ $source['alert'] }}</p>
            @endforeach
        </div>
    @endif

    <div class="solar-sync-health__cards">
        @foreach ($sources as $key => $source)
            <article class="solar-sync-card" data-sync-source="{{ $key }}" data-status="{{ $source['status'] }}">
                <h3 class="solar-sync-card__name">
                    <span class="solar-sync-dot" aria-hidden="true"></span>
                    {{ $source['label'] }}
                </h3>
                <p class="solar-sync-card__status">{{ $source['text'] }}</p>
                <dl class="solar-sync-card__facts">
                    <div>
                        <dt>Último dato</dt>
                        <dd>{{ $source['lastData'] }}</dd>
                    </div>
                    <div>
                        <dt>Última ejecución</dt>
                        <dd>{{ $source['lastRun'] }}</dd>
                    </div>
                </dl>
                @if ($source['error'])
                    <p class="solar-sync-card__error">{{ $source['error'] }}</p>
                @endif
            </article>
        @endforeach
    </div>
</section>
