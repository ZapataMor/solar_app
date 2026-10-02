import * as THREE from 'three';
import { box, DEG, drift, mesh, room } from './parts.js';

/**
 * Iron (ADR-0019) on its ironing board with a shirt: it goes back and forth, its light on while it
 * heats and steam puffing from the front. It draws a lot while it is on.
 */

const BOARD_Y = 0.86;

export const frame = { target: new THREE.Vector3(0.02, 0.93, 0.5), radius: 0.36, polar: 70 * DEG };

export const note = () => 'Calienta su suela con 1.100 W: mientras está encendida gasta como un microondas.';

/** Flat outline pointed at the front (+x), extruded upward: the board and the soleplate share it. */
const pointed = (length, width, nose, height, material) => {
    const shape = new THREE.Shape();
    shape.moveTo(-length / 2, -width / 2);
    shape.lineTo(length / 2 - nose, -width / 2);
    shape.quadraticCurveTo(length / 2, -width * 0.2, length / 2, 0);
    shape.quadraticCurveTo(length / 2, width * 0.2, length / 2 - nose, width / 2);
    shape.lineTo(-length / 2, width / 2);
    shape.closePath();
    const geometry = new THREE.ExtrudeGeometry(shape, { depth: height, bevelEnabled: false, curveSegments: 16 });
    // Lying flat: the outline in x–z, the thickness up.
    geometry.rotateX(Math.PI / 2);
    geometry.translate(0, height, 0);

    return mesh(geometry, material);
};

export const build = (m) => {
    const group = room(m, { height: 1.6 });

    // The board on crossed legs.
    const board = new THREE.Group();
    board.position.set(0, BOARD_Y, 0.5);
    board.add(pointed(1.15, 0.36, 0.3, 0.025, new THREE.MeshStandardMaterial({ color: '#6c8fb8', roughness: 0.9 })));
    [-1, 1].forEach((side) => {
        const leg = box(0.025, 1.0, 0.025, m.metal, 0, -BOARD_Y / 2, 0);
        leg.rotation.z = side * 24 * DEG;
        leg.position.x = -0.15;
        board.add(leg);
    });
    board.add(box(0.2, 0.02, 0.2, m.metal, -0.55, -0.01, 0));

    // A shirt on the board.
    const shirt = new THREE.MeshStandardMaterial({ color: '#f4f1ea', roughness: 0.95, side: THREE.DoubleSide });
    const body = box(0.42, 0.006, 0.3, shirt, 0.05, 0.028, 0);
    const collar = box(0.08, 0.012, 0.16, shirt, -0.2, 0.031, 0);
    const sleeve = box(0.18, 0.006, 0.1, shirt, 0.02, 0.028, 0.19);
    sleeve.rotation.y = 0.5;
    board.add(body, collar, sleeve);
    [0.2, 0.08, -0.04].forEach((x) => board.add(box(0.012, 0.008, 0.012, new THREE.MeshStandardMaterial({ color: '#9aa3ad' }), x, 0.033, 0)));
    group.add(board);

    // The iron: soleplate, body, handle, water tank and its light.
    const iron = new THREE.Group();
    iron.position.set(0, BOARD_Y + 0.031, 0.5);
    iron.add(pointed(0.24, 0.12, 0.08, 0.012, m.steel));
    const plastic = new THREE.MeshStandardMaterial({ color: '#2f6fb3', roughness: 0.45 });
    const shell = pointed(0.22, 0.1, 0.07, 0.05, plastic);
    shell.position.y = 0.012;
    iron.add(shell);
    iron.add(box(0.13, 0.025, 0.035, plastic, -0.01, 0.11, 0));
    [-0.065, 0.05].forEach((x) => iron.add(box(0.025, 0.05, 0.03, plastic, x, 0.085, 0)));
    iron.add(box(0.07, 0.04, 0.07, new THREE.MeshStandardMaterial({ color: '#9fd4ff', roughness: 0.1, transparent: true, opacity: 0.5 }), 0.02, 0.075, 0));
    const lightMaterial = new THREE.MeshBasicMaterial({ color: '#ff7a3d' });
    const lamp = mesh(new THREE.SphereGeometry(0.006, 8, 6), lightMaterial, { cast: false });
    lamp.position.set(-0.08, 0.06, 0.052);
    iron.add(lamp);

    // Steam from the front of the soleplate.
    const steam = drift({
        count: 22, length: 0.18, speed: 0.6, size: 0.008, color: '#ffffff', opacity: 0.65,
        emit: ([across, ahead, spread]) => ({
            origin: new THREE.Vector3(0.06 + ahead * 0.05, 0.01, (across - 0.5) * 0.08),
            direction: new THREE.Vector3(0.3 + spread * 0.4, 1, (across - 0.5) * 0.6).normalize(),
        }),
    });
    iron.add(steam.group);
    group.add(iron);

    // The cord, out of the back of the iron.
    const cord = new THREE.CatmullRomCurve3([
        new THREE.Vector3(-0.12, 0.05, 0),
        new THREE.Vector3(-0.2, 0.12, 0.02),
        new THREE.Vector3(-0.3, 0.1, 0.05),
    ]);
    iron.add(mesh(new THREE.TubeGeometry(cord, 20, 0.004, 6, false), m.dark));

    let time = 0;

    return {
        group,
        update(dt, still = false) {
            time += dt;
            if (!still) {
                iron.position.x = Math.sin(time * 1.1) * 0.17;
                iron.position.z = 0.5 + Math.sin(time * 0.55) * 0.04;
            }
            // The light goes off when the plate is hot and back on when it heats again.
            lightMaterial.color.set(still || time % 5 < 3.2 ? '#ff7a3d' : '#5a2a17');
            steam.update(still ? 0 : dt, 0.85);
        },
    };
};
