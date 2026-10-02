import * as THREE from 'three';
import { approach, box, compressorTarget, cylinder, drift, hingedDoor, inverterBadge, openBox, openingCycle, room } from './parts.js';

/**
 * Fridge (ADR-0019) against the kitchen wall. Size sets its measures, from a small one to a two-door
 * one; its door opens now and then to show the inside. Technology shows in the heat the compressor
 * throws out at the back: in bursts with a conventional compressor, soft and steady with an inverter.
 */

/** Width, height and depth (m); `freezer` is the share of the height of the top freezer. */
const SIZES = {
    small: { width: 0.55, height: 1.25, depth: 0.6, freezer: 0.26, steel: false },
    medium: { width: 0.6, height: 1.62, depth: 0.66, freezer: 0.28, steel: false },
    large: { width: 0.7, height: 1.78, depth: 0.72, freezer: 0.27, steel: true },
    side_by_side: { width: 0.91, height: 1.78, depth: 0.74, split: 0.45, steel: true },
};

const WALL = 0.04;
const KICK = 0.07;

export const frame = { target: new THREE.Vector3(0.05, 0.95, 0.42), radius: 1.3 };

const parse = (variant) => {
    const [size, tech] = String(variant ?? '').split('.');

    return { size: SIZES[size] ? size : 'medium', inverter: tech === 'inverter' };
};

export const note = (variant) => (parse(variant).inverter
    ? 'Inverter: el compresor trabaja suave y sin parar; gasta cerca de un 30 % menos.'
    : 'Convencional: el compresor arranca, enfría a toda potencia y se apaga; por detrás sale su calor.');

/** A fridge door; the top one carries the inverter pill. */
const fridgeDoor = (m, finish, width, height, side, inverter) => {
    const door = hingedDoor(m, { width, height, finish, side });
    if (inverter) {
        const badge = inverterBadge(Math.min(0.17, width * 0.4));
        badge.position.set(side * width * 0.12, height / 2 - 0.08, 0.052);
        door.panel.add(badge);
    }

    return door;
};

/** Food on a shelf: bottles and boxes in a few colors. */
const groceries = (width, y, z, seed) => {
    const group = new THREE.Group();
    const colors = ['#f2f2ee', '#f29b38', '#e0503b', '#5aa0d8', '#7bbf5a', '#f4d35e'];
    let x = -width / 2 + 0.05;
    let index = seed;
    while (x < width / 2 - 0.08) {
        const color = colors[index % colors.length];
        const material = new THREE.MeshStandardMaterial({ color, roughness: 0.5 });
        if (index % 3 === 0) {
            const height = 0.16 + (index % 2) * 0.05;
            group.add(cylinder(0.03, 0.035, height, material, x + 0.035, y + height / 2, z, 12));
            x += 0.09;
        } else {
            const height = 0.08 + (index % 4) * 0.025;
            group.add(box(0.09, height, 0.08, material, x + 0.045, y + height / 2, z));
            x += 0.12;
        }
        index++;
    }

    return group;
};

export const build = (m, variant) => {
    const { size, inverter } = parse(variant);
    const { width, height, depth, freezer, split, steel } = SIZES[size];
    const finish = steel ? m.steel : m.plastic;
    const group = room(m, { height: 2 });
    const fridge = new THREE.Group();
    fridge.position.z = 0.03;
    group.add(fridge);

    // The inside lights up when its door opens.
    const lining = new THREE.MeshStandardMaterial({ color: '#f4f7f8', roughness: 0.6, emissive: new THREE.Color('#e8f4ff'), emissiveIntensity: 0 });
    const body = height - KICK;
    fridge.add(box(width - 0.02, KICK, depth - 0.06, m.dark, 0, KICK / 2, depth / 2 - 0.03));
    for (let slot = 0; slot < 5; slot++) {
        fridge.add(box(width * 0.12, 0.012, 0.004, m.trim, -width * 0.3 + slot * width * 0.15, KICK / 2, depth - 0.059));
    }

    const doors = [];
    let coldBox;
    if (split) {
        // Two doors side by side: the freezer on the left, the fridge on the right.
        const left = width * split;
        const right = width - left;
        const freezerBox = openBox(finish, lining, left, body, depth);
        freezerBox.position.set(-width / 2 + left / 2, KICK + body / 2, depth / 2);
        const fridgeBox = openBox(finish, lining, right, body, depth);
        fridgeBox.position.set(width / 2 - right / 2, KICK + body / 2, depth / 2);
        fridge.add(freezerBox, fridgeBox);
        [0.28, 0.52, 0.76].forEach((share, index) => {
            fridge.add(box(right - WALL * 2, 0.008, depth - 0.1, m.glass, width / 2 - right / 2, KICK + body * share, depth / 2));
            fridge.add(groceries(right - WALL * 2, KICK + body * share + 0.004, depth * 0.45, index * 2).translateX(width / 2 - right / 2));
        });
        const freezerDoor = fridgeDoor(m, finish, left, body, -1, inverter);
        freezerDoor.hinge.position.set(-width / 2, KICK + body / 2, depth);
        const mainDoor = fridgeDoor(m, finish, right, body, 1, false);
        mainDoor.hinge.position.set(width / 2, KICK + body / 2, depth);
        fridge.add(freezerDoor.hinge, mainDoor.hinge);
        // A water and ice dispenser on the freezer door.
        freezerDoor.panel.add(box(left * 0.42, 0.22, 0.012, m.dark, 0, body * 0.12, 0.056));
        doors.push(mainDoor);
        coldBox = { x: width / 2 - right / 2, width: right };
    } else {
        // Freezer on top, fridge below.
        const top = body * freezer;
        const bottom = body - top;
        const freezerBox = openBox(finish, lining, width, top, depth);
        freezerBox.position.set(0, KICK + bottom + top / 2, depth / 2);
        const fridgeBox = openBox(finish, lining, width, bottom, depth);
        fridgeBox.position.set(0, KICK + bottom / 2, depth / 2);
        fridge.add(freezerBox, fridgeBox);
        const shelves = size === 'small' ? [0.35, 0.68] : [0.25, 0.5, 0.75];
        shelves.forEach((share, index) => {
            fridge.add(box(width - WALL * 2, 0.008, depth - 0.1, m.glass, 0, KICK + bottom * share, depth / 2));
            fridge.add(groceries(width - WALL * 2, KICK + bottom * share + 0.004, depth * 0.45, index * 2 + 1));
        });
        const freezerDoor = fridgeDoor(m, finish, width, top, -1, inverter);
        freezerDoor.hinge.position.set(-width / 2, KICK + bottom + top / 2, depth);
        const mainDoor = fridgeDoor(m, finish, width, bottom, -1, false);
        mainDoor.hinge.position.set(-width / 2, KICK + bottom / 2, depth);
        fridge.add(freezerDoor.hinge, mainDoor.hinge);
        doors.push(mainDoor);
        coldBox = { x: 0, width };
    }

    // Cold air that slips out at the bottom of the open door.
    const cold = drift({
        count: 22,
        length: 0.45,
        speed: 0.5,
        size: 0.013,
        color: '#cfeeff',
        opacity: 0.6,
        emit: ([across, rise]) => ({
            origin: new THREE.Vector3(coldBox.x + (across - 0.5) * coldBox.width * 0.8, KICK + 0.08 + rise * 0.15, depth + 0.06),
            direction: new THREE.Vector3((across - 0.5) * 0.4, -0.35, 1).normalize(),
        }),
    });
    fridge.add(cold.group);

    // The compressor's heat, rising behind the fridge.
    const heat = drift({
        count: 18,
        length: 0.5,
        speed: 0.45,
        size: 0.012,
        color: '#ffb36b',
        opacity: 0.65,
        emit: ([across, spread]) => ({
            origin: new THREE.Vector3((across - 0.5) * width * 0.8, height + 0.02, 0.06),
            direction: new THREE.Vector3((spread - 0.5) * 0.3, 1, 0.15).normalize(),
        }),
    });
    fridge.add(heat.group);

    let time = 0;
    let work = inverter ? 0.6 : 1;

    return {
        group,
        update(dt, still = false) {
            time += dt;
            work = still ? compressorTarget(time, inverter, { still }) : approach(work, compressorTarget(time, inverter), dt, 2.5);
            heat.update(still ? 0 : dt, work * 0.9);

            // The door opens now and then; with reduced motion it stays ajar.
            const eased = still ? 0.35 : openingCycle(time);
            doors.forEach(({ swing }) => swing(eased * 1.25));
            lining.emissiveIntensity = 0.55 * Math.min(1, eased * 3);
            cold.update(still ? 0 : dt, eased);
        },
    };
};
