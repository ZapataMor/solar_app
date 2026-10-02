import * as THREE from 'three';
import { box, cylinder, DEG, mesh, room, rounded } from './parts.js';

/**
 * Internet router (ADR-0019) on a wall shelf: antennas, blinking lights and Wi-Fi waves. It is on all
 * day, but it uses very little.
 */

const SHELF_Y = 1.0;

export const frame = { target: new THREE.Vector3(0, SHELF_Y + 0.1, 0.22), radius: 0.34 };

export const note = () => 'Encendido todo el día, pero gasta poco: unos 10 W, como un bombillo LED.';

export const build = (m) => {
    const group = room(m, { height: 1.6, width: 1.4, depth: 0.8, x: 0 });

    // The shelf, on two brackets.
    group.add(box(0.46, 0.022, 0.24, m.wood, 0, SHELF_Y - 0.011, 0.12));
    [-0.16, 0.16].forEach((x) => group.add(box(0.02, 0.1, 0.16, m.metal, x, SHELF_Y - 0.072, 0.08)));

    const router = new THREE.Group();
    router.position.set(0, SHELF_Y, 0.13);
    router.add(rounded(0.22, 0.035, 0.15, 0.01, m.dark, 0, 0.0175, 0, 2));
    [-1, 1].forEach((side) => router.add(box(0.03, 0.006, 0.12, m.dark, side * 0.08, -0.003, 0)));

    // Antennas, a little open.
    [-0.085, -0.03, 0.03, 0.085].forEach((x, index) => {
        const antenna = new THREE.Group();
        antenna.position.set(x, 0.03, -0.07);
        antenna.rotation.z = (index - 1.5) * 9 * DEG;
        antenna.add(cylinder(0.006, 0.007, 0.16, m.dark, 0, 0.08, 0, 10));
        router.add(antenna);
    });

    // Lights on the front: power, internet, Wi-Fi and network.
    const lights = Array.from({ length: 4 }, (_, index) => {
        const material = new THREE.MeshBasicMaterial({ color: '#4ade80' });
        const light = mesh(new THREE.SphereGeometry(0.0035, 8, 6), material, { cast: false, receive: false });
        light.position.set(-0.06 + index * 0.04, 0.018, 0.076);
        router.add(light);

        return material;
    });

    // Wi-Fi waves: arcs that open upward and fade.
    const waves = Array.from({ length: 3 }, () => {
        const material = new THREE.MeshBasicMaterial({ color: '#7cc4ff', transparent: true, opacity: 0, side: THREE.DoubleSide, depthWrite: false });
        const arc = mesh(new THREE.TorusGeometry(1, 0.035, 6, 32, Math.PI / 2), material, { cast: false, receive: false });
        arc.rotation.z = Math.PI / 4;
        arc.position.set(0, 0.07, 0.02);
        router.add(arc);

        return { arc, material };
    });
    group.add(router);

    // The cable down the wall.
    const cable = new THREE.CatmullRomCurve3([
        new THREE.Vector3(0.08, SHELF_Y + 0.02, 0.05),
        new THREE.Vector3(0.12, SHELF_Y - 0.02, 0.02),
        new THREE.Vector3(0.13, SHELF_Y - 0.4, 0.012),
        new THREE.Vector3(0.14, 0.3, 0.012),
    ]);
    group.add(mesh(new THREE.TubeGeometry(cable, 30, 0.003, 6, false), m.dark));

    let time = 0;

    return {
        group,
        update(dt, still = false) {
            time += dt;
            waves.forEach(({ arc, material }, index) => {
                const progress = still ? 0.3 + index * 0.25 : ((time * 0.6 + index / 3) % 1);
                arc.scale.setScalar(0.05 + progress * 0.13);
                material.opacity = 0.8 * (1 - progress);
            });
            // Power steady; the others flicker with the traffic.
            lights.forEach((material, index) => {
                const on = index === 0 || still || Math.sin(time * (7 + index * 3.1) + index) > -0.2;
                material.color.set(on ? '#4ade80' : '#1f5130');
            });
        },
    };
};
