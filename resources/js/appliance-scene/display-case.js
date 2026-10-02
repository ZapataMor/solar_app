import * as THREE from 'three';
import { box, cylinder, drift, room } from './parts.js';

/**
 * Refrigerated display case of a bakery or a deli (ADR-0019): a low counter with a slanted glass front,
 * trays of food and light inside. The cold leaks through the glass all day, so it hardly rests.
 */

const WIDTH = 1.4;
const BASE = 0.85;
const TOP = 1.28;
const DEPTH = 0.85;
const BACK = 0.12;

export const frame = { target: new THREE.Vector3(0.05, 0.78, 0.55), radius: 1.2 };

export const note = () => 'Vidrio y luz todo el día: el frío se escapa por el vidrio, por eso el compresor casi no descansa.';

/** A metal tray with food: cheese, ham, cakes, sausages. */
const tray = (m, x, y, z, kind) => {
    const group = new THREE.Group();
    group.add(box(0.3, 0.02, 0.22, m.metal, x, y + 0.01, z));
    const food = {
        cheese: () => [0, 1, 2].map((index) => box(0.08, 0.06, 0.12, new THREE.MeshStandardMaterial({ color: '#f2c94c', roughness: 0.6 }), x - 0.09 + index * 0.09, y + 0.05, z)),
        ham: () => [0, 1].map((index) => {
            const roll = cylinder(0.045, 0.045, 0.2, new THREE.MeshStandardMaterial({ color: '#e7a1a8', roughness: 0.5 }), x - 0.06 + index * 0.12, y + 0.065, z, 16);
            roll.rotation.x = Math.PI / 2;

            return roll;
        }),
        cakes: () => [0, 1].map((index) => cylinder(0.065, 0.065, 0.08, new THREE.MeshStandardMaterial({ color: index ? '#7a4a2a' : '#f3e4cf', roughness: 0.7 }), x - 0.07 + index * 0.14, y + 0.06, z, 20)),
        sausages: () => [0, 1, 2, 3].map((index) => {
            const sausage = cylinder(0.018, 0.018, 0.18, new THREE.MeshStandardMaterial({ color: '#b5452d', roughness: 0.5 }), x - 0.09 + index * 0.06, y + 0.04, z, 10);
            sausage.rotation.x = Math.PI / 2;

            return sausage;
        }),
    }[kind]();
    group.add(...food);

    return group;
};

export const build = (m) => {
    const group = room(m, { height: 1.75 });
    const shop = new THREE.Group();
    shop.position.set(0, 0, 0.15);
    group.add(shop);

    // The counter: steel base with a vent at the bottom.
    shop.add(box(WIDTH, BASE - 0.08, DEPTH, m.steel, 0, (BASE + 0.08) / 2, DEPTH / 2));
    shop.add(box(WIDTH - 0.02, 0.08, DEPTH - 0.04, m.dark, 0, 0.04, DEPTH / 2));
    for (let slot = 0; slot < 8; slot++) {
        shop.add(box(0.12, 0.012, 0.004, m.dark, -WIDTH * 0.42 + slot * WIDTH * 0.12, BASE * 0.3, DEPTH + 0.002));
    }

    // The case: a lit deck, a back wall for the seller, a glass top and the slanted glass front.
    const deck = new THREE.MeshStandardMaterial({ color: '#f5f7f8', roughness: 0.4, emissive: new THREE.Color('#fff6e8'), emissiveIntensity: 0.25 });
    shop.add(box(WIDTH, 0.02, DEPTH - BACK, deck, 0, BASE + 0.01, (DEPTH + BACK) / 2));
    shop.add(box(WIDTH, TOP - BASE, BACK, m.steel, 0, (BASE + TOP) / 2, BACK / 2));
    [-1, 1].forEach((side) => shop.add(box(0.03, TOP - BASE, DEPTH - BACK, m.steel, side * (WIDTH / 2 - 0.015), (BASE + TOP) / 2, (DEPTH + BACK) / 2)));
    const topDepth = DEPTH - BACK - 0.3;
    shop.add(box(WIDTH - 0.06, 0.012, topDepth, m.glass, 0, TOP, BACK + topDepth / 2));
    shop.add(box(WIDTH - 0.06, 0.02, 0.03, new THREE.MeshBasicMaterial({ color: '#fffaf0' }), 0, TOP - 0.02, BACK + 0.05));

    const slant = Math.hypot(0.3, TOP - BASE);
    const front = box(WIDTH - 0.06, slant, 0.012, m.glass, 0, (BASE + TOP) / 2, DEPTH - 0.15);
    front.rotation.x = -Math.atan2(0.3, TOP - BASE);
    shop.add(front);

    // Trays on the deck and on a glass shelf behind.
    const kinds = ['cheese', 'ham', 'cakes', 'sausages'];
    kinds.forEach((kind, index) => shop.add(tray(m, -0.48 + index * 0.32, BASE + 0.02, DEPTH * 0.62, kind)));
    shop.add(box(WIDTH - 0.1, 0.01, 0.22, m.glass, 0, BASE + 0.2, BACK + 0.14));
    kinds.slice().reverse().forEach((kind, index) => shop.add(tray(m, -0.48 + index * 0.32, BASE + 0.205, BACK + 0.14, kind)));

    // The cold, drifting slowly over the food behind the glass.
    const cold = drift({
        count: 24, length: 0.25, speed: 0.25, size: 0.011, color: '#dff3ff', opacity: 0.55,
        emit: ([across, along, spread]) => ({
            origin: new THREE.Vector3((across - 0.5) * (WIDTH - 0.2), BASE + 0.12 + spread * 0.12, BACK + 0.1 + along * (DEPTH - BACK - 0.3)),
            direction: new THREE.Vector3((spread - 0.5) * 0.6, -0.3, 1).normalize(),
        }),
    });
    shop.add(cold.group);

    return {
        group,
        update(dt, still = false) {
            cold.update(still ? 0 : dt, 0.8);
        },
    };
};
