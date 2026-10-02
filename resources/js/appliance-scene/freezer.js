import * as THREE from 'three';
import { box, drift, hingedDoor, mesh, openBox, openingCycle, room, rounded } from './parts.js';

/**
 * Freezer (ADR-0019). A chest freezer opens its lid upward: the cold air is heavy and stays inside,
 * so little is lost. An upright one opens like a fridge and its cold air falls to the floor.
 */

const TYPES = {
    chest_small: { width: 0.75, height: 0.85, depth: 0.58, chest: true },
    chest_large: { width: 1.25, height: 0.85, depth: 0.7, chest: true },
    upright: { width: 0.6, height: 1.45, depth: 0.65, chest: false },
};

const KICK = 0.07;
const FROST = '#eef6fb';

/** Looks a little from above, so the inside of an open chest can be seen. */
export const frame = { target: new THREE.Vector3(0.05, 0.7, 0.42), radius: 1.3, polar: 70 * Math.PI / 180 };

const typeOf = (variant) => (TYPES[variant] ? variant : 'chest_small');

export const note = (variant) => (TYPES[typeOf(variant)].chest
    ? 'Horizontal: al abrirlo, el aire frío se queda adentro porque pesa más; pierde menos frío.'
    : 'Vertical: es cómodo, pero al abrir la puerta el aire frío se cae y el motor trabaja más.');

/** Frozen food: packages and blocks in cold colors. */
const frozenFood = (width, depth, y, count) => {
    const group = new THREE.Group();
    const colors = ['#d9e9f2', '#c7d6e8', '#f0c9b4', '#e9e2cf', '#bfe0d7'];
    for (let index = 0; index < count; index++) {
        const material = new THREE.MeshStandardMaterial({ color: colors[index % colors.length], roughness: 0.7 });
        const w = 0.12 + (index % 3) * 0.03;
        const h = 0.05 + (index % 2) * 0.04;
        group.add(box(w, h, 0.1 + (index % 2) * 0.04, material,
            -width / 2 + 0.1 + ((index * 0.37) % 1) * (width - 0.2), y + h / 2, -depth / 2 + 0.1 + ((index * 0.61) % 1) * (depth - 0.2)));
    }

    return group;
};

const chestFreezer = (m, { width, height, depth }, frost, cold) => {
    const group = new THREE.Group();
    const body = height - KICK - 0.06;
    const tub = openBox(m.plastic, frost, width, depth, body, 0.05);
    // The tub opens upward: its mouth turned from +z to +y.
    tub.rotation.x = -Math.PI / 2;
    tub.position.set(0, KICK + body / 2, depth / 2);
    group.add(tub, box(width - 0.04, KICK, depth - 0.04, m.dark, 0, KICK / 2, depth / 2));
    group.add(frozenFood(width - 0.12, depth - 0.12, KICK + 0.07, Math.round(width * 9)).translateZ(depth / 2));
    // A wire basket hanging at the right.
    group.add(box(width * 0.3, 0.012, depth - 0.14, m.metal, width * 0.3, KICK + body - 0.12, depth / 2));

    // Control knob and light on the front, at the right.
    const knob = mesh(new THREE.CylinderGeometry(0.022, 0.022, 0.02, 14), m.dark);
    knob.rotation.x = Math.PI / 2;
    knob.position.set(width / 2 - 0.1, KICK + body - 0.1, depth + 0.008);
    const light = mesh(new THREE.SphereGeometry(0.008, 8, 6), new THREE.MeshBasicMaterial({ color: '#4ade80' }), { cast: false });
    light.position.set(width / 2 - 0.17, KICK + body - 0.1, depth + 0.004);
    group.add(knob, light);

    // The lid, hinged at the back.
    const hinge = new THREE.Group();
    hinge.position.set(0, KICK + body, 0.02);
    const lid = rounded(width, 0.06, depth, 0.02, m.plastic, 0, 0.03, depth / 2 - 0.01);
    hinge.add(lid, box(0.18, 0.025, 0.03, m.dark, 0, 0.03, depth + 0.004));
    group.add(hinge);

    // The cold rises a little out of the open chest and falls back in.
    const mist = drift({
        count: 26, length: 0.18, speed: 0.4, size: 0.014, color: '#dff3ff', opacity: 0.55,
        emit: ([across, along, spread]) => ({
            origin: new THREE.Vector3((across - 0.5) * (width - 0.15), KICK + body - 0.02, 0.08 + along * (depth - 0.16)),
            direction: new THREE.Vector3((spread - 0.5) * 0.3, 1, 0),
        }),
    });
    group.add(mist.group);

    return { group, open: (amount) => { hinge.rotation.x = -amount * 1.1; }, mist: (dt, amount) => mist.update(dt, amount * cold) };
};

const uprightFreezer = (m, { width, height, depth }, frost, cold) => {
    const group = new THREE.Group();
    const body = height - KICK;
    const cabinet = openBox(m.plastic, frost, width, body, depth);
    cabinet.position.set(0, KICK + body / 2, depth / 2);
    group.add(cabinet, box(width - 0.02, KICK, depth - 0.06, m.dark, 0, KICK / 2, depth / 2 - 0.03));

    // Drawers with their white fronts and a frosty inside.
    const drawers = 4;
    for (let index = 0; index < drawers; index++) {
        const y = KICK + 0.07 + index * ((body - 0.14) / drawers);
        const drawerHeight = (body - 0.14) / drawers - 0.03;
        group.add(box(width - 0.1, drawerHeight, depth - 0.12, new THREE.MeshStandardMaterial({ color: '#cfe3ee', transparent: true, opacity: 0.55, roughness: 0.3 }),
            0, y + drawerHeight / 2, depth / 2 + 0.02));
        group.add(box(width * 0.3, 0.02, 0.02, m.metal, 0, y + drawerHeight - 0.03, depth - 0.03));
    }

    const door = hingedDoor(m, { width, height: body, finish: m.plastic, side: -1 });
    door.hinge.position.set(-width / 2, KICK + body / 2, depth);
    group.add(door.hinge);

    // Open, the cold air pours out and falls.
    const spill = drift({
        count: 28, length: 0.6, speed: 0.45, size: 0.014, color: '#dff3ff', opacity: 0.55,
        emit: ([across, rise, spread]) => ({
            origin: new THREE.Vector3((across - 0.5) * (width - 0.1), KICK + 0.15 + rise * body * 0.75, depth + 0.04),
            direction: new THREE.Vector3((spread - 0.5) * 0.4, -1, 0.7).normalize(),
        }),
    });
    group.add(spill.group);

    return { group, open: (amount) => door.swing(amount * 1.2), mist: (dt, amount) => spill.update(dt, amount * cold) };
};

export const build = (m, variant) => {
    const type = TYPES[typeOf(variant)];
    const group = room(m, { height: 1.7 });
    const frost = new THREE.MeshStandardMaterial({ color: FROST, roughness: 0.9, emissive: new THREE.Color('#dff0ff'), emissiveIntensity: 0 });
    const freezer = (type.chest ? chestFreezer : uprightFreezer)(m, type, frost, 1);
    freezer.group.position.z = 0.04;
    group.add(freezer.group);

    let time = 0;

    return {
        group,
        update(dt, still = false) {
            time += dt;
            const open = still ? 0.4 : openingCycle(time, { closed: 2.2, open: 2.8 });
            freezer.open(open);
            frost.emissiveIntensity = 0.35 * Math.min(1, open * 3);
            freezer.mist(still ? 0 : dt, open);
        },
    };
};
