<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="{{ asset('images/logo.png') }}" type="image/png">
<link rel="apple-touch-icon" href="{{ asset('images/logo.png') }}">

@fonts

{{--
    3D scenes (ADR-0012, ADR-0018): with WebGL the figures wait for their scene with a loader instead of
    flashing the flat sketch (resources/js/scene-loader.js). Set before the first paint, and again on each
    wire:navigate, whose swap copies the <html> attributes of the new page. If app.js never runs, the
    figures of this page show their sketch after a while.
--}}
<script>
    if ('WebGLRenderingContext' in window) {
        const can3d = () => document.documentElement.classList.add('solar-can-3d');
        can3d();
        document.addEventListener('livewire:navigating', (event) => event.detail?.onSwap?.(can3d));
        document.addEventListener('DOMContentLoaded', () => {
            const figures = document.querySelectorAll('[data-solar-scene], [data-station-scene]');
            setTimeout(() => figures.forEach((figure) => figure.classList.contains('is-3d') || figure.classList.add('is-flat')), 12000);
        });
    }
</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
{{-- Per page: e.g. the chunk of its 3D scene, so it downloads with the page instead of after app.js. --}}
@stack('head')
