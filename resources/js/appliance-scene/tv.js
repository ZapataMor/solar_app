import * as THREE from 'three';
import { box, canvasPlane, cylinder, room, rounded } from './parts.js';

/**
 * Television (ADR-0019) on its stand, which keeps one size so the screens compare: the inches set the
 * real size of the 16:9 screen, and a bigger screen has more to light. It shows a Guajira landscape.
 */

const INCHES = ['32', '43', '55', '65'];
const STAND = { width: 1.6, height: 0.48, depth: 0.42 };

export const frame = { target: new THREE.Vector3(0.05, 0.86, 0.4), radius: 1.18 };

const inchesOf = (variant) => (INCHES.includes(String(variant)) ? String(variant) : '43');

export const note = (variant) => ({
    32: 'Pantalla pequeña: la que menos gasta.',
    43: 'Mediana: gasta un poco más de una vez y media lo de una de 32".',
    55: 'Grande: más pantalla que iluminar, más del doble que una de 32".',
    65: 'Muy grande: gasta unas tres veces lo de una de 32".',
}[inchesOf(variant)]);

/** The show: sky, the sun crossing, drifting clouds and dunes with a cardón. */
const landscape = (context, w, h, time) => {
    const sky = context.createLinearGradient(0, 0, 0, h * 0.7);
    sky.addColorStop(0, '#2f7fd1');
    sky.addColorStop(1, '#bfe3f7');
    context.fillStyle = sky;
    context.fillRect(0, 0, w, h);

    const sunX = ((time * 0.04) % 1.2 - 0.1) * w;
    context.fillStyle = '#ffd34d';
    context.beginPath();
    context.arc(sunX, h * 0.24, h * 0.09, 0, Math.PI * 2);
    context.fill();

    context.fillStyle = 'rgba(255, 255, 255, 0.9)';
    [0, 0.45, 0.8].forEach((offset, index) => {
        const x = (((time * 0.025 + offset) % 1.3) - 0.15) * w;
        const y = h * (0.16 + index * 0.08);
        context.beginPath();
        context.ellipse(x, y, w * 0.07, h * 0.035, 0, 0, Math.PI * 2);
        context.ellipse(x + w * 0.05, y - h * 0.02, w * 0.05, h * 0.03, 0, 0, Math.PI * 2);
        context.fill();
    });

    context.fillStyle = '#e8c27f';
    context.beginPath();
    context.moveTo(0, h * 0.72);
    context.quadraticCurveTo(w * 0.3, h * 0.6, w * 0.55, h * 0.7);
    context.quadraticCurveTo(w * 0.8, h * 0.78, w, h * 0.66);
    context.lineTo(w, h);
    context.lineTo(0, h);
    context.fill();
    context.fillStyle = '#d4a85c';
    context.fillRect(0, h * 0.86, w, h * 0.14);

    context.fillStyle = '#4f7a40';
    const cactusX = w * 0.72;
    context.fillRect(cactusX, h * 0.42, w * 0.025, h * 0.34);
    context.fillRect(cactusX - w * 0.03, h * 0.5, w * 0.03, h * 0.025);
    context.fillRect(cactusX - w * 0.03, h * 0.44, w * 0.012, h * 0.08);
};

export const build = (m, variant) => {
    const inches = Number(inchesOf(variant));
    const diagonal = inches * 0.0254;
    const width = diagonal * 0.8716;
    const height = diagonal * 0.4903;
    const group = room(m, { height: 1.9 });

    // The stand, with two drawers and a set-top box.
    const stand = new THREE.Group();
    stand.position.z = STAND.depth / 2 + 0.03;
    stand.add(box(STAND.width, STAND.height - 0.06, STAND.depth, m.wood, 0, STAND.height / 2 + 0.03, 0));
    [-1, 1].forEach((side) => {
        stand.add(box(0.06, 0.06, STAND.depth - 0.06, m.dark, side * (STAND.width / 2 - 0.08), 0.03, 0));
        stand.add(box(STAND.width / 2 - 0.08, 0.004, 0.004, m.trim, side * STAND.width / 4, STAND.height * 0.5, STAND.depth / 2 + 0.002));
        stand.add(box(0.12, 0.014, 0.014, m.metal, side * STAND.width / 4, STAND.height * 0.68, STAND.depth / 2 + 0.008));
    });
    stand.add(box(0.26, 0.05, 0.18, m.dark, STAND.width / 2 - 0.25, STAND.height + 0.025, 0.02));
    group.add(stand);

    // The TV on its foot, centered on the stand.
    const tv = new THREE.Group();
    tv.position.set(0, STAND.height, stand.position.z - 0.05);
    tv.add(box(0.3, 0.012, 0.18, m.dark, 0, 0.006, 0));
    tv.add(box(0.06, 0.08, 0.03, m.dark, 0, 0.05, -0.02));
    const screenY = 0.08 + height / 2 + 0.02;
    tv.add(rounded(width + 0.02, height + 0.02, 0.05, 0.008, m.dark, 0, screenY, -0.02, 2));
    const screen = canvasPlane(width, height, landscape, { resolution: 320 });
    screen.plane.position.set(0, screenY, 0.006);
    tv.add(screen.plane);
    // The power light under the screen.
    tv.add(cylinder(0.004, 0.004, 0.004, new THREE.MeshBasicMaterial({ color: '#ff5a4f' }), width * 0.4, screenY - height / 2 - 0.004, 0.008, 8));
    group.add(tv);

    // A plant beside the stand, for scale.
    const plant = new THREE.Group();
    plant.position.set(-STAND.width / 2 - 0.25, 0, 0.3);
    plant.add(cylinder(0.11, 0.08, 0.3, m.pad, 0, 0.15, 0));
    const leaves = new THREE.MeshStandardMaterial({ color: '#5c8a4b', roughness: 0.8, flatShading: true });
    [[0, 0.5, 0, 0.16], [0.08, 0.62, 0.03, 0.12], [-0.07, 0.68, -0.02, 0.11]].forEach(([x, y, z, r]) => {
        const leaf = new THREE.Mesh(new THREE.IcosahedronGeometry(r, 0), leaves);
        leaf.position.set(x, y, z);
        leaf.castShadow = true;
        plant.add(leaf);
    });
    group.add(plant);

    let time = 3;
    let sinceRepaint = 1;

    return {
        group,
        update(dt, still = false) {
            time += dt;
            sinceRepaint += dt;
            // About 15 pictures per second is enough for drifting clouds.
            if (!still && sinceRepaint > 1 / 15) {
                sinceRepaint = 0;
                screen.repaint(time);
            }
        },
    };
};
