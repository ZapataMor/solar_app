import * as THREE from 'three';
import { box, counter, cylinder, mesh, room, rounded } from './parts.js';

/**
 * Blender (ADR-0019) on the kitchen counter: in short bursts the blades spin, the fruit whirls in the
 * smoothie and a vortex opens on top. Powerful, but used a few minutes a week.
 */

const JAR = { bottom: 0.055, top: 0.075, height: 0.25 };
const LEVEL = 0.14;
const BLEND = 3;
const REST = 2;

export const frame = { target: new THREE.Vector3(0.1, 1.1, 0.3), radius: 0.33 };

export const note = () => 'Mucha potencia (450 W), pero solo unos minutos a la semana.';

const jarRadiusAt = (height) => JAR.bottom + (JAR.top - JAR.bottom) * (height / JAR.height);

export const build = (m) => {
    const group = room(m, { height: 1.7 });
    const { group: kitchen, top } = counter(m, { width: 1.2, x: 0.1 });
    group.add(kitchen, box(1.24, 0.5, 0.01, new THREE.MeshStandardMaterial({ color: '#d9e4e8', roughness: 0.4 }), 0.1, top + 0.25, 0.005));

    const blender = new THREE.Group();
    blender.position.set(0.1, top, 0.3);
    group.add(blender);

    // The base with its knob and buttons.
    const base = new THREE.MeshStandardMaterial({ color: '#2b2f36', roughness: 0.45 });
    blender.add(rounded(0.17, 0.13, 0.17, 0.025, base, 0, 0.065, 0, 3));
    blender.add(box(0.172, 0.012, 0.172, m.steel, 0, 0.12, 0));
    const knob = cylinder(0.022, 0.022, 0.018, m.steel, -0.03, 0.06, 0.086, 18);
    knob.rotation.x = Math.PI / 2;
    blender.add(knob);
    ['#4ade80', '#f2c94c', '#e0503b'].forEach((color, index) => {
        blender.add(box(0.016, 0.01, 0.006, new THREE.MeshStandardMaterial({ color, roughness: 0.5 }), 0.025 + index * 0.022, 0.06, 0.087));
    });

    // The jar: collar, glass, lid and handle.
    const jarY = 0.13 + 0.03;
    blender.add(cylinder(0.062, 0.066, 0.03, base, 0, 0.13 + 0.015, 0, 24));
    const glass = mesh(new THREE.CylinderGeometry(JAR.top, JAR.bottom, JAR.height, 28, 1, true), m.glass, { cast: false });
    glass.position.y = jarY + JAR.height / 2;
    blender.add(glass);
    blender.add(cylinder(JAR.top + 0.004, JAR.top + 0.004, 0.02, base, 0, jarY + JAR.height + 0.01, 0, 28));
    blender.add(cylinder(0.025, 0.025, 0.02, base, 0, jarY + JAR.height + 0.03, 0, 18));
    blender.add(box(0.02, 0.17, 0.03, m.glass, JAR.top + 0.035, jarY + JAR.height * 0.55, 0));
    [0.3, 0.85].forEach((share) => blender.add(box(0.04, 0.015, 0.025, m.glass, JAR.top + 0.012, jarY + JAR.height * share, 0)));

    // The smoothie, with a vortex while it blends.
    const smoothie = new THREE.MeshStandardMaterial({ color: '#f39c4a', roughness: 0.6, transparent: true, opacity: 0.88 });
    const liquid = mesh(new THREE.CylinderGeometry(jarRadiusAt(LEVEL) - 0.003, JAR.bottom - 0.003, LEVEL, 28), smoothie, { cast: false });
    liquid.position.y = jarY + LEVEL / 2;
    const vortex = mesh(new THREE.ConeGeometry(jarRadiusAt(LEVEL) - 0.006, 0.06, 28, 1, true), new THREE.MeshStandardMaterial({ color: '#d9822f', roughness: 0.6, side: THREE.DoubleSide }), { cast: false });
    vortex.rotation.x = Math.PI;
    vortex.position.y = jarY + LEVEL - 0.028;
    blender.add(liquid, vortex);

    // Blades at the bottom.
    const blades = new THREE.Group();
    blades.position.y = jarY + 0.015;
    blades.add(box(0.07, 0.004, 0.012, m.steel), box(0.012, 0.004, 0.07, m.steel), cylinder(0.008, 0.008, 0.02, m.steel, 0, -0.008, 0, 10));
    blender.add(blades);

    // Pieces of fruit in the smoothie.
    const colors = ['#f2c94c', '#e0503b', '#f28fb0', '#7bbf5a'];
    const pieces = Array.from({ length: 9 }, (_, index) => {
        const piece = new THREE.Mesh(new THREE.BoxGeometry(0.016, 0.016, 0.016), new THREE.MeshStandardMaterial({ color: colors[index % colors.length], roughness: 0.6 }));
        blender.add(piece);

        return { piece, angle: (index / 9) * Math.PI * 2, height: 0.03 + (index % 4) * 0.025, reach: 0.02 + (index % 3) * 0.012 };
    });

    let time = 0;
    let speed = 0;

    return {
        group,
        update(dt, still = false) {
            time += dt;
            const blending = !still && time % (BLEND + REST) < BLEND;
            speed += ((blending ? 1 : 0) - speed) * Math.min(1, dt * (blending ? 6 : 2.5));
            blades.rotation.y += speed * 60 * dt;
            vortex.visible = speed > 0.15;
            vortex.scale.set(1, Math.max(0.01, speed), 1);
            vortex.rotation.y += speed * 12 * dt;
            liquid.scale.y = 1 + speed * 0.06;
            pieces.forEach((item) => {
                item.angle += (0.3 + speed * 9) * dt;
                const y = item.height + speed * 0.03 * Math.sin(time * 6 + item.angle);
                item.piece.position.set(Math.cos(item.angle) * item.reach, jarY + y, Math.sin(item.angle) * item.reach);
                item.piece.rotation.set(item.angle, item.angle * 0.7, 0);
            });
            // It shakes on the counter while it blends.
            blender.position.x = 0.1 + (blending ? Math.sin(time * 80) * 0.0007 : 0);
        },
    };
};
