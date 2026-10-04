import { describe, formatClock, formatPower, phaseOf } from './simulation.js';

/**
 * Binds the controls under the picture to the simulation, and shows what it reads: the clock, the
 * sentence that says what is going on and the four numbers of the energy. The simulation is the one that
 * knows; this only listens to the buttons and writes the words (all in the figure's data-system-* hooks).
 *
 * @param {HTMLElement} figure The [data-system-scene] figure.
 * @param {ReturnType<import('./simulation.js').createSimulation>} simulation
 * @param {{reduceMotion: boolean, onChange: () => void}} options `onChange` is called after any action of the user.
 * @return {{sync: (readout: object, dt?: number, force?: boolean) => void, dispose: () => void}}
 */
export const bindControls = (figure, simulation, { reduceMotion, onChange }) => {
    const all = (selector) => [...figure.querySelectorAll(selector)];
    const one = (selector) => figure.querySelector(selector);

    const slider = one('[data-system-time]');
    const battery = one('[data-system-battery]');
    const batteryValue = one('[data-system-battery-value]');
    const play = one('[data-system-play]');
    const playLabel = one('[data-system-play-label]');
    const status = one('[data-system-status]');
    const presets = all('[data-system-preset]');
    const toggles = all('[data-system-toggle]');
    let activePreset = null;
    let accumulated = 1;
    let lastStatus = '';

    const write = (elements, value) => {
        elements.forEach((element) => {
            if (element.textContent !== value) {
                element.textContent = value;
            }
        });
    };

    const listeners = [];
    const listen = (element, type, handler) => {
        element.addEventListener(type, handler);
        listeners.push(() => element.removeEventListener(type, handler));
    };

    // Moving the clock by hand stops the day: the user wants to look at that moment.
    if (slider) {
        listen(slider, 'input', () => {
            simulation.setPlaying(false);
            simulation.setHour(Number(slider.value));
            activePreset = null;
            onChange();
        });
    }
    if (battery) {
        listen(battery, 'input', () => {
            simulation.setBattery(Number(battery.value) / 100);
            activePreset = null;
            onChange();
        });
    }
    if (play) {
        if (reduceMotion) {
            play.disabled = true;
            play.title = 'Tu sistema pide menos movimiento: usa la hora para recorrer el día.';
        }
        listen(play, 'click', () => {
            simulation.setPlaying(!simulation.state.playing);
            onChange();
        });
    }
    presets.forEach((button) => listen(button, 'click', () => {
        simulation.applyPreset(button.dataset.systemPreset);
        activePreset = button.dataset.systemPreset;
        onChange();
    }));
    toggles.forEach((input) => listen(input, 'change', () => {
        const key = input.dataset.systemToggle;
        if (key === 'clouds') {
            simulation.setCloud(input.checked);
        } else if (key === 'grid-down') {
            simulation.setGrid(!input.checked);
        } else {
            simulation.setDevice(key, input.checked);
        }
        activePreset = null;
        onChange();
    }));

    const stat = (name, value, note, state = '') => {
        write(all(`[data-system-stat-value="${name}"]`), value);
        write(all(`[data-system-stat-note="${name}"]`), note);
        all(`[data-system-stat="${name}"]`).forEach((tile) => {
            tile.dataset.state = state;
        });
    };

    const sync = (r, dt = 1, force = false) => {
        accumulated += dt;
        if (!force && accumulated < 0.12) {
            return;
        }
        accumulated = 0;

        write(all('[data-system-clock]'), formatClock(r.hour));
        write(all('[data-system-phase]'), phaseOf(r.hour));
        figure.classList.toggle('is-night', r.sun.y < 0);
        figure.dataset.mode = r.mode;

        // The sliders follow the simulation, except the one the user is holding.
        if (slider && (r.playing || document.activeElement !== slider)) {
            slider.value = r.hour.toFixed(2);
        }
        if (battery && document.activeElement !== battery) {
            battery.value = Math.round(r.soc * 100);
        }
        write(batteryValue ? [batteryValue] : [], `${Math.round(r.soc * 100)} %`);

        if (play) {
            play.setAttribute('aria-pressed', String(r.playing));
            write(playLabel ? [playLabel] : [], r.playing ? 'Pausar el día' : 'Ver un día completo');
        }
        presets.forEach((button) => button.setAttribute('aria-pressed', String(button.dataset.systemPreset === activePreset)));
        toggles.forEach((input) => {
            const key = input.dataset.systemToggle;
            const checked = key === 'clouds' ? simulation.state.cloud > 0.5 : key === 'grid-down' ? !r.gridUp : r.on[key];
            if (input.checked !== checked) {
                input.checked = checked;
            }
        });

        const sentence = describe(r);
        if (status && sentence !== lastStatus) {
            lastStatus = sentence;
            status.textContent = sentence;
        }

        // Short notes: they live in a small strip over the picture.
        stat('pv', formatPower(r.pvW), r.pvW > 5 ? (r.cloud > 0.5 ? 'con nubes' : 'produciendo') : (r.daylight < 0.12 ? 'de noche' : 'sin sol'), r.pvW > 5 ? 'on' : 'off');
        stat('load', formatPower(r.servedW), r.blackout ? 'sin luz' : 'consumo', r.blackout ? 'alert' : 'on');
        const bank = {
            charging: [`carga ${formatPower(r.chargeW)}`, 'on'],
            discharging: [`entrega ${formatPower(r.dischargeW)}`, 'use'],
            full: ['llenas', 'on'],
            reserve: ['en reserva', 'alert'],
            idle: ['en reposo', 'off'],
        }[r.bank];
        stat('battery', `${Math.round(r.soc * 100)} %`, bank[0], bank[1]);
        if (!r.gridUp) {
            stat('grid', 'Caída', 'apagón', 'alert');
        } else if (r.gridW > 5) {
            stat('grid', formatPower(r.gridW), 'hacia la red', 'export');
        } else if (r.gridW < -5) {
            stat('grid', formatPower(r.gridW), 'desde la red', 'import');
        } else {
            stat('grid', '0 W', 'sin intercambio', 'off');
        }
    };

    return {
        sync,
        dispose: () => listeners.forEach((remove) => remove()),
    };
};
