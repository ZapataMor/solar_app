import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';
import { BACK, block, box, CEILING_Y, mesh, ROOF_Y } from './house.js';
import { DEVICE_WATTS, formatPower, PANEL_TILT, RESERVE } from './simulation.js';
import { glow, solarCells } from './textures.js';

/**
 * The pieces of the system. Each one is a group with the text to tell about it in `userData.info`
 * ({title, text, live(readout)}: `live` says what it is doing right now). Their screens are canvas planes
 * that `paint(readout)` repaints a few times a second; the readout comes from simulation.js.
 */

const MONO = '"IBM Plex Mono", ui-monospace, monospace';
const LCD = { label: '#7dd3c8', amber: '#ffd34d', green: '#4ade80', cyan: '#38bdf8', red: '#f87171', violet: '#c084fc', dim: '#5b6b73', text: '#e8f1f2' };

const rounded = (width, height, depth, radius, material, x = 0, y = 0, z = 0) => {
    const object = mesh(new RoundedBoxGeometry(width, height, depth, 3, radius), material);
    object.position.set(x, y, z);

    return object;
};

const UP = new THREE.Vector3(0, 1, 0);

/** A bar from one point to another (the legs and braces of the rack). */
const strut = (from, to, thickness, material) => {
    const direction = to.clone().sub(from);
    const object = mesh(new THREE.BoxGeometry(thickness, direction.length(), thickness), material);
    object.position.copy(from).addScaledVector(direction, 0.5);
    object.quaternion.setFromUnitVectors(UP, direction.normalize());

    return object;
};

const cylinderZ = (radius, depth, material, x = 0, y = 0, z = 0) => {
    const object = mesh(new THREE.CylinderGeometry(radius, radius, depth, 24), material);
    object.rotation.x = Math.PI / 2;
    object.position.set(x, y, z);

    return object;
};

/** A plane facing +z painted on a canvas; `draw(context, width, height, readout)`. */
const screen = (width, height, draw, { resolution = 256 } = {}) => {
    const canvas = document.createElement('canvas');
    canvas.width = resolution;
    canvas.height = Math.max(8, Math.round((resolution * height) / width));
    const context = canvas.getContext('2d');
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.anisotropy = 4;
    const plane = mesh(
        new THREE.PlaneGeometry(width, height),
        new THREE.MeshBasicMaterial({ map: texture, toneMapped: false }),
        { cast: false, receive: false },
    );
    const paint = (readout) => {
        context.clearRect(0, 0, canvas.width, canvas.height);
        draw(context, canvas.width, canvas.height, readout);
        texture.needsUpdate = true;
    };

    return { plane, paint };
};

const text = (context, value, x, y, { size, color = LCD.text, align = 'left', weight = 'bold' }) => {
    context.fillStyle = color;
    context.font = `${weight} ${size}px ${MONO}`;
    context.textAlign = align;
    context.textBaseline = 'middle';
    context.fillText(value, x, y);
};

const lcd = (context, w, h, background = '#0d2a2e') => {
    context.fillStyle = background;
    context.fillRect(0, 0, w, h);
};

/** A small triangle, up or down: the direction of an exchange. */
const arrow = (context, x, y, size, up, color) => {
    context.fillStyle = color;
    context.beginPath();
    if (up) {
        context.moveTo(x, y - size);
        context.lineTo(x + size, y + size);
        context.lineTo(x - size, y + size);
    } else {
        context.moveTo(x, y + size);
        context.lineTo(x + size, y - size);
        context.lineTo(x - size, y - size);
    }
    context.closePath();
    context.fill();
};

const number = (value, decimals = 0) => value.toLocaleString('es-CO', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });

/** A flat label on a wall (the name under a device). */
const nameplate = (value, width, color = '#2b3138') => {
    const { plane } = screen(width, 0.09, (context, w, h) => {
        text(context, value, w / 2, h / 2, { size: h * 0.62, color, align: 'center' });
    }, { resolution: 256 });

    return plane;
};

const led = (radius, color = '#222') => {
    const material = new THREE.MeshBasicMaterial({ color, toneMapped: false });

    return { object: mesh(new THREE.SphereGeometry(radius, 10, 8), material, { cast: false, receive: false }), material };
};

const BANK_STATES = {
    charging: { label: 'CARGANDO', color: LCD.green },
    discharging: { label: 'DESCARGANDO', color: LCD.amber },
    full: { label: 'LLENAS', color: LCD.green },
    reserve: { label: 'RESERVA', color: LCD.red },
    idle: { label: 'EN REPOSO', color: LCD.dim },
};

/**
 * The array: `columns` × `rows` panels on an aluminum rack over the flat roof, tilted toward the south.
 * `frame` is the plane of the panels (local +z runs down the slope, +y out of the glass): the sunlight lands
 * there. `exit` is the junction box behind the top edge, in that frame, at `exitX` in the world.
 */
export const buildPanels = (m, { x = 0, front = 1.75, lift = 0.35, columns = 4, rows = 2, exitX = 0 } = {}) => {
    const group = new THREE.Group();
    const frame = new THREE.Group();
    const size = { x: 1.65, z: 1.0 };
    const gap = 0.05;
    const width = columns * size.x + (columns - 1) * gap;
    const depth = rows * size.z + (rows - 1) * gap;
    frame.position.set(x, ROOF_Y + lift + depth * Math.sin(PANEL_TILT), front - depth * Math.cos(PANEL_TILT));
    frame.rotation.x = PANEL_TILT;
    group.add(frame);

    const glass = new THREE.MeshPhysicalMaterial({
        map: solarCells(),
        roughness: 0.18,
        metalness: 0.25,
        clearcoat: 1,
        clearcoatRoughness: 0.08,
        emissive: '#1d3f8f',
        emissiveIntensity: 0.12,
        envMapIntensity: 1.4,
    });
    const positions = [];

    // Two aluminum rails under each row, across the whole array.
    for (let row = 0; row < rows; row++) {
        const centerZ = size.z / 2 + row * (size.z + gap);
        [-0.3, 0.3].forEach((offset) => frame.add(box(width + 0.2, 0.05, 0.05, m.panelFrame, 0, -0.025, centerZ + offset)));
    }
    for (let row = 0; row < rows; row++) {
        for (let column = 0; column < columns; column++) {
            const px = (column - (columns - 1) / 2) * (size.x + gap);
            const pz = size.z / 2 + row * (size.z + gap);
            const panel = new THREE.Group();
            panel.position.set(px, 0, pz);
            panel.add(box(size.x, 0.04, size.z, m.panelFrame, 0, 0.02, 0));
            const face = mesh(new THREE.PlaneGeometry(size.x - 0.06, size.z - 0.06), glass, { cast: false });
            face.rotation.x = -Math.PI / 2;
            face.position.y = 0.042;
            panel.add(face);
            frame.add(panel);
            positions.push({ x: px, z: pz });
        }
    }

    // The rack: legs from the roof to the rails, a brace between each pair, and feet along the slab.
    group.updateMatrixWorld(true);
    const under = (lx, lz) => frame.localToWorld(new THREE.Vector3(lx, -0.05, lz));
    const onRoof = (point) => new THREE.Vector3(point.x, ROOF_Y, point.z);
    [-1, -1 / 3, 1 / 3, 1].forEach((k) => {
        const lx = k * (width / 2 - 0.25);
        const high = under(lx, 0.3);
        const low = under(lx, depth - 0.3);
        group.add(strut(onRoof(high), high, 0.05, m.panelFrame), strut(onRoof(low), low, 0.05, m.panelFrame), strut(onRoof(low), high, 0.035, m.panelFrame));
    });
    [under(0, 0.3).z, under(0, depth - 0.3).z].forEach((z) => group.add(box(width - 0.3, 0.04, 0.08, m.panelFrame, x, ROOF_Y + 0.02, z)));

    // The junction box behind the top edge, where the cable to the house starts.
    const exit = { x: exitX - x, y: -0.13, z: 0.15 };
    frame.add(box(0.18, 0.09, 0.12, m.dark, exit.x, -0.085, exit.z));

    group.userData.info = {
        title: 'Paneles solares',
        text: 'Convierten la luz del sol en electricidad de corriente continua (CC). Están inclinados hacia el sur, sobre una estructura en el techo, para recibir más sol.',
        live: (r) => {
            if (r.pvW > 5) {
                return `Ahora: producen ${formatPower(r.pvW)}${r.cloud > 0.5 ? ' (nublado: llega menos sol)' : ''}.`;
            }

            return r.daylight < 0.12 ? 'Ahora: no producen, es de noche.' : 'Ahora: casi no producen, llega muy poco sol.';
        },
    };

    return {
        group,
        frame,
        positions,
        exit,
        /** `light` is 0 at night and 1 in full sun: the glass only glows with the sun. */
        update: (time, light) => { glass.emissiveIntensity = (0.12 + 0.05 * Math.sin(time * 1.6)) * light; },
    };
};

export const buildController = (m, x, y) => {
    const group = new THREE.Group();
    group.position.set(x, y, BACK + 0.07);
    group.add(rounded(0.55, 0.75, 0.14, 0.02, m.casing));
    group.add(box(0.55, 0.06, 0.145, m.accent, 0, 0.345, 0));
    const lcdScreen = screen(0.42, 0.3, (context, w, h, r) => {
        lcd(context, w, h);
        text(context, 'PANELES', 10, h * 0.12, { size: h * 0.11, color: LCD.label });
        text(context, formatPower(r.pvW), w - 10, h * 0.12, { size: h * 0.15, color: r.pvW > 5 ? LCD.amber : LCD.dim, align: 'right' });
        text(context, `${number(r.pvVolts)} V   ${number(r.pvAmps, 1)} A`, 10, h * 0.31, { size: h * 0.12, color: r.pvW > 5 ? LCD.text : LCD.dim });
        text(context, 'BATERÍA', 10, h * 0.53, { size: h * 0.11, color: LCD.label });
        const bank = BANK_STATES[r.bank];
        text(context, bank.label, w - 10, h * 0.53, { size: h * 0.12, color: bank.color, align: 'right' });
        text(context, `${number(r.soc * 100)} %`, 10, h * 0.72, { size: h * 0.17, color: r.locked ? LCD.red : LCD.green });
        context.fillStyle = '#1b4a50';
        context.fillRect(10, h * 0.87, w - 20, h * 0.07);
        context.fillStyle = r.locked ? LCD.red : LCD.green;
        context.fillRect(10, h * 0.87, (w - 20) * r.soc, h * 0.07);
    }, { resolution: 320 });
    lcdScreen.plane.position.set(0, 0.12, 0.075);
    group.add(lcdScreen.plane);
    const plate = nameplate('REGULADOR', 0.46);
    plate.position.set(0, -0.2, 0.075);
    group.add(plate);
    const sub = nameplate('MPPT', 0.2, '#6b7280');
    sub.position.set(0, -0.29, 0.075);
    group.add(sub);
    group.userData.info = {
        title: 'Regulador de carga (MPPT)',
        text: 'Toma la energía de los paneles, ajusta la tensión para aprovechar al máximo el sol y carga las baterías sin dejar que se sobrecarguen.',
        live: (r) => {
            if (r.pvW <= 5) {
                return 'Ahora: los paneles no producen, está en espera.';
            }

            return r.chargeW > 1 ? `Ahora: recibe ${formatPower(r.pvW)} y carga las baterías con ${formatPower(r.chargeW)}.` : `Ahora: recibe ${formatPower(r.pvW)} y se los pasa al inversor.`;
        },
    };

    return { group, paint: lcdScreen.paint, top: new THREE.Vector3(x, y + 0.375, BACK + 0.03), bottom: new THREE.Vector3(x, y - 0.375, BACK + 0.03) };
};

/** The bank: `columns` × `rows` batteries on a metal rack, wired in series with copper straps. */
export const buildBatteries = (m, x, { columns = 2, rows = 2 } = {}) => {
    const group = new THREE.Group();
    const size = { w: 0.42, h: 0.36, d: 0.3 };
    const gap = 0.08;
    const levels = Array.from({ length: rows }, (_, row) => 0.1 + row * (size.h + 0.1));
    const inner = columns * size.w + (columns - 1) * gap + 0.1;
    const depth = 0.36;
    const z = BACK + depth / 2 + 0.04;
    const height = levels[rows - 1] + size.h + 0.04;
    const terminalZ = z - 0.05;
    const screens = [];
    const terminals = [];

    // The rack: four posts and a shelf under each level.
    [-1, 1].forEach((sx) => [-1, 1].forEach((sz) => group.add(box(0.035, height, 0.035, m.rack, x + sx * (inner / 2 + 0.0175), height / 2, z + sz * (depth / 2 - 0.0175)))));
    levels.forEach((level) => group.add(box(inner + 0.07, 0.03, depth, m.rack, x, level - 0.015, z)));

    for (let row = 0; row < rows; row++) {
        for (let column = 0; column < columns; column++) {
            const cx = x + (column - (columns - 1) / 2) * (size.w + gap);
            const bottom = levels[row];
            group.add(rounded(size.w, size.h, size.d, 0.02, m.battery, cx, bottom + size.h / 2, z));
            group.add(box(size.w + 0.002, 0.045, size.d + 0.002, m.batteryTop, cx, bottom + size.h - 0.0225, z));
            [-1, 1].forEach((side) => group.add(mesh(new THREE.CylinderGeometry(0.025, 0.025, 0.04, 10), side > 0 ? m.copper : m.dark)
                .translateX(cx + side * 0.12).translateY(bottom + size.h + 0.02).translateZ(terminalZ)));
            const level = screen(0.3, 0.16, (context, w, h, r) => {
                lcd(context, w, h, '#101820');
                const bars = 5;
                const color = r.locked ? LCD.red : r.soc < 0.3 ? LCD.amber : LCD.green;
                for (let bar = 0; bar < bars; bar++) {
                    context.fillStyle = r.soc > (bar + 0.5) / bars ? color : '#20352f';
                    context.fillRect(8 + bar * ((w - 16) / bars), h * 0.12, (w - 16) / bars - 6, h * 0.4);
                }
                text(context, `${number(r.soc * 100)} %`, w / 2, h * 0.67, { size: h * 0.22, color: '#c7f9e0', align: 'center' });
                const bank = BANK_STATES[r.bank];
                text(context, bank.label, w / 2, h * 0.89, { size: h * 0.15, color: bank.color, align: 'center' });
            }, { resolution: 192 });
            level.plane.position.set(cx, bottom + size.h * 0.45, z + size.d / 2 + 0.003);
            group.add(level.plane);
            screens.push(level);
            if (row === rows - 1) {
                terminals.push(new THREE.Vector3(cx - 0.12, bottom + size.h + 0.04, terminalZ), new THREE.Vector3(cx + 0.12, bottom + size.h + 0.04, terminalZ));
            }
        }
        // A copper strap along the row: the batteries are joined in series.
        group.add(box((columns - 1) * (size.w + gap) + 0.28, 0.015, 0.03, m.copper, x, levels[row] + size.h + 0.045, terminalZ));
    }
    // And one down the side, from the upper row to the lower one.
    if (rows > 1) {
        group.add(box(0.03, levels[rows - 1] - levels[0], 0.015, m.copper, x - inner / 2 + 0.06, (levels[0] + levels[rows - 1]) / 2 + size.h + 0.045, z + size.d / 2 + 0.02));
    }

    group.userData.info = {
        title: 'Acumuladores (baterías)',
        text: `Guardan la energía que sobra del día para usarla de noche o cuando hay nubes. Por protección, el sistema no las baja de ${Math.round(RESERVE * 100)} %.`,
        live: (r) => {
            const level = `${number(r.soc * 100)} %`;
            const states = {
                charging: `cargándose con ${formatPower(r.chargeW)}`,
                discharging: `entregando ${formatPower(r.dischargeW)} a la casa`,
                full: 'llenas',
                reserve: 'en su reserva mínima: no se usan hasta que se recarguen',
                idle: 'en reposo',
            };

            return `Ahora: ${level}, ${states[r.bank]}.`;
        },
    };

    return {
        group,
        paint: (readout) => screens.forEach((level) => level.paint(readout)),
        /** Where the regulator's cable comes in (right) and where the inverter's goes out (left). */
        chargeIn: terminals[terminals.length - 1],
        dischargeOut: terminals[0],
    };
};

export const buildInverter = (m, x, y) => {
    const group = new THREE.Group();
    group.position.set(x, y, BACK + 0.1);
    group.add(rounded(0.8, 1.0, 0.2, 0.025, m.casing));
    group.add(box(0.8, 0.08, 0.205, m.inverter, 0, 0.46, 0));
    const states = { on: { label: 'EN LÍNEA', color: LCD.green }, standby: { label: 'EN ESPERA', color: LCD.amber }, off: { label: 'APAGADO', color: LCD.red } };
    const lcdScreen = screen(0.54, 0.34, (context, w, h, r) => {
        const active = r.inverterState === 'on';
        lcd(context, w, h);
        text(context, 'ENTRADA CC', 10, h * 0.12, { size: h * 0.1, color: LCD.label });
        text(context, `${number(r.busVolts, 1)} V`, w - 10, h * 0.12, { size: h * 0.13, color: r.inverterDcW > 1 ? LCD.amber : LCD.dim, align: 'right' });
        text(context, 'SALIDA CA', 10, h * 0.31, { size: h * 0.1, color: LCD.label });
        text(context, active ? '120 V  60 Hz' : '--- V', w - 10, h * 0.31, { size: h * 0.13, color: active ? LCD.cyan : LCD.dim, align: 'right' });
        text(context, formatPower(r.inverterAcW), w / 2, h * 0.58, { size: h * 0.26, color: active ? LCD.green : LCD.dim, align: 'center' });
        const state = states[r.inverterState];
        text(context, state.label, w / 2, h * 0.82, { size: h * 0.12, color: state.color, align: 'center' });
        context.fillStyle = '#1b4a50';
        context.fillRect(10, h * 0.93, w - 20, h * 0.04);
        context.fillStyle = LCD.green;
        context.fillRect(10, h * 0.93, (w - 20) * Math.min(1, r.inverterAcW / 3500), h * 0.04);
    }, { resolution: 352 });
    lcdScreen.plane.position.set(0, 0.14, 0.105);
    group.add(lcdScreen.plane);
    const plate = nameplate('INVERSOR', 0.5, '#16958a');
    plate.position.set(0, -0.12, 0.105);
    group.add(plate);
    for (let index = 0; index < 5; index++) {
        group.add(box(0.6, 0.012, 0.215, m.metal, 0, -0.27 - index * 0.035, 0));
    }
    group.userData.info = {
        title: 'Inversor',
        text: 'Convierte la corriente continua (CC) de las baterías y los paneles en corriente alterna (CA), que es la que usan los equipos de la casa.',
        live: (r) => {
            if (r.inverterState === 'off') {
                return 'Ahora: apagado, no tiene de dónde tomar energía.';
            }
            if (r.inverterState === 'standby') {
                return r.gridImportW > 1 ? 'Ahora: en espera, la casa está tomando la electricidad de la red.' : 'Ahora: en espera, la casa no está pidiendo energía.';
            }

            return `Ahora: entrega ${formatPower(r.inverterAcW)} de corriente alterna.`;
        },
    };

    const port = (dx, dy) => new THREE.Vector3(x + dx, y + dy, BACK + 0.03);

    return { group, paint: lcdScreen.paint, dcIn: port(0.2, -0.5), acOut: port(-0.35, -0.5) };
};

export const buildBreaker = (m, x, y) => {
    const group = new THREE.Group();
    group.position.set(x, y, BACK + 0.06);
    group.add(box(0.5, 0.65, 0.12, m.casing));
    group.add(box(0.44, 0.58, 0.02, m.dark, 0, 0, 0.06));
    for (let row = 0; row < 3; row++) {
        [-1, 1].forEach((side) => group.add(box(0.12, 0.08, 0.04, row === 0 ? m.accent : m.plastic, side * 0.11, 0.1 - row * 0.15, 0.085)));
    }
    // One light per circuit: green when it has electricity, red when it should and does not.
    const lights = ['lamp', 'tv', 'fridge'].map((key, index) => {
        const circuit = led(0.016);
        circuit.object.position.set((index - 1) * 0.11, 0.24, 0.075);
        group.add(circuit.object);

        return { key, ...circuit };
    });
    const plate = nameplate('TABLERO', 0.44, '#e8f1f2');
    plate.position.set(0, -0.28, 0.072);
    plate.scale.setScalar(0.9);
    group.add(plate);
    group.userData.info = {
        title: 'Tablero de protecciones',
        text: 'Reparte la corriente alterna a cada circuito de la casa y la corta si hay una sobrecarga o un cortocircuito. Cada luz es un circuito: lámpara, televisor y nevera.',
        live: (r) => (r.blackout ? 'Ahora: sin electricidad en ningún circuito.' : `Ahora: la casa está pidiendo ${formatPower(r.servedW)}.`),
    };

    const port = (dx, dy) => new THREE.Vector3(x + dx, y + dy, BACK + 0.03);

    return {
        group,
        paint: (r) => lights.forEach(({ key, material }) => {
            material.color.set(r.powered[key] ? '#4ade80' : r.on[key] && r.blackout ? '#f87171' : '#2a3a30');
        }),
        /** From the inverter (below), to the meter (left), and the three circuits, up into the ceiling. */
        bottomIn: port(-0.1, -0.325),
        left: port(-0.25, 0.1),
        top: [port(-0.15, 0.325), port(0, 0.325), port(0.15, 0.325)],
    };
};

/** Bidirectional meter: it counts what comes in from the grid and what goes out to it. */
export const buildMeter = (m, x, y) => {
    const group = new THREE.Group();
    group.position.set(x, y, BACK + 0.07);
    group.add(cylinderZ(0.3, 0.14, m.casing));
    group.add(cylinderZ(0.26, 0.04, m.glass, 0, 0, 0.09));
    const display = screen(0.4, 0.2, (context, w, h, r) => {
        lcd(context, w, h, '#14232a');
        text(context, 'RED A CASA', 8, h * 0.16, { size: h * 0.14, color: LCD.label });
        text(context, `${number(r.importKwh, 1)} kWh`, w - 8, h * 0.16, { size: h * 0.17, color: r.gridImportW > 1 ? LCD.violet : '#c7d2d6', align: 'right' });
        text(context, 'CASA A RED', 8, h * 0.42, { size: h * 0.14, color: LCD.label });
        text(context, `${number(r.exportKwh, 1)} kWh`, w - 8, h * 0.42, { size: h * 0.17, color: r.exportW > 1 ? LCD.green : '#c7d2d6', align: 'right' });
        context.fillStyle = '#1f3a42';
        context.fillRect(8, h * 0.58, w - 16, 2);
        if (!r.gridUp) {
            text(context, 'SIN RED', w / 2, h * 0.8, { size: h * 0.2, color: LCD.red, align: 'center' });
        } else if (r.gridW > 5) {
            arrow(context, w * 0.18, h * 0.8, h * 0.09, true, LCD.green);
            text(context, `ENTREGA ${formatPower(r.gridW)}`, w * 0.28, h * 0.8, { size: h * 0.16, color: LCD.green });
        } else if (r.gridW < -5) {
            arrow(context, w * 0.18, h * 0.8, h * 0.09, false, LCD.violet);
            text(context, `RECIBE ${formatPower(r.gridW)}`, w * 0.28, h * 0.8, { size: h * 0.16, color: LCD.violet });
        } else {
            text(context, 'SIN INTERCAMBIO', w / 2, h * 0.8, { size: h * 0.15, color: LCD.dim, align: 'center' });
        }
    }, { resolution: 320 });
    display.plane.position.set(0, 0.04, 0.072);
    group.add(display.plane);
    const plate = nameplate('MEDIDOR', 0.3, '#2b3138');
    plate.position.set(0, -0.19, 0.072);
    plate.scale.setScalar(0.8);
    group.add(plate);
    group.userData.info = {
        title: 'Medidor bidireccional',
        text: 'Mide lo que la casa toma de la red y los excedentes que le entrega cuando los paneles producen más de lo que se gasta.',
        live: (r) => {
            if (!r.gridUp) {
                return 'Ahora: sin red, no puede medir nada.';
            }
            if (r.gridW > 5) {
                return `Ahora: la casa le entrega ${formatPower(r.gridW)} a la red.`;
            }

            return r.gridW < -5 ? `Ahora: la casa toma ${formatPower(r.gridW)} de la red.` : 'Ahora: no hay intercambio con la red.';
        },
    };

    return { group, paint: display.paint, left: new THREE.Vector3(x - 0.3, y, BACK + 0.03), right: new THREE.Vector3(x + 0.3, y, BACK + 0.03) };
};

/** The light of the house: a bulb that hangs from the ceiling on its cord. */
export const buildLamp = (m, x, z) => {
    const group = new THREE.Group();
    group.position.set(x, CEILING_Y, z);
    const cord = 0.4;
    const socketY = -0.03 - cord - 0.045;
    const bulbY = socketY - 0.045 - 0.07;

    group.add(mesh(new THREE.CylinderGeometry(0.075, 0.075, 0.03, 16), m.plastic, { cast: false }).translateY(-0.015));
    group.add(mesh(new THREE.CylinderGeometry(0.034, 0.034, 0.09, 12), m.metal).translateY(socketY));
    const bulbMaterial = new THREE.MeshBasicMaterial({ color: '#8f8a7c', toneMapped: false });
    const bulb = mesh(new THREE.SphereGeometry(0.07, 18, 14), bulbMaterial, { cast: false });
    bulb.position.y = bulbY;
    const neck = mesh(new THREE.CylinderGeometry(0.03, 0.05, 0.06, 12), bulbMaterial, { cast: false });
    neck.position.y = socketY - 0.045 - 0.01;
    group.add(bulb, neck);

    const halo = new THREE.Sprite(new THREE.SpriteMaterial({ map: glow(), color: '#ffd58a', blending: THREE.AdditiveBlending, transparent: true, depthWrite: false, opacity: 0, fog: false }));
    halo.scale.setScalar(0.9);
    halo.position.y = bulbY;
    group.add(halo);
    // Always counted as a light, so turning it on or off never makes the scene rebuild its shaders.
    const light = new THREE.PointLight('#ffd9a0', 0, 7, 2);
    light.position.y = bulbY - 0.05;
    group.add(light);

    group.userData.info = {
        title: 'Foco',
        text: `Es un equipo que gasta energía: recibe la electricidad del tablero y la convierte en luz. Un foco LED usa unos ${DEVICE_WATTS.lamp} W.`,
        live: (r) => {
            if (!r.on.lamp) {
                return 'Ahora: apagado.';
            }

            return r.powered.lamp ? `Ahora: encendido, gastando ${DEVICE_WATTS.lamp} W.` : 'Ahora: no tiene electricidad.';
        },
    };

    const off = new THREE.Color('#8f8a7c');
    const on = new THREE.Color('#fff1c0');
    let level = 0;

    return {
        group,
        /** Where the cord starts at the ceiling, and where it ends at the socket. */
        rose: new THREE.Vector3(x, CEILING_Y - 0.05, z),
        socket: new THREE.Vector3(x, CEILING_Y - 0.03 - cord, z),
        update: (dt, r) => {
            const target = r.powered.lamp ? 1 : 0;
            level = dt > 0 ? level + (target - level) * (1 - Math.exp(-dt * 7)) : target;
            bulbMaterial.color.lerpColors(off, on, level);
            // By day the light is hardly seen: the halo only shows in the dark.
            halo.material.opacity = 0.85 * level * (1 - 0.9 * r.daylight);
            light.intensity = 12 * level;
        },
    };
};

/** A television on the wall, over a low console. */
export const buildTv = (m, x, y = 1.35) => {
    const group = new THREE.Group();
    group.add(box(1.0, 0.42, 0.38, m.wood, x, 0.23, BACK + 0.19));
    group.add(box(1.02, 0.025, 0.4, m.counterTop, x, 0.4525, BACK + 0.19));
    group.add(box(0.9, 0.52, 0.045, m.dark, x, y, BACK + 0.045));
    const picture = screen(0.84, 0.46, (context, w, h, r) => {
        if (!r.powered.tv) {
            // Off: dark glass with a faint reflection.
            const glass = context.createLinearGradient(0, 0, w, h);
            glass.addColorStop(0, '#12161b');
            glass.addColorStop(0.5, '#1b2128');
            glass.addColorStop(1, '#0d1014');
            context.fillStyle = glass;
            context.fillRect(0, 0, w, h);

            return;
        }
        const t = r.time;
        const gradient = context.createLinearGradient(0, 0, w, h);
        gradient.addColorStop(0, `hsl(${(t * 20) % 360} 70% 45%)`);
        gradient.addColorStop(1, `hsl(${(t * 20 + 120) % 360} 70% 30%)`);
        context.fillStyle = gradient;
        context.fillRect(0, 0, w, h);
        context.fillStyle = 'rgba(255,255,255,0.35)';
        context.beginPath();
        context.arc(w * (0.2 + 0.6 * ((t * 0.08) % 1)), h * 0.6, h * 0.14, 0, Math.PI * 2);
        context.fill();
        context.fillStyle = 'rgba(255,255,255,0.18)';
        context.fillRect(0, h * 0.78, w, h * 0.22);
    }, { resolution: 256 });
    picture.plane.position.set(x, y, BACK + 0.069);
    group.add(picture.plane);
    const standby = led(0.012);
    standby.object.position.set(x + 0.4, y - 0.245, BACK + 0.069);
    group.add(standby.object);

    group.userData.info = {
        title: 'Televisor',
        text: `Es un equipo que gasta energía mientras está prendido: usa alrededor de ${DEVICE_WATTS.tv} W. Recibe la electricidad del tablero.`,
        live: (r) => {
            if (!r.on.tv) {
                return 'Ahora: apagado.';
            }

            return r.powered.tv ? `Ahora: prendido, gastando ${DEVICE_WATTS.tv} W.` : 'Ahora: no tiene electricidad.';
        },
    };

    return {
        group,
        paint: (r) => {
            picture.paint(r);
            standby.material.color.set(r.powered.tv ? '#4ade80' : r.on.tv ? '#f87171' : '#ef4444');
        },
        port: new THREE.Vector3(x - 0.45, y, BACK + 0.03),
    };
};

export const buildFridge = (m, x) => {
    const group = new THREE.Group();
    const z = BACK + 0.35;
    group.add(rounded(0.7, 1.7, 0.65, 0.03, m.steel, x, 0.87, z));
    group.add(box(0.7, 0.012, 0.655, m.dark, x, 0.57, z));
    [1.15, 0.8].forEach((y) => group.add(box(0.03, y === 1.15 ? 0.3 : 0.22, 0.04, m.metal, x - 0.28, y + 0.14, z + 0.345)));
    const motor = led(0.014);
    motor.object.position.set(x + 0.25, 1.55, z + 0.33);
    group.add(motor.object);
    group.userData.info = {
        title: 'Nevera',
        text: 'Es un equipo que gasta energía las 24 horas: su motor prende y apaga todo el día. Por eso pesa tanto en el recibo.',
        live: (r) => {
            if (!r.on.fridge) {
                return 'Ahora: desconectada.';
            }
            if (r.blackout) {
                return 'Ahora: no tiene electricidad.';
            }

            return r.fridgeRunning ? `Ahora: el motor está prendido, gastando ${DEVICE_WATTS.fridge} W.` : 'Ahora: el motor descansa, casi no gasta.';
        },
    };

    return {
        group,
        paint: (r) => motor.material.color.set(r.powered.fridge ? '#4ade80' : r.on.fridge && !r.blackout ? '#2b6b45' : '#2a2e33'),
        side: new THREE.Vector3(x - 0.36, 1.3, BACK + 0.03),
    };
};

/* ---- The house around the system: it is not hoverable, it only makes it a home. ---- */

/** The plywood board the equipment is mounted on, as installers do. */
export const buildBoard = (m, xs, ys) => block(xs, ys, [BACK, BACK + 0.018], m.board);

/** A rug on the living room floor. */
export const buildRug = (m, xs, zs) => block(xs, [0.02, 0.032], zs, m.rug, { cast: false });

/** A two-seat sofa, its back to the viewer, facing the television. */
export const buildSofa = (m, x, z, width = 1.7) => {
    const group = new THREE.Group();
    const depth = 0.85;
    group.position.set(x, 0.02, z);
    [-1, 1].forEach((sx) => [-1, 1].forEach((sz) => group.add(mesh(new THREE.CylinderGeometry(0.025, 0.02, 0.08, 8), m.wood)
        .translateX(sx * (width / 2 - 0.08)).translateY(0.04).translateZ(sz * (depth / 2 - 0.08)))));
    group.add(rounded(width, 0.2, depth, 0.04, m.sofa, 0, 0.18, 0));
    [-1, 1].forEach((side) => group.add(rounded(width / 2 - 0.2, 0.14, depth - 0.25, 0.05, m.cushion, side * (width / 4 - 0.04), 0.35, -0.08)));
    group.add(rounded(width - 0.1, 0.48, 0.2, 0.06, m.sofa, 0, 0.52, depth / 2 - 0.1));
    [-1, 1].forEach((side) => group.add(rounded(0.16, 0.36, depth, 0.05, m.sofa, side * (width / 2 - 0.08), 0.38, 0)));
    // Two cushions against the back.
    [-1, 1].forEach((side) => {
        const pillow = rounded(0.36, 0.3, 0.1, 0.05, m.cushion, side * 0.42, 0.56, depth / 2 - 0.26);
        pillow.rotation.x = -0.25;
        group.add(pillow);
    });

    return group;
};

/** The kitchen counter under the window, with its doors, sink and tap. */
export const buildKitchen = (m, [x0, x1]) => {
    const group = new THREE.Group();
    const depth = 0.6;
    const z = BACK + depth / 2;
    const x = (x0 + x1) / 2;
    const width = x1 - x0;
    group.add(box(width, 0.86, depth - 0.02, m.cabinet, x, 0.45, z - 0.01));
    for (let door = 0; door < 2; door++) {
        const dx = x0 + (width / 2) * (door + 0.5);
        group.add(box(width / 2 - 0.03, 0.74, 0.02, m.cabinetDoor, dx, 0.45, z + depth / 2 - 0.01));
        group.add(box(0.12, 0.015, 0.02, m.metal, dx, 0.76, z + depth / 2 + 0.01));
    }
    group.add(box(width + 0.04, 0.04, depth + 0.02, m.counterTop, x, 0.9, z));
    group.add(box(0.46, 0.012, 0.34, m.steel, x + 0.12, 0.925, z - 0.03));
    group.add(box(0.4, 0.012, 0.28, m.dark, x + 0.12, 0.927, z - 0.03));
    const tap = new THREE.CatmullRomCurve3([
        new THREE.Vector3(x + 0.12, 0.92, BACK + 0.06),
        new THREE.Vector3(x + 0.12, 1.15, BACK + 0.07),
        new THREE.Vector3(x + 0.12, 1.18, BACK + 0.15),
        new THREE.Vector3(x + 0.12, 1.08, BACK + 0.21),
    ]);
    group.add(mesh(new THREE.TubeGeometry(tap, 16, 0.012, 8), m.steel));

    return group;
};
