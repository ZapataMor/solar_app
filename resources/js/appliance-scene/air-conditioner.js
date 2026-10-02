import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';

/**
 * Split air conditioner (ADR-0019): the indoor unit high on the wall and the condenser on the patio,
 * joined by the insulated pipes. Capacity sets the size of both units (approximate real sizes per BTU);
 * technology sets how it works: a conventional compressor runs at full power and stops, again and
 * again, while an inverter adjusts its speed and keeps a steady, gentler flow. In meters; the wall's
 * face is z = 0 and the room is +z.
 */

const DEG = Math.PI / 180;

/** Width, height and depth of the indoor and outdoor units, and the radius of the condenser fan. */
const SIZES = {
    9000: { indoor: [0.78, 0.27, 0.2], outdoor: [0.7, 0.5, 0.26], fan: 0.17 },
    12000: { indoor: [0.84, 0.28, 0.21], outdoor: [0.76, 0.54, 0.28], fan: 0.19 },
    18000: { indoor: [0.97, 0.3, 0.22], outdoor: [0.84, 0.6, 0.3], fan: 0.21 },
    24000: { indoor: [1.08, 0.32, 0.23], outdoor: [0.9, 0.7, 0.32], fan: 0.24 },
};

const INDOOR_X = -0.35;
const INDOOR_TOP = 1.42;
const OUTDOOR_X = 0.62;
const OUTDOOR_Z = 0.42;
/** Conventional: seconds running and seconds stopped in each cycle (shortened to be seen). */
const RUN_SECONDS = 5.5;
const REST_SECONDS = 3.5;
const PARTICLES = 46;

/** Same for every capacity, so a bigger one looks bigger. */
export const frame = { target: new THREE.Vector3(0.12, 0.8, 0.32), radius: 1.16 };

const parse = (variant) => {
    const [size, tech] = String(variant ?? '').split('.');

    return { size: SIZES[size] ? size : '12000', inverter: tech === 'inverter' };
};

export const note = (variant) => (parse(variant).inverter
    ? 'Inverter: el compresor ajusta su velocidad y casi nunca se apaga, por eso gasta menos.'
    : 'Convencional: el compresor arranca a toda potencia y se apaga, una y otra vez.');

const mesh = (geometry, material, { cast = true, receive = true } = {}) => {
    const object = new THREE.Mesh(geometry, material);
    object.castShadow = cast;
    object.receiveShadow = receive;

    return object;
};

const box = (width, height, depth, material, x = 0, y = 0, z = 0) => {
    const object = mesh(new THREE.BoxGeometry(width, height, depth), material);
    object.position.set(x, y, z);

    return object;
};

/** A flat label drawn on a canvas, as a plane facing +z; `pill` rounds its background. */
const label = (text, { width, height, color, background = null, pill = false, font = 'bold 40px system-ui, sans-serif' }) => {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = Math.round((256 * height) / width);
    const context = canvas.getContext('2d');
    if (background) {
        context.fillStyle = background;
        context.beginPath();
        context.roundRect(0, 0, canvas.width, canvas.height, pill ? canvas.height / 2 : 0);
        context.fill();
    }
    context.fillStyle = color;
    context.font = font;
    context.textAlign = 'center';
    context.textBaseline = 'middle';
    context.fillText(text, canvas.width / 2, canvas.height / 2 + 2);
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.anisotropy = 4;

    return mesh(
        new THREE.PlaneGeometry(width, height),
        new THREE.MeshBasicMaterial({ map: texture, transparent: background === null || pill, toneMapped: false }),
        { cast: false, receive: false },
    );
};

const indoorUnit = (m, [width, height, depth], inverter) => {
    const unit = new THREE.Group();
    unit.add(mesh(new RoundedBoxGeometry(width, height, depth, 3, 0.04), m.plastic));

    // The air comes out under the front: a dark slot and the flap that sends it down.
    unit.add(box(width * 0.86, 0.035, 0.02, m.dark, 0, -height / 2 + 0.05, depth / 2 - 0.005));
    const flap = box(width * 0.84, 0.008, 0.06, m.plastic, 0, -height / 2 + 0.03, depth / 2 + 0.01);
    flap.rotation.x = 28 * DEG;
    unit.add(flap);
    unit.add(box(width * 0.95, 0.004, 0.004, m.trim, 0, height * 0.12, depth / 2 + 0.001));

    const display = label('24°', { width: 0.075, height: 0.034, color: '#7fe1ff', background: '#0f1c27' });
    display.position.set(width * 0.33, height * 0.02, depth / 2 + 0.002);
    unit.add(display);

    const ledMaterial = new THREE.MeshBasicMaterial({ color: '#4ade80' });
    const led = mesh(new THREE.SphereGeometry(0.007, 8, 6), ledMaterial, { cast: false, receive: false });
    led.position.set(width * 0.33 - 0.055, height * 0.02, depth / 2 + 0.003);
    unit.add(led);

    if (inverter) {
        const badge = label('INVERTER', { width: 0.2, height: 0.042, color: '#ffffff', background: '#16958a', pill: true, font: 'bold 34px system-ui, sans-serif' });
        badge.position.set(-width * 0.3, height * 0.02, depth / 2 + 0.002);
        unit.add(badge);
    }

    return { unit, ledMaterial };
};

const outdoorUnit = (m, [width, height, depth], fanRadius, inverter) => {
    const unit = new THREE.Group();
    unit.add(mesh(new RoundedBoxGeometry(width, height, depth, 2, 0.025), m.casing));

    // The fan, behind a round grille, on the left of the front; the service panel on the right.
    const fanX = -width * 0.17;
    const fanZ = depth / 2;
    unit.add(mesh(new THREE.CircleGeometry(fanRadius * 1.06, 32), m.dark, { cast: false }).translateX(fanX).translateZ(fanZ + 0.002));
    const blades = new THREE.Group();
    blades.position.set(fanX, 0, fanZ + 0.012);
    for (let index = 0; index < 3; index++) {
        const holder = new THREE.Group();
        holder.rotation.z = (index * 2 * Math.PI) / 3;
        const blade = box(fanRadius * 0.85, fanRadius * 0.38, 0.006, m.metal, fanRadius * 0.5, 0, 0);
        blade.rotation.x = 22 * DEG;
        holder.add(blade);
        blades.add(holder);
    }
    blades.add(mesh(new THREE.CylinderGeometry(0.03, 0.03, 0.03, 12), m.metal).rotateX(Math.PI / 2));
    unit.add(blades);

    [0.4, 0.7, 0.98].forEach((share) => {
        const ring = mesh(new THREE.TorusGeometry(fanRadius * share, 0.004, 6, 40), m.metal, { cast: false });
        ring.position.set(fanX, 0, fanZ + 0.03);
        unit.add(ring);
    });
    unit.add(
        box(fanRadius * 2, 0.006, 0.006, m.metal, fanX, 0, fanZ + 0.03),
        box(0.006, fanRadius * 2, 0.006, m.metal, fanX, 0, fanZ + 0.03),
    );

    const panelX = width * 0.33;
    for (let index = 0; index < 6; index++) {
        unit.add(box(width * 0.22, 0.008, 0.004, m.trim, panelX, height * 0.28 - index * height * 0.1, fanZ + 0.002));
    }
    if (inverter) {
        const badge = label('INVERTER', { width: 0.18, height: 0.038, color: '#ffffff', background: '#16958a', pill: true, font: 'bold 34px system-ui, sans-serif' });
        badge.position.set(panelX, -height * 0.34, fanZ + 0.003);
        unit.add(badge);
    }

    // Feet on a small concrete pad.
    [-1, 1].forEach((side) => unit.add(box(0.05, 0.05, depth * 0.95, m.dark, side * width * 0.36, -height / 2 - 0.025, 0)));

    return { unit, blades };
};

/** Cold air leaving the flap: specks that drift forward and down, as strong as the flow. */
const coldAir = (start, width) => {
    const group = new THREE.Group();
    const material = new THREE.MeshBasicMaterial({ color: '#8fd3ff', transparent: true, opacity: 0.75, depthWrite: false });
    const geometry = new THREE.SphereGeometry(0.015, 6, 4);
    const direction = new THREE.Vector3(0, -0.55, 1).normalize();
    const specks = Array.from({ length: PARTICLES }, (_, index) => {
        const object = mesh(geometry, material, { cast: false, receive: false });
        group.add(object);

        return { object, across: ((index * 0.618) % 1) - 0.5, life: (index * 0.37) % 1, drift: ((index * 0.29) % 1) - 0.5 };
    });

    return {
        group,
        update(dt, flow) {
            specks.forEach((speck) => {
                speck.life = (speck.life + dt * (0.25 + 0.55 * flow)) % 1;
                const travel = speck.life * 0.75;
                speck.object.position.set(
                    start.x + speck.across * width * 0.8 + speck.drift * travel * 0.25,
                    start.y + direction.y * travel,
                    start.z + direction.z * travel,
                );
                speck.object.scale.setScalar(Math.max(0.001, Math.sin(speck.life * Math.PI) * flow));
            });
        },
    };
};

export const build = (m, variant) => {
    const { size, inverter } = parse(variant);
    const { indoor, outdoor, fan } = SIZES[size];
    const group = new THREE.Group();

    // A piece of the room: wall with its baseboard, and the floor of the patio.
    group.add(box(2.3, 1.55, 0.1, m.wall, 0.05, 0.775, -0.05));
    group.add(box(2.3, 0.08, 0.02, m.trim, 0.05, 0.04, 0.01));
    group.add(box(2.3, 0.05, 1.25, m.floor, 0.05, -0.025, 0.625));

    const { unit: inside, ledMaterial } = indoorUnit(m, indoor, inverter);
    const indoorY = INDOOR_TOP - indoor[1] / 2;
    inside.position.set(INDOOR_X, indoorY, indoor[2] / 2 + 0.01);
    group.add(inside);

    const { unit: outside, blades } = outdoorUnit(m, outdoor, fan, inverter);
    const padHeight = 0.05;
    outside.position.set(OUTDOOR_X, padHeight + 0.05 + outdoor[1] / 2, OUTDOOR_Z);
    group.add(outside, box(outdoor[0] + 0.2, padHeight, outdoor[2] + 0.22, m.pad, OUTDOOR_X, padHeight / 2, OUTDOOR_Z));

    // The insulated pipes: out of the indoor unit, down the wall and into the back of the condenser.
    const right = INDOOR_X + indoor[0] / 2;
    const pipe = new THREE.CatmullRomCurve3([
        new THREE.Vector3(right - 0.04, indoorY - 0.02, 0.07),
        new THREE.Vector3(right + 0.07, indoorY - 0.07, 0.045),
        new THREE.Vector3(right + 0.09, 0.5, 0.045),
        new THREE.Vector3(right + 0.12, 0.36, 0.06),
        new THREE.Vector3(OUTDOOR_X - outdoor[0] * 0.2, 0.34, OUTDOOR_Z - outdoor[2] / 2 - 0.01),
    ]);
    group.add(mesh(new THREE.TubeGeometry(pipe, 48, 0.022, 10, false), m.insulation));

    const air = coldAir(new THREE.Vector3(INDOOR_X, indoorY - indoor[1] / 2 + 0.02, indoor[2] + 0.05), indoor[0]);
    group.add(air.group);

    let time = 0;
    let flow = inverter ? 0.65 : 1;
    let fanSpeed = 0;

    return {
        group,
        update(dt, still = false) {
            time += dt;

            // How hard it works now: steady with inverter; on and off with a conventional compressor.
            let target = 0.65;
            if (!inverter) {
                const moment = time % (RUN_SECONDS + REST_SECONDS);
                target = still || moment < RUN_SECONDS ? 1 : 0;
            }
            flow += (target - flow) * Math.min(1, dt * (target > flow ? 3 : 1.6));
            if (still) {
                flow = target;
            }

            fanSpeed = (inverter ? 7 : 13) * flow;
            blades.rotation.z -= fanSpeed * dt;
            air.update(still ? 0 : dt, flow);
            air.group.visible = flow > 0.02;
            ledMaterial.color.set(flow > 0.1 ? '#4ade80' : '#3a4a3f');
        },
    };
};
