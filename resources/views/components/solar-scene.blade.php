{{--
    The 3D illustration of a building with panels (ADR-0012) outside "Mi sistema": the roof preview of the
    project form (mode "preview") and the landing's example (mode "showcase"). resources/js/solar-scene
    draws the data-*; with WebGL the stage takes the sketch's place from the first paint (scene-loader.js).

    @param string $mode        preview | showcase
    @param string $propertyType house | business | institution
    @param int    $panels       Panels drawn on the roof
    @param float  $roofArea     m² of the roof: it sets the size of the building
    @param float  $panelArea    m² of one panel
    @param string $label        Text for screen readers
    @param string $note         Line under the scene
    @param array  $choices      Showcase only: [type => [label, panels, area]] for the chooser
--}}
@props(['mode', 'propertyType' => 'house', 'panels' => 0, 'roofArea' => 0, 'panelArea' => 2.6, 'label' => 'Ilustración de un techo con paneles solares', 'note' => '', 'choices' => []])

<figure
    class="solar-scene solar-scene--{{ $mode }}"
    data-solar-scene
    data-scene-mode="{{ $mode }}"
    data-property-type="{{ $propertyType }}"
    data-panels-installed="{{ $panels }}"
    data-panels-fit="{{ $panels }}"
    data-panels-missing="0"
    data-roof-area-m2="{{ $roofArea }}"
    data-panel-area-m2="{{ $panelArea }}"
    data-daily-kwh="0"
    aria-label="{{ $label }}"
    {{ $attributes }}
>
    {{-- The sketch of the server: it stays without WebGL, and the scene covers it with WebGL. --}}
    <div class="solar-scene__placeholder" aria-hidden="true">
        <svg viewBox="0 0 200 120" class="solar-scene__house" data-sketch="house">
            <path d="M36 58h128v54H36z" /><path d="M22 62L100 16l78 46" /><path d="M88 112V84h24v28" />
        </svg>
        <svg viewBox="0 0 200 120" class="solar-scene__house" data-sketch="business">
            <path d="M20 50h160v62H20z" /><path d="M14 50l12-24h148l12 24" /><path d="M86 112V82h28v30" />
        </svg>
        <svg viewBox="0 0 200 120" class="solar-scene__house" data-sketch="institution">
            <path d="M24 56h152v56H24z" /><path d="M14 56L100 18l86 38" /><path d="M90 112V84h20v28" />
        </svg>
    </div>

    <div class="solar-scene__stage" data-solar-scene-stage>
        <p class="solar-3d-loading" aria-hidden="true"><span class="solar-sync-spinner"></span>Preparando la ilustración 3D…</p>
        <button type="button" class="solar-scene__replay" data-solar-scene-replay title="Repetir la animación">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v5h5"/></svg>
            <span class="sr-only">Repetir la animación</span>
        </button>
    </div>

    <div class="solar-scene__hud" aria-hidden="true">
        <p class="solar-scene__status" data-solar-scene-status hidden></p>
    </div>

    @if ($choices)
        <div class="solar-scene__choices" role="group" aria-label="Tipo de lugar">
            @foreach ($choices as $type => $choice)
                <button
                    type="button"
                    class="solar-scene__choice"
                    aria-pressed="{{ $type === $propertyType ? 'true' : 'false' }}"
                    data-scene-property="{{ $type }}"
                    data-scene-panels="{{ $choice['panels'] }}"
                    data-scene-area="{{ $choice['area'] }}"
                >{{ $choice['label'] }}</button>
            @endforeach
        </div>
    @endif

    <figcaption>
        <span class="solar-scene__note">{{ $note }}<span class="solar-scene__hint"> · arrastra para girarla</span></span>
    </figcaption>
</figure>
