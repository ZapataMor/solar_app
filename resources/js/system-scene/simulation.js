/**
 * The numbers behind the system animation (ADR-0021): where the sun is, how much the panels make and where
 * the energy goes. Plain functions: no DOM and no Three.js. It shows how a hybrid system with batteries
 * behaves through a day; the figures are round on purpose and are not a design.
 */

export const DEG = Math.PI / 180;

export const clamp = (value, min = 0, max = 1) => Math.min(max, Math.max(min, value));
export const lerp = (from, to, amount) => from + (to - from) * amount;
export const smoothstep = (edge0, edge1, x) => {
    const t = clamp((x - edge0) / (edge1 - edge0));

    return t * t * (3 - 2 * t);
};

/** Tilt of the panels on their rack: they face south, as they should at the latitude of La Guajira. */
export const PANEL_TILT = 20 * DEG;

/** The system: 8 panels of 400 W, a 48 V × 40 Ah bank of batteries and a 3.5 kW inverter. */
export const PV_PEAK_W = 3200;
const PV_DERATE = 0.88;
export const BANK_WH = 1920;
/** The batteries are not used below this charge, and are used again once they climb back past RESTART. */
export const RESERVE = 0.1;
const RESTART = 0.15;
const MAX_CHARGE_W = 1500;
const MAX_DISCHARGE_W = 2500;
const INVERTER_EFFICIENCY = 0.96;

/** A whole day in a minute. */
export const HOURS_PER_SECOND = 0.4;
export const DEVICE_WATTS = { lamp: 12, tv: 100, fridge: 140 };
/** The fridge's motor runs 9 s of every 22 s (real time), as a compressor does. */
const FRIDGE_CYCLE_S = 22;
const FRIDGE_ON_S = 9;

const SUNRISE = 6;
const SUNSET = 18;
const PANEL_NORMAL = { x: 0, y: Math.cos(PANEL_TILT), z: Math.sin(PANEL_TILT) };

/** Quick scenes: each one sets the moment and the conditions, and stops the clock to look at it. */
export const PRESETS = {
    noon: { hour: 12, soc: 0.8, cloud: 0, gridUp: true },
    sunset: { hour: 17.75, soc: 0.9, cloud: 0, gridUp: true },
    night: { hour: 21, soc: 0.65, cloud: 0, gridUp: true },
    empty: { hour: 3.5, soc: RESERVE, cloud: 0, gridUp: true },
    blackout: { hour: 3.5, soc: RESERVE, cloud: 0, gridUp: false },
};

/**
 * Unit vector toward the sun: x to the east (the right of the picture), y up, z to the south (toward
 * the viewer). It rises in the east at 6:00, passes a little south of the zenith and sets in the west.
 */
export const sunAt = (hour) => {
    const angle = (Math.PI * (hour - SUNRISE)) / (SUNSET - SUNRISE);
    const x = Math.cos(angle);
    const y = Math.sin(angle);
    const z = 0.1 + 0.28 * y;
    const length = Math.hypot(x, y, z);

    return { x: x / length, y: y / length, z: z / length };
};

/** What the panels could make with the sun where it is: 0 at night, most at midday, less under clouds. */
export const panelOutput = (sun, cloud) => {
    const incidence = Math.max(0, sun.x * PANEL_NORMAL.x + sun.y * PANEL_NORMAL.y + sun.z * PANEL_NORMAL.z);

    return PV_PEAK_W * PV_DERATE * incidence * smoothstep(0, 0.14, sun.y) * (1 - 0.78 * cloud);
};

/**
 * Where the energy goes, from what the panels can make and what the house asks for. The panels serve the
 * house first, then charge the batteries, then send the rest to the grid. What the panels cannot give, the
 * batteries do, down to their reserve; and what they cannot give, the grid. Without grid, the house is left
 * in the dark. `hours` is how long the step lasts, so a battery lands softly on empty or full.
 */
export const dispatch = ({ pvW, loads, soc, locked, gridUp, hours = 0 }) => {
    const demandW = loads.lamp + loads.tv + loads.fridge;
    const dcNeeded = demandW / INVERTER_EFFICIENCY;

    let batteryMax = !locked && soc > RESERVE ? MAX_DISCHARGE_W : 0;
    if (batteryMax > 0 && hours > 0) {
        batteryMax = Math.min(batteryMax, ((soc - RESERVE) * BANK_WH) / hours);
    }

    let pvToInverter = Math.min(pvW, dcNeeded);
    let dischargeW = Math.min(dcNeeded - pvToInverter, batteryMax);
    const shortDc = dcNeeded - pvToInverter - dischargeW;
    let gridImportW = 0;
    let blackout = false;

    if (shortDc > 0.5) {
        if (gridUp) {
            gridImportW = shortDc * INVERTER_EFFICIENCY;
        } else {
            blackout = true;
            pvToInverter = 0;
            dischargeW = 0;
        }
    }

    const pvLeft = pvW - pvToInverter;
    let chargeMax = soc < 1 ? MAX_CHARGE_W : 0;
    if (chargeMax > 0 && hours > 0) {
        chargeMax = Math.min(chargeMax, ((1 - soc) * BANK_WH) / hours);
    }
    const chargeW = Math.min(pvLeft, chargeMax);
    const toGridDc = gridUp ? pvLeft - chargeW : 0;
    const curtailedW = pvLeft - chargeW - toGridDc;
    const exportW = toGridDc * INVERTER_EFFICIENCY;
    const inverterDcW = pvToInverter + dischargeW + toGridDc;

    return {
        demandW,
        servedW: blackout ? 0 : demandW,
        pvW: pvToInverter + chargeW + toGridDc,
        pvToInverter,
        chargeW,
        dischargeW,
        batteryW: chargeW - dischargeW,
        inverterDcW,
        inverterAcW: inverterDcW * INVERTER_EFFICIENCY,
        gridImportW,
        exportW,
        gridW: exportW - gridImportW,
        curtailedW,
        blackout,
    };
};

/** What is going on, in one word: it picks the sentence that explains it. */
const modeOf = (flows) => {
    if (flows.blackout) {
        return 'blackout';
    }
    if (flows.gridImportW > 1) {
        return flows.pvToInverter + flows.dischargeW < 1 ? 'grid' : 'grid-assist';
    }
    if (flows.dischargeW > 1) {
        return flows.pvToInverter < 20 ? 'battery' : 'battery-assist';
    }
    if (flows.exportW > 1) {
        return 'exporting';
    }
    if (flows.curtailedW > 1) {
        return 'curtailed';
    }
    if (flows.chargeW > 1) {
        return 'charging';
    }

    return flows.pvToInverter > 1 ? 'direct' : 'idle';
};

export const createSimulation = () => {
    const state = {
        hour: 10.5,
        soc: 0.72,
        locked: false,
        cloud: 0,
        cloudLevel: 0,
        gridUp: true,
        devices: { lamp: true, tv: true, fridge: true },
        playing: false,
        importKwh: 3.2,
        exportKwh: 12.4,
    };

    const step = (dt, elapsed) => {
        const hours = state.playing ? dt * HOURS_PER_SECOND : 0;
        state.hour = (state.hour + hours) % 24;
        // The clouds come and go slowly (at once on a redraw).
        state.cloudLevel += (state.cloud - state.cloudLevel) * (dt > 0 ? 1 - Math.exp(-dt * 1.6) : 1);

        const sun = sunAt(state.hour);
        const fridgeRunning = state.devices.fridge && elapsed % FRIDGE_CYCLE_S < FRIDGE_ON_S;
        const loads = {
            lamp: state.devices.lamp ? DEVICE_WATTS.lamp : 0,
            tv: state.devices.tv ? DEVICE_WATTS.tv : 0,
            fridge: fridgeRunning ? DEVICE_WATTS.fridge : 0,
        };
        const flows = dispatch({
            pvW: panelOutput(sun, state.cloudLevel),
            loads,
            soc: state.soc,
            locked: state.locked,
            gridUp: state.gridUp,
            hours,
        });

        if (hours > 0) {
            state.soc = clamp(state.soc + (flows.batteryW * hours) / BANK_WH, RESERVE, 1);
            if (state.soc <= RESERVE + 1e-4) {
                state.locked = true;
            } else if (state.soc >= RESTART) {
                state.locked = false;
            }
            state.importKwh += (flows.gridImportW * hours) / 1000;
            state.exportKwh += (flows.exportW * hours) / 1000;
        }

        const powered = {
            lamp: state.devices.lamp && !flows.blackout,
            tv: state.devices.tv && !flows.blackout,
            fridge: fridgeRunning && !flows.blackout,
        };
        const pvVolts = flows.pvW > 5 ? 108 + 14 * Math.sqrt(flows.pvW / PV_PEAK_W) : 0;
        const bank = flows.chargeW > 1 ? 'charging'
            : flows.dischargeW > 1 ? 'discharging'
                : state.soc >= 0.995 ? 'full'
                    : state.locked ? 'reserve' : 'idle';

        return {
            ...flows,
            time: elapsed,
            hour: state.hour,
            sun,
            daylight: smoothstep(-0.03, 0.2, sun.y),
            /** How strong the shafts of sunlight are: none at night, softer under clouds. */
            beam: smoothstep(0, 0.16, sun.y) * (1 - 0.8 * state.cloudLevel),
            cloud: state.cloudLevel,
            playing: state.playing,
            gridUp: state.gridUp,
            on: { ...state.devices },
            powered,
            fridgeRunning,
            loads,
            soc: state.soc,
            locked: state.locked,
            bank,
            busVolts: 46 + 7 * state.soc + (flows.chargeW > 1 ? 1.4 : 0) - (flows.dischargeW > 1 ? 0.6 : 0),
            pvVolts,
            pvAmps: pvVolts > 0 ? flows.pvW / pvVolts : 0,
            inverterState: flows.inverterAcW > 1 ? 'on' : flows.blackout ? 'off' : 'standby',
            importKwh: state.importKwh,
            exportKwh: state.exportKwh,
            mode: modeOf(flows),
        };
    };

    return {
        state,
        step,
        setHour: (hour) => { state.hour = clamp(hour, 0, 24) % 24; },
        setPlaying: (playing) => { state.playing = playing; },
        setCloud: (cloudy) => { state.cloud = cloudy ? 1 : 0; },
        setGrid: (up) => { state.gridUp = up; },
        setDevice: (key, on) => { state.devices[key] = on; },
        setBattery: (soc) => {
            state.soc = clamp(soc, RESERVE, 1);
            if (state.soc <= RESERVE + 1e-4) {
                state.locked = true;
            } else if (state.soc >= RESTART) {
                state.locked = false;
            }
        },
        applyPreset: (name) => {
            const preset = PRESETS[name];
            if (!preset) {
                return;
            }
            state.hour = preset.hour;
            state.cloud = preset.cloud;
            state.cloudLevel = preset.cloud;
            state.gridUp = preset.gridUp;
            state.devices = { lamp: true, tv: true, fridge: true };
            state.playing = false;
            state.soc = preset.soc;
            state.locked = preset.soc <= RESERVE + 1e-4;
        },
    };
};

/* ---- Words ---- */

export const formatPower = (watts) => {
    const value = Math.abs(watts);

    return value >= 1000 ? `${(value / 1000).toLocaleString('es-CO', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} kW` : `${Math.round(value)} W`;
};

export const formatClock = (hour) => {
    const minutes = Math.round(hour * 60) % 1440;
    const h24 = Math.floor(minutes / 60);
    const h12 = h24 % 12 === 0 ? 12 : h24 % 12;

    return `${h12}:${String(minutes % 60).padStart(2, '0')} ${h24 < 12 ? 'a. m.' : 'p. m.'}`;
};

export const phaseOf = (hour) => {
    if (hour >= 5 && hour < 7) {
        return 'Amanecer';
    }
    if (hour >= 7 && hour < 11.5) {
        return 'Mañana';
    }
    if (hour >= 11.5 && hour < 13.5) {
        return 'Mediodía';
    }
    if (hour >= 13.5 && hour < 17) {
        return 'Tarde';
    }
    if (hour >= 17 && hour < 19) {
        return 'Atardecer';
    }

    return 'Noche';
};

/** The sentence that says what the system is doing now, in a client's words. */
export const describe = (r) => {
    const dark = r.daylight < 0.12;
    const noSun = dark ? 'Es de noche' : 'Casi no llega sol';
    const sentences = {
        blackout: `${noSun}, las baterías están en su reserva mínima y la red está caída: la casa se queda sin electricidad.`,
        grid: `${noSun} y las baterías están en su reserva mínima: la casa toma la electricidad de la red.`,
        'grid-assist': 'Los paneles no alcanzan y las baterías están en su reserva mínima: la red completa lo que falta.',
        battery: dark
            ? 'Es de noche: los paneles no producen y la casa funciona con la energía guardada en las baterías.'
            : 'Casi no llega sol: la casa funciona con la energía guardada en las baterías.',
        'battery-assist': 'Hay poco sol: los paneles no alcanzan y las baterías completan lo que falta.',
        exporting: r.chargeW > 1
            ? 'Los paneles producen más de lo que gasta la casa: el sobrante carga las baterías y lo que aún sobra se entrega a la red.'
            : 'Las baterías están llenas: lo que sobra de los paneles se entrega a la red.',
        curtailed: 'Las baterías están llenas y la red está caída: los paneles producen solo lo que la casa necesita.',
        charging: 'Los paneles producen más de lo que gasta la casa: el sobrante carga las baterías.',
        direct: 'Los paneles cubren justo lo que gasta la casa.',
        idle: 'Todos los equipos están apagados.',
    };
    const backup = !r.gridUp && r.mode !== 'blackout' && r.demandW > 0 ? ' La red está caída, pero la casa sigue con luz.' : '';

    return sentences[r.mode] + backup;
};
