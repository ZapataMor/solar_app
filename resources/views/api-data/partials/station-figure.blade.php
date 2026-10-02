{{--
    The station that collects the data of the selected tab (ADR-0018). resources/js/station-scene draws
    it in 3D from these data-* and follows the tab through data-station (showApiDataTab in app.js);
    without WebGL the flat sketches stay. CSS shows the sketch and caption of the selected station.
--}}
@php
    $wind = $stations['wind'];
@endphp
<figure
    class="solar-station"
    data-station-scene
    data-station="{{ $activeTab }}"
    data-wind-speed="{{ $wind['speedKmh'] }}"
    data-wind-direction="{{ $wind['directionDegrees'] }}"
    data-latitude="{{ $stations['nasaPoint']['latitude'] }}"
    data-longitude="{{ $stations['nasaPoint']['longitude'] }}"
>
    <div class="solar-station__view">
        <div class="solar-station__sketches" aria-hidden="true">
            <svg viewBox="0 0 200 120" data-station-sketch="ambient">
                <path d="M100 112V46M88 112h24M60 46h80" />
                <path d="M91 46l3-9h12l3 9" />
                <path d="M92 52h16M91 57h18M92 62h16M93 67h14" />
                <path d="M136 46V30M122 30h28" />
                <circle cx="120" cy="30" r="3.5" /><circle cx="152" cy="30" r="3.5" />
                <path d="M64 46V33M52 33h26M78 33l-7-4v8zM52 33l-5-6v12z" />
            </svg>
            <svg viewBox="0 0 200 120" data-station-sketch="weather-station">
                <path d="M36 112h128" />
                <path d="M58 112V80M88 112V80M52 80h42V56H52zM56 62h34M56 68h34M56 74h34M48 56h50l-4-6H52z" />
                <path d="M136 112V22M120 26h32M136 22V10" />
                <circle cx="122" cy="21" r="3.5" /><circle cx="150" cy="21" r="3.5" />
                <path d="M139 46l22-8 4 10-22 8zM127 70h18v18h-18z" />
            </svg>
            <svg viewBox="0 0 200 120" data-station-sketch="nasa">
                <circle cx="78" cy="72" r="40" />
                <path d="M42 58q36 10 72 0M42 86q36-10 72 0M60 35q-14 37 0 74M96 35q14 37 0 74" />
                <circle cx="74" cy="62" r="3" class="is-accent" />
                <path d="M146 24h14v12h-14zM124 26h18v8h-18zM164 26h18v8h-18z" />
                <path d="M151 37L77 61" stroke-dasharray="3 4" />
            </svg>
        </div>
        <p class="solar-3d-loading" aria-hidden="true"><span class="solar-sync-spinner"></span>Preparando la estación en 3D…</p>
        <div class="solar-station__stage" data-station-scene-stage hidden></div>
        <span class="solar-station__hint" aria-hidden="true">Ilustración · arrastra para girarla</span>
    </div>

    <figcaption>
        <div data-station-caption="ambient">
            <strong>Estación Ambient Weather</strong>
            <span>Sensores de viento, lluvia, temperatura, humedad, radiación y UV en un mástil. Sus lecturas llegan por internet cada 5 minutos.</span>
            <span class="solar-station__live">
                <svg viewBox="0 0 20 20" class="solar-station__compass" style="--wind-direction: {{ $wind['directionDegrees'] ?? 0 }}deg" @if ($wind['directionDegrees'] === null) data-no-direction @endif data-station-compass aria-hidden="true">
                    <circle cx="10" cy="10" r="8.5" /><path d="M10 1.5v2.2" /><path d="M10 4.2l2.6 6.8h-5.2z" class="solar-station__needle" />
                </svg>
                <span>Viento de la última lectura: <span data-station-wind-text>{{ $wind['text'] }}</span></span>
            </span>
        </div>
        <div data-station-caption="weather-station">
            <strong>Estación del centro meteorológico</strong>
            <span>Abrigo meteorológico para temperatura y humedad, sensores UVA y UVB, y medidor de CO₂ y partículas (PM2.5 y PM10).</span>
        </div>
        <div data-station-caption="nasa">
            <strong>Satélites de NASA POWER</strong>
            <span>Miden la radiación desde el espacio. NASA POWER la publica por día para el punto marcado en La Guajira, con unos días de retraso.</span>
        </div>
    </figcaption>
</figure>
