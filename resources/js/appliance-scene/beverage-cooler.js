import * as THREE from 'three';
import { box, canvasPlane, cylinder, drift, hingedDoor, openBox, openingCycle, room } from './parts.js';

/**
 * Beverage cooler of a shop (ADR-0019): glass doors, shelves full of bottles and cans, light inside
 * and a lit sign on top. Now and then a door opens, as when a customer takes a drink.
 */

const DOORS = {
    one_door: { width: 0.62, height: 1.95, depth: 0.62, doors: 1 },
    two_doors: { width: 1.12, height: 1.98, depth: 0.68, doors: 2 },
};

const KICK = 0.1;
const SIGN = 0.2;
const DRINKS = ['#c8102e', '#f28c1e', '#2f9e44', '#1c7ed6', '#e7e7e2', '#7b2d8b', '#f2c94c'];

export const frame = { target: new THREE.Vector3(0.05, 1.02, 0.45), radius: 1.45 };

const doorsOf = (variant) => (DOORS[variant] ? variant : 'one_door');

export const note = (variant) => (DOORS[doorsOf(variant)].doors === 2
    ? 'Dos puertas: el doble de espacio y casi el doble de consumo. El vidrio y la luz trabajan todo el día.'
    : 'Puerta de vidrio y luz encendida todo el día: por eso gasta más que una nevera de casa.');

/** A row of drinks on a shelf: bottles and cans, sharing geometry and colors. */
const drinksRow = (width, y, depth, shared, seed) => {
    const group = new THREE.Group();
    const columns = Math.floor((width - 0.04) / 0.075);
    for (let row = 0; row < 2; row++) {
        for (let column = 0; column < columns; column++) {
            const index = seed + column + row * 3;
            const material = shared.materials[index % shared.materials.length];
            const x = -width / 2 + 0.05 + column * 0.075;
            const z = depth * 0.25 - row * 0.12;
            if ((seed + column) % 4 === 3) {
                const can = new THREE.Mesh(shared.can, material);
                can.position.set(x, y + 0.06, z);
                group.add(can);
            } else {
                const bottle = new THREE.Mesh(shared.bottle, material);
                bottle.position.set(x, y + 0.1, z);
                const neck = new THREE.Mesh(shared.neck, material);
                neck.position.set(x, y + 0.225, z);
                group.add(bottle, neck);
            }
        }
    }

    return group;
};

export const build = (m, variant) => {
    const { width, height, depth, doors } = DOORS[doorsOf(variant)];
    const group = room(m, { height: 2.3, width: 2.4 });
    const cooler = new THREE.Group();
    cooler.position.z = 0.03;
    group.add(cooler);

    const lining = new THREE.MeshStandardMaterial({ color: '#f3f6f8', roughness: 0.5, emissive: new THREE.Color('#eaf6ff'), emissiveIntensity: 0.45 });
    const body = height - KICK - SIGN;
    const cabinet = openBox(m.plastic, lining, width, body, depth);
    cabinet.position.set(0, KICK + body / 2, depth / 2);
    cooler.add(cabinet, box(width - 0.02, KICK, depth - 0.04, m.dark, 0, KICK / 2, depth / 2));

    // The lit sign on top.
    cooler.add(box(width, SIGN, depth, m.plastic, 0, KICK + body + SIGN / 2, depth / 2));
    const sign = canvasPlane(width - 0.04, SIGN - 0.04, (context, w, h) => {
        context.fillStyle = '#c8102e';
        context.fillRect(0, 0, w, h);
        context.fillStyle = '#ffffff';
        // As large as the sign allows, in its height and in its width.
        context.font = 'bold 100px system-ui, sans-serif';
        const fit = Math.min(h * 0.55, (w * 0.86 * 100) / context.measureText('BEBIDAS FRÍAS').width);
        context.font = `bold ${Math.round(fit)}px system-ui, sans-serif`;
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.fillText('BEBIDAS FRÍAS', w / 2, h / 2 + 2);
    });
    sign.plane.position.set(0, KICK + body + SIGN / 2, depth + 0.002);
    cooler.add(sign.plane);

    // Shelves full of drinks, and light strips at the sides.
    const shared = {
        bottle: new THREE.CylinderGeometry(0.03, 0.032, 0.2, 12),
        neck: new THREE.CylinderGeometry(0.011, 0.016, 0.05, 10),
        can: new THREE.CylinderGeometry(0.031, 0.031, 0.12, 12),
        materials: DRINKS.map((color) => new THREE.MeshStandardMaterial({ color, roughness: 0.35, metalness: 0.1 })),
    };
    const inner = width - 0.08;
    const shelves = 5;
    for (let index = 0; index < shelves; index++) {
        const y = KICK + 0.05 + index * ((body - 0.1) / shelves);
        cooler.add(box(inner, 0.012, depth - 0.1, m.metal, 0, y, depth / 2));
        cooler.add(drinksRow(inner, y + 0.006, depth, shared, index * 2).translateZ(depth / 2));
    }
    [-1, 1].forEach((side) => cooler.add(box(0.012, body - 0.1, 0.012, new THREE.MeshBasicMaterial({ color: '#f4fbff' }), side * (inner / 2 - 0.01), KICK + body / 2, depth - 0.06)));

    // Glass doors in a dark frame; the right one opens now and then.
    const doorWidth = width / doors;
    const glassDoors = Array.from({ length: doors }, (_, index) => {
        const side = doors === 1 ? -1 : (index === 0 ? -1 : 1);
        const door = hingedDoor(m, { width: doorWidth, height: body, thickness: 0.04, finish: m.dark, side, glass: m.glass });
        door.hinge.position.set(side * width / 2, KICK + body / 2, depth);
        cooler.add(door.hinge);

        return door;
    });
    const moving = glassDoors[glassDoors.length - 1];

    const spill = drift({
        count: 20, length: 0.5, speed: 0.45, size: 0.013, color: '#dff3ff', opacity: 0.5,
        emit: ([across, rise, spread]) => ({
            origin: new THREE.Vector3(width / 2 - doorWidth / 2 + (across - 0.5) * doorWidth * 0.8, KICK + 0.1 + rise * body * 0.6, depth + 0.05),
            direction: new THREE.Vector3((spread - 0.5) * 0.4, -1, 0.7).normalize(),
        }),
    });
    cooler.add(spill.group);

    let time = 0;

    return {
        group,
        update(dt, still = false) {
            time += dt;
            const open = still ? 0 : openingCycle(time, { closed: 3.2, swing: 0.8, open: 1.6 });
            moving.swing(open * 1.1);
            spill.update(still ? 0 : dt, open);
        },
    };
};
