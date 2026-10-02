import * as THREE from 'three';
import { box, canvasPlane, cylinder, mesh, room, rounded } from './parts.js';

/**
 * Washing machine (ADR-0019), front-loading, in the laundry area. Through the door the drum turns
 * with the clothes and the water at the bottom; now and then it spins fast and the machine shakes.
 * The bigger one has a bigger drum and motor.
 */

const SIZES = {
    up_to_12: { width: 0.6, height: 0.85, depth: 0.6, door: 0.19 },
    over_12: { width: 0.68, height: 0.98, depth: 0.72, door: 0.22 },
};

/** The wash cycle (s): turning one way, the other way, and spinning. */
const TURN = 2.6;
const SPIN = 2.4;

export const frame = { target: new THREE.Vector3(0.02, 0.56, 0.45), radius: 0.74 };

const sizeOf = (variant) => (SIZES[variant] ? variant : 'up_to_12');

export const note = (variant) => (sizeOf(variant) === 'over_12'
    ? 'Más de 12 kg: tambor y motor más grandes; gasta más por lavada, pero lava más ropa de una vez.'
    : 'Hasta 12 kg: casi todo lo que gasta es el motor que mueve el tambor.');

const clothes = (radius) => {
    const group = new THREE.Group();
    const colors = ['#e0503b', '#5aa0d8', '#f4d35e', '#f2f2ee', '#7bbf5a', '#8e5cc4'];
    for (let index = 0; index < 9; index++) {
        const material = new THREE.MeshStandardMaterial({ color: colors[index % colors.length], roughness: 0.9, flatShading: true });
        const piece = new THREE.Mesh(new THREE.IcosahedronGeometry(radius * (0.22 + (index % 3) * 0.05), 0), material);
        const angle = (index / 9) * Math.PI * 2;
        const reach = radius * (0.35 + (index % 2) * 0.25);
        piece.position.set(Math.cos(angle) * reach, Math.sin(angle) * reach, -0.05 - (index % 3) * 0.06);
        piece.scale.set(1.3, 0.8, 1);
        group.add(piece);
    }

    return group;
};

export const build = (m, variant) => {
    const { width, height, depth, door } = SIZES[sizeOf(variant)];
    const group = room(m, { height: 1.5, floor: m.pad });
    const machine = new THREE.Group();
    machine.position.set(0, 0, depth / 2 + 0.04);
    group.add(machine);

    // The body, and a front block with a round tunnel where the drum turns.
    const front = 0.2;
    const doorY = height * 0.45;
    machine.add(rounded(width, height - 0.03, depth - front, 0.025, m.plastic, 0, height / 2 + 0.015, -front / 2, 2));
    const face = new THREE.Shape([
        new THREE.Vector2(-width / 2, 0.03), new THREE.Vector2(width / 2, 0.03),
        new THREE.Vector2(width / 2, height), new THREE.Vector2(-width / 2, height),
    ]);
    face.holes.push(new THREE.Path().absarc(0, doorY, door * 0.98, 0, Math.PI * 2, true));
    const block = mesh(new THREE.ExtrudeGeometry(face, { depth: front, bevelEnabled: false, curveSegments: 40 }), m.plastic);
    block.position.z = depth / 2 - front;
    machine.add(block);
    [-1, 1].forEach((side) => [-1, 1].forEach((front) => machine.add(cylinder(0.02, 0.025, 0.03, m.dark, side * (width / 2 - 0.06), 0.015, front * (depth / 2 - 0.06), 10))));

    // Top panel: drawer, display and knob.
    const panelY = height - 0.08;
    machine.add(box(width * 0.3, 0.07, 0.01, m.casing, -width * 0.3, panelY, depth / 2 + 0.005));
    const display = canvasPlane(0.09, 0.035, (context, w, h, time) => {
        context.fillStyle = '#0f1c27';
        context.fillRect(0, 0, w, h);
        const left = 42 - Math.floor(time / 60) % 42;
        context.fillStyle = '#7fe1ff';
        context.font = `bold ${Math.round(h * 0.7)}px system-ui, sans-serif`;
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.fillText(`0:${String(left).padStart(2, '0')}`, w / 2, h / 2 + 2);
    });
    display.plane.position.set(width * 0.05, panelY, depth / 2 + 0.002);
    const knob = cylinder(0.03, 0.03, 0.02, m.casing, width * 0.3, panelY, depth / 2 + 0.01, 20);
    knob.rotation.x = Math.PI / 2;
    machine.add(display.plane, knob, box(width - 0.04, 0.004, 0.004, m.trim, 0, height - 0.15, depth / 2 + 0.001));

    // The door: a ring, the glass and, behind it, the drum with clothes and some water.
    const ring = mesh(new THREE.TorusGeometry(door, 0.025, 12, 40), m.steel);
    ring.position.set(0, doorY, depth / 2 + 0.02);
    const glass = mesh(new THREE.CircleGeometry(door, 40), new THREE.MeshStandardMaterial({ color: '#b8d9ea', roughness: 0.05, transparent: true, opacity: 0.28, depthWrite: false }), { cast: false });
    glass.position.set(0, doorY, depth / 2 + 0.024);
    machine.add(ring, glass, box(0.04, 0.08, 0.03, m.casing, door + 0.03, doorY, depth / 2 + 0.03));

    const cavity = mesh(new THREE.CircleGeometry(door * 0.98, 40), m.dark, { cast: false });
    cavity.position.set(0, doorY, depth / 2 - front + 0.002);
    const drum = new THREE.Group();
    drum.position.set(0, doorY, depth / 2 - 0.02);
    const shell = mesh(new THREE.CylinderGeometry(door * 0.97, door * 0.97, 0.14, 32, 1, true), m.steel, { cast: false });
    shell.rotation.x = Math.PI / 2;
    shell.position.z = -0.06;
    drum.add(shell, clothes(door));
    for (let paddle = 0; paddle < 3; paddle++) {
        const holder = new THREE.Group();
        holder.rotation.z = (paddle * Math.PI * 2) / 3;
        holder.add(box(0.03, 0.04, 0.12, m.steel, 0, door * 0.88, -0.06));
        drum.add(holder);
    }
    const water = mesh(new THREE.CircleGeometry(door * 0.95, 32, Math.PI * 1.15, Math.PI * 0.7), new THREE.MeshBasicMaterial({ color: '#6fb6e8', transparent: true, opacity: 0.55, depthWrite: false }), { cast: false });
    water.position.set(0, doorY, depth / 2 + 0.005);
    machine.add(cavity, drum, water);

    let time = 0;
    let sinceRepaint = 1;

    return {
        group,
        update(dt, still = false) {
            time += dt;
            sinceRepaint += dt;
            if (still) {
                return;
            }
            const moment = time % (TURN * 2 + SPIN);
            const spinning = moment >= TURN * 2;
            const speed = spinning ? 16 : (moment < TURN ? 2.2 : -2.2);
            drum.rotation.z += speed * dt;
            water.visible = !spinning;
            // It shakes a little while it spins.
            machine.position.x = spinning ? Math.sin(time * 70) * 0.002 : 0;
            if (sinceRepaint > 0.5) {
                sinceRepaint = 0;
                display.repaint(time);
            }
        },
    };
};
