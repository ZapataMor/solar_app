import * as THREE from 'three';
import { box, canvasPlane, cylinder, DEG, mesh, room, rounded, table } from './parts.js';

/**
 * Computer (ADR-0019) on a desk: a laptop is screen and computer in one; a desktop computer is a tower
 * on the floor, a monitor, keyboard and mouse, and uses about three times more. The screen draws a chart.
 */

const DESK = { width: 1.15, depth: 0.6, height: 0.74 };

const typeOf = (variant) => (variant === 'desktop' ? 'desktop' : 'laptop');

/** The laptop up close on the desk; the desktop with its tower on the floor. */
export const frame = (variant) => (typeOf(variant) === 'desktop'
    ? { target: new THREE.Vector3(0.14, 0.7, 0.45), radius: 0.84 }
    : { target: new THREE.Vector3(0.2, 0.86, 0.38), radius: 0.4 });

export const note = (variant) => (typeOf(variant) === 'desktop'
    ? 'De escritorio: torre y monitor por separado; gasta unas tres veces lo de un portátil.'
    : 'Portátil: pantalla y computador en uno, con batería; gasta unos 60 W.');

/** A spreadsheet-like window with a line chart that keeps drawing. */
const desktopScreen = (context, w, h, time) => {
    context.fillStyle = '#eef2f6';
    context.fillRect(0, 0, w, h);
    context.fillStyle = '#2b5f8e';
    context.fillRect(0, 0, w, h * 0.1);
    context.fillStyle = '#ffffff';
    context.fillRect(w * 0.05, h * 0.18, w * 0.9, h * 0.72);
    context.strokeStyle = '#dbe3ea';
    context.lineWidth = 1;
    for (let line = 1; line < 5; line++) {
        context.beginPath();
        context.moveTo(w * 0.05, h * (0.18 + line * 0.144));
        context.lineTo(w * 0.95, h * (0.18 + line * 0.144));
        context.stroke();
    }
    const visible = 0.15 + ((time * 0.12) % 1) * 0.85;
    context.strokeStyle = '#e39a2d';
    context.lineWidth = Math.max(2, w * 0.012);
    context.beginPath();
    for (let step = 0; step <= 40 * visible; step++) {
        const t = step / 40;
        const x = w * (0.07 + t * 0.86);
        const y = h * (0.78 - 0.45 * (0.5 + 0.5 * Math.sin(t * Math.PI)) - 0.06 * Math.sin(t * 17));
        if (step === 0) {
            context.moveTo(x, y);
        } else {
            context.lineTo(x, y);
        }
    }
    context.stroke();
};

const keyboard = (m, width, depth) => {
    const group = new THREE.Group();
    group.add(box(width, 0.012, depth, m.dark));
    const keys = new THREE.MeshStandardMaterial({ color: '#4b525c', roughness: 0.6 });
    const columns = Math.round(width / 0.022);
    const rows = 4;
    const geometry = new THREE.BoxGeometry(0.017, 0.005, 0.016);
    for (let row = 0; row < rows; row++) {
        for (let column = 0; column < columns - 1; column++) {
            const key = new THREE.Mesh(geometry, keys);
            key.position.set(-width / 2 + 0.018 + column * 0.022, 0.008, -depth / 2 + 0.02 + row * 0.022);
            group.add(key);
        }
    }

    return group;
};

export const build = (m, variant) => {
    const type = typeOf(variant);
    const group = room(m, { height: 1.6 });
    const { group: desk, top } = table(m, { ...DESK, x: 0.1, z: DESK.depth / 2 + 0.03 });
    group.add(desk);

    let screen;
    let fanLight = null;
    if (type === 'laptop') {
        const laptop = new THREE.Group();
        laptop.position.set(0.1, top, 0.36);
        laptop.rotation.y = -12 * DEG;
        laptop.add(rounded(0.34, 0.018, 0.235, 0.006, m.steel, 0, 0.009, 0, 2));
        laptop.add(keyboard(m, 0.29, 0.1).translateY(0.018).translateZ(-0.03));
        laptop.add(box(0.1, 0.002, 0.06, m.dark, 0, 0.019, 0.07));
        const lid = new THREE.Group();
        lid.position.set(0, 0.018, -0.115);
        lid.rotation.x = -18 * DEG;
        lid.add(rounded(0.34, 0.23, 0.01, 0.006, m.steel, 0, 0.115, 0, 2));
        screen = canvasPlane(0.31, 0.19, desktopScreen, { resolution: 256 });
        screen.plane.position.set(0, 0.118, 0.0055);
        lid.add(screen.plane);
        laptop.add(lid);
        group.add(laptop);
        // A mug beside it, for scale.
        group.add(cylinder(0.04, 0.035, 0.1, new THREE.MeshStandardMaterial({ color: '#c8102e', roughness: 0.5 }), 0.42, top + 0.05, 0.42));
    } else {
        // Monitor on its foot, keyboard and mouse.
        const monitor = new THREE.Group();
        monitor.position.set(0.1, top, 0.2);
        monitor.add(box(0.22, 0.012, 0.16, m.dark, 0, 0.006, 0));
        monitor.add(box(0.04, 0.2, 0.025, m.dark, 0, 0.11, -0.03));
        monitor.add(rounded(0.56, 0.34, 0.03, 0.008, m.dark, 0, 0.36, -0.01, 2));
        screen = canvasPlane(0.53, 0.31, desktopScreen, { resolution: 320 });
        screen.plane.position.set(0, 0.36, 0.0055);
        monitor.add(screen.plane);
        group.add(monitor);
        group.add(keyboard(m, 0.42, 0.13).translateX(0.06).translateY(top + 0.006).translateZ(0.44));
        group.add(rounded(0.06, 0.025, 0.1, 0.012, m.dark, 0.38, top + 0.0125, 0.45));

        // The tower on the floor, with its fan light.
        const tower = new THREE.Group();
        tower.position.set(0.44, 0, 0.3);
        tower.add(rounded(0.2, 0.44, 0.42, 0.01, m.dark, 0, 0.22, 0, 2));
        tower.add(box(0.008, 0.4, 0.38, new THREE.MeshStandardMaterial({ color: '#30363f', roughness: 0.2, transparent: true, opacity: 0.6 }), 0.101, 0.22, 0));
        const fan = new THREE.MeshBasicMaterial({ color: '#4fc3f7' });
        const ring = mesh(new THREE.TorusGeometry(0.06, 0.007, 8, 30), fan, { cast: false });
        ring.position.set(0, 0.3, 0.214);
        tower.add(ring, cylinder(0.006, 0.006, 0.004, new THREE.MeshBasicMaterial({ color: '#4ade80' }), 0.06, 0.41, 0.211, 10).rotateX(Math.PI / 2));
        group.add(tower);
        fanLight = fan;
    }

    let time = 0;
    let sinceRepaint = 1;

    return {
        group,
        update(dt, still = false) {
            time += dt;
            sinceRepaint += dt;
            if (!still && sinceRepaint > 1 / 12) {
                sinceRepaint = 0;
                screen.repaint(time);
            }
            fanLight?.color.setHSL(0.55 + 0.08 * Math.sin(time * 1.5), 0.85, 0.62);
        },
    };
};
