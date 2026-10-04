{{--
    Development only (gate `design-3d`): the place where the 3D models will be designed.
    It has no models yet; the existing ones (solar-scene, appliance-scene, station-scene) are not here.
--}}
<x-layouts::app :title="__('Diseñador 3D')">
    <div class="solar-page">
        <div class="solar-page-header">
            <div>
                <p class="solar-kicker">Desarrollo</p>
                <h1 class="solar-title mt-0">Diseñador 3D</h1>
                <p class="solar-subtitle mt-2 max-w-3xl">
                    Aquí se diseñarán los modelos 3D de la app. Solo se ve en modo desarrollo.
                </p>
            </div>
        </div>

        <section class="solar-card">
            <div class="grid min-h-80 place-items-center rounded-lg border border-dashed border-[color:var(--solar-border)] text-center text-[color:var(--solar-text-muted)]">
                <p>Aún no hay modelos. El lienzo aparecerá aquí.</p>
            </div>
        </section>
    </div>
</x-layouts::app>
