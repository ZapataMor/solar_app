{{--
    "Ver en kWh | Pesos" for a [data-unit-root] container (consumption diary, alternative panel).
    Figures are drawn in both units (.solar-unit--kwh / .solar-unit--money) and the root shows one.
    The choice is shared and remembered in the browser; app.js wires the buttons.
    Params: $rate (COP per kWh; without it, only kWh), $label (optional).
--}}
@if ($rate > 0)
    {{-- Before the content is drawn: the unit this viewer chose last time (no kWh → pesos jump). --}}
    <script>
        try {
            if (localStorage.getItem('natalia:unit') === 'money') {
                document.querySelectorAll('[data-unit-root]').forEach((root) => { root.dataset.unit = 'money'; });
            }
        } catch (_error) {
            // Storage unavailable: kWh.
        }
    </script>

    <div class="solar-unit-toolbar">
        <span class="solar-unit-toolbar__label" id="unit-switch-label">{{ $label ?? 'Ver consumo en' }}</span>
        <div class="solar-unit-switch" role="group" aria-labelledby="unit-switch-label">
            <button type="button" data-unit-choice="kwh" aria-pressed="true">kWh</button>
            <button type="button" data-unit-choice="money" aria-pressed="false">Pesos</button>
        </div>
        <span class="solar-unit-toolbar__note solar-unit solar-unit--money">
            Con tu tarifa de ${{ number_format($rate, 0, ',', '.') }} por kWh; es lo que esa energía cuesta hoy en el recibo.
        </span>
    </div>
@endif
