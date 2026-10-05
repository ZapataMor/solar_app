import * as THREE from 'three';
import { BACK, buildHouse, buildPole, buildScenery, CEILING_Y, HOUSE, mesh, ROOF_Y } from './house.js';
import {
    buildBatteries, buildBoard, buildBreaker, buildController, buildFridge, buildInverter, buildKitchen, buildLamp, buildMeter,
    buildPanels, buildRug, buildSofa, buildTv,
} from './equipment.js';
import { cable, sunlight } from './flow.js';
import { formatPower, PV_PEAK_W, smoothstep } from './simulation.js';

/**
 * The hybrid system with batteries, in a cutaway house: panels on the roof → regulator → batteries →
 * inverter → breaker → the circuits of the living room, and the meter that joins the house with the grid in
 * both directions. The equipment lives in the utility room (left); the loads, in the living room (right).
 * It illustrates how the system works; it is not a specific installation.
 *
 * `buildSystem(materials)` returns the group, an `update(dt, elapsed, readout)` that shows what the
 * simulation says (simulation.js) and the `hoverables`: the objects that say what they are when pointed
 * at (each with `userData.info = {title, text, live(readout)}`).
 */

/** Where the camera looks, and how far it stands to see all of it (the pole on the left included). */
export const FRAME = { target: new THREE.Vector3(-0.5, 2.4, 0.3), radius: 4.0 };

const p = (x, y, z) => new THREE.Vector3(x, y, z);
const LOW_SUN = new THREE.Color('#ffad5c');
const HIGH_SUN = new THREE.Color('#fff0bd');

/** "Now: 1.2 kW pass", or the words for when nothing does. */
const passing = (watts, idle) => (Math.abs(watts) < 5 ? idle : `Ahora: pasan ${formatPower(watts)}.`);

const gridFlow = (r) => {
    if (!r.gridUp) {
        return 'Ahora: la red está caída, no pasa corriente.';
    }
    if (r.gridW > 5) {
        return `Ahora: salen ${formatPower(r.gridW)} hacia la red.`;
    }

    return r.gridW < -5 ? `Ahora: entran ${formatPower(r.gridW)} desde la red.` : 'Ahora: sin corriente.';
};

export const buildSystem = (m) => {
    const group = new THREE.Group();
    const hoverables = [];
    const track = (object, info = object.userData.info) => {
        object.userData.info = info;
        hoverables.push(object);
        group.add(object);

        return object;
    };

    const house = buildHouse(m);
    group.add(house.group, buildScenery(m));

    // Where the cable from the panels goes through the roof: into the utility room, by the partition.
    const drop = -0.55;
    const panels = buildPanels(m, { x: -0.2, exitX: drop });

    // Utility room (left): the meter, the breaker, the inverter and the regulator on a plywood board, and
    // the batteries on their rack under them.
    group.add(buildBoard(m, [-4.15, -0.62], [1.1, 2.55]));
    const meter = buildMeter(m, -3.75, 2.05);
    const breaker = buildBreaker(m, -2.95, 1.75);
    const inverter = buildInverter(m, -1.95, 1.75);
    const controller = buildController(m, -0.95, 1.85);
    const batteries = buildBatteries(m, -1.75);

    // Living room (right): the television, a sofa on a rug, the kitchen under the window, the fridge and the
    // light that hangs from the ceiling.
    group.add(buildRug(m, [-0.05, 1.95], [-1.2, 1.6]), buildSofa(m, 0.95, 1.42), buildKitchen(m, [1.95, 3.05]));
    const tv = buildTv(m, 0.85);
    const fridge = buildFridge(m, 3.55);
    const lamp = buildLamp(m, 1.7, 0.15);

    [panels.group, controller.group, batteries.group, inverter.group, breaker.group, meter.group, lamp.group, tv.group, fridge.group].forEach((part) => track(part));
    const poleX = -5.4;
    const zw = BACK + 0.03;
    track(buildPole(m, poleX, zw, -1), {
        title: 'Red eléctrica',
        text: 'La red recibe los excedentes de la casa y le da energía cuando no alcanzan ni los paneles ni las baterías.',
        live: (r) => {
            if (!r.gridUp) {
                return 'Ahora: caída (apagón): no entrega ni recibe energía.';
            }
            if (r.gridW > 5) {
                return `Ahora: recibe ${formatPower(r.gridW)} de la casa.`;
            }

            return r.gridW < -5 ? `Ahora: le entrega ${formatPower(r.gridW)} a la casa.` : 'Ahora: conectada, sin intercambio.';
        },
    });

    // Cables, in the direction the current goes; `zw` is the wall's face. `watts` says how much goes
    // through now (a negative number runs backwards) and `scale` the amount that makes the pulses fastest.
    // The circuits of the living room run inside the roof slab, as they do in a concrete house.
    const out = panels.frame.localToWorld(p(panels.exit.x, panels.exit.y, panels.exit.z));
    const onRoof = ROOF_Y + 0.03;
    const inSlab = CEILING_Y + HOUSE.ceiling / 2;

    // Where the cable from the panels goes into the roof.
    const gland = mesh(new THREE.CylinderGeometry(0.05, 0.06, 0.06, 12), m.dark);
    gland.position.set(drop, ROOF_Y + 0.03, zw);
    group.add(gland);

    const DC = 'Cable de corriente continua (CC)';
    const AC = 'Cable de corriente alterna (CA)';
    const EXCHANGE = 'Cable de intercambio con la red';
    const loadScale = 160;
    const [lampCircuit, tvCircuit, fridgeCircuit] = breaker.top;
    const lines = [
        {
            kind: 'dc', title: DC, scale: PV_PEAK_W, watts: (r) => r.pvW,
            points: [out, p(out.x, onRoof, out.z), p(drop, onRoof, zw), p(drop, 2.45, zw), p(controller.top.x, 2.45, zw), controller.top],
            text: 'Baja de los paneles por el techo y lleva su energía al regulador. Cada pulso es corriente que avanza.',
            live: (r) => passing(r.pvW, r.daylight < 0.12 ? 'Ahora: sin corriente, es de noche.' : 'Ahora: sin corriente.'),
        },
        {
            kind: 'dc', title: DC, scale: PV_PEAK_W, watts: (r) => r.pvW,
            points: [controller.bottom, p(controller.bottom.x, 1.1, zw), p(batteries.chargeIn.x, 1.1, batteries.chargeIn.z), batteries.chargeIn],
            text: 'Lleva la energía que el regulador ya acondicionó hacia las baterías: de ahí la toman las baterías para cargarse y el inversor para la casa.',
            live: (r) => passing(r.pvW, 'Ahora: sin corriente.'),
        },
        {
            kind: 'dc', title: DC, scale: PV_PEAK_W, watts: (r) => r.inverterDcW,
            points: [batteries.dischargeOut, p(batteries.dischargeOut.x, 1.1, batteries.dischargeOut.z), p(inverter.dcIn.x, 1.1, zw), inverter.dcIn],
            text: 'Entrega al inversor la energía guardada en las baterías y la que llega de los paneles.',
            live: (r) => passing(r.inverterDcW, 'Ahora: sin corriente, el inversor no está trabajando.'),
        },
        {
            kind: 'ac', title: AC, scale: PV_PEAK_W, watts: (r) => r.inverterAcW,
            points: [inverter.acOut, p(inverter.acOut.x, 1.1, zw), p(breaker.bottomIn.x, 1.1, zw), breaker.bottomIn],
            text: 'Después del inversor la corriente ya es alterna: va al tablero.',
            live: (r) => passing(r.inverterAcW, 'Ahora: sin corriente.'),
        },
        {
            kind: 'ac', title: AC, scale: loadScale, watts: (r) => (r.powered.lamp ? r.loads.lamp : 0),
            points: [lampCircuit, p(lampCircuit.x, inSlab, zw), p(lamp.rose.x, inSlab, lamp.rose.z), lamp.socket],
            text: 'Sube del tablero al techo, cruza por dentro de la losa hasta la sala y baja por el cordón hasta el foco.',
            live: (r) => passing(r.powered.lamp ? r.loads.lamp : 0, 'Ahora: sin corriente.'),
        },
        {
            kind: 'ac', title: AC, scale: loadScale, watts: (r) => (r.powered.tv ? r.loads.tv : 0),
            points: [tvCircuit, p(tvCircuit.x, inSlab, zw), p(0.25, inSlab, zw), p(0.25, tv.port.y, zw), tv.port],
            text: 'Sube del tablero, cruza por la losa hasta la sala y baja por la pared hasta el televisor.',
            live: (r) => passing(r.powered.tv ? r.loads.tv : 0, 'Ahora: sin corriente.'),
        },
        {
            kind: 'ac', title: AC, scale: loadScale, watts: (r) => (r.powered.fridge ? r.loads.fridge : 0),
            points: [fridgeCircuit, p(fridgeCircuit.x, inSlab, zw), p(3.12, inSlab, zw), p(3.12, 1.3, zw), fridge.side.clone().setX(3.19)],
            text: 'Sube del tablero, cruza por la losa hasta la cocina y baja por la pared hasta la nevera.',
            live: (r) => passing(r.powered.fridge ? r.loads.fridge : 0, 'Ahora: sin corriente.'),
        },
        {
            kind: 'grid', title: EXCHANGE, scale: PV_PEAK_W, watts: (r) => r.gridW,
            points: [breaker.left, p(meter.right.x + 0.12, breaker.left.y, zw), p(meter.right.x + 0.12, meter.right.y, zw), meter.right],
            text: 'Une el tablero con el medidor: por aquí sale lo que sobra (verde) y entra lo que falta (violeta).',
            live: gridFlow,
        },
        {
            kind: 'grid', title: EXCHANGE, scale: PV_PEAK_W, watts: (r) => r.gridW,
            points: [meter.left, p(poleX + 0.14, meter.left.y, zw), p(poleX + 0.14, 5.3, zw)],
            text: 'Sale por el muro y une el medidor con la red eléctrica: los excedentes salen por aquí (verde) y, cuando falta energía, entra la de la red (violeta).',
            live: gridFlow,
        },
    ].map((config) => {
        const line = cable(config.points, config.kind, m);
        line.object.userData.info = { title: config.title, text: config.text, live: config.live };
        hoverables.push(line.object);
        group.add(line.object);

        return { line, watts: config.watts, scale: config.scale };
    });

    // The sunlight, seen as shafts that fall on the panels (the sun itself is out of the picture).
    const glassY = 0.044;
    const rays = sunlight(panels.positions.map(({ x, z }) => panels.frame.localToWorld(p(x, glassY, z))), panels.frame, panels.positions, glassY);
    group.add(rays.group);

    const screens = [controller, batteries, inverter, meter, breaker, fridge];
    const travel = new THREE.Vector3();
    const tint = new THREE.Color();
    let screenTimer = 0;
    let tvTimer = 0;

    return {
        group,
        hoverables,
        /** Shows the readout of the simulation; with `dt` = 0 (a redraw) everything lands at once. */
        update(dt, elapsed, r) {
            screenTimer -= dt;
            tvTimer -= dt;
            if (dt === 0 || screenTimer <= 0) {
                screenTimer = 0.25;
                screens.forEach((part) => part.paint(r));
            }
            if (dt === 0 || tvTimer <= 0) {
                tvTimer = 0.08;
                tv.paint(r);
            }

            lines.forEach(({ line, watts, scale }) => {
                const value = watts(r);
                line.setFlow(Math.sign(value) * Math.min(1, Math.sqrt(Math.abs(value) / scale)));
                line.update(dt, elapsed);
            });

            // The shafts follow the sun, and turn orange when it is low.
            travel.set(-r.sun.x, -r.sun.y, -r.sun.z);
            rays.set(travel, r.beam, tint.lerpColors(LOW_SUN, HIGH_SUN, smoothstep(0.05, 0.4, r.sun.y)));
            rays.update(elapsed);
            panels.update(elapsed, r.daylight * (1 - 0.6 * r.cloud));
            lamp.update(dt, r);
        },
    };
};
