import * as THREE from 'three';
import { box, cylinder, DEG, drift, mesh, room, table } from './parts.js';

/**
 * Fan (ADR-0019) in the same room for its three types, so their sizes compare: a table fan on a small
 * table, a stand fan on the floor and a ceiling fan. The blades spin, the table and stand fans swing
 * from side to side, and specks show where the air goes.
 */

const CEILING = 2.45;

const typeOf = (variant) => (['table', 'stand', 'ceiling'].includes(variant) ? variant : 'stand');

/** Each type framed on its own: the ceiling one seen from a little below. */
export const frame = (variant) => ({
    table: { target: new THREE.Vector3(0.38, 0.78, 0.45), radius: 0.62 },
    stand: { target: new THREE.Vector3(0.32, 0.72, 0.5), radius: 0.9 },
    ceiling: { target: new THREE.Vector3(0.12, 1.55, 0.62), radius: 1.2, polar: 94 * DEG },
}[typeOf(variant)]);

export const note = (variant) => ({
    table: 'De mesa: pequeño y cerca de ti; es el que menos gasta.',
    stand: 'De pie: mueve más aire y gira de lado a lado para repartirlo.',
    ceiling: 'De techo: aspas grandes que mueven el aire de todo el cuarto; gasta un poco más.',
}[typeOf(variant)]);

/** A fan head facing +z: motor, blades in a round cage. Returns the head and its rotor. */
const fanHead = (m, radius, bladeMaterial) => {
    const head = new THREE.Group();
    const motor = cylinder(radius * 0.32, radius * 0.38, radius * 0.75, m.plastic, 0, 0, -radius * 0.45, 20);
    motor.rotation.x = Math.PI / 2;
    head.add(motor);

    const rotor = new THREE.Group();
    rotor.position.z = radius * 0.05;
    rotor.add(mesh(new THREE.SphereGeometry(radius * 0.14, 14, 10), m.plastic));
    for (let index = 0; index < 3; index++) {
        const holder = new THREE.Group();
        holder.rotation.z = (index * 2 * Math.PI) / 3;
        const blade = mesh(new THREE.CircleGeometry(radius * 0.42, 20), bladeMaterial);
        blade.scale.set(1, 0.55, 1);
        blade.position.x = radius * 0.5;
        blade.rotation.y = 22 * DEG;
        holder.add(blade);
        rotor.add(holder);
    }
    head.add(rotor);

    // The cage: rings in front and behind, and a few ribs.
    [radius * 0.15, -radius * 0.12].forEach((z) => {
        const ring = mesh(new THREE.TorusGeometry(radius, radius * 0.02, 6, 40), m.metal, { cast: false });
        ring.position.z = z;
        head.add(ring);
    });
    for (let index = 0; index < 8; index++) {
        const rib = box(radius * 0.02, radius * 2, radius * 0.02, m.metal, 0, 0, radius * 0.16);
        rib.rotation.z = (index * Math.PI) / 8;
        head.add(rib);
    }

    return { head, rotor };
};

export const build = (m, variant) => {
    const type = typeOf(variant);
    const group = room(m, { height: CEILING, width: 2.6, x: 0.1, depth: 1.6 });
    group.add(box(2.6, 0.06, 1.0, m.trim, 0.1, CEILING + 0.03, 0.5));

    const blades = new THREE.MeshStandardMaterial({ color: '#7fb3d9', roughness: 0.4, transparent: true, opacity: 0.85, side: THREE.DoubleSide });
    let rotor;
    let swing = null;
    let spin = 18;
    let breeze;

    if (type === 'ceiling') {
        const fan = new THREE.Group();
        fan.position.set(0.1, CEILING, 0.75);
        fan.add(cylinder(0.08, 0.08, 0.04, m.plastic, 0, -0.02, 0));
        fan.add(cylinder(0.012, 0.012, 0.32, m.metal, 0, -0.2, 0, 8));
        fan.add(cylinder(0.11, 0.13, 0.12, m.plastic, 0, -0.42, 0));
        rotor = new THREE.Group();
        rotor.position.y = -0.47;
        for (let index = 0; index < 5; index++) {
            const holder = new THREE.Group();
            holder.rotation.y = (index * 2 * Math.PI) / 5;
            const blade = box(0.56, 0.012, 0.14, m.wood, 0.4, 0, 0);
            blade.rotation.x = 12 * DEG;
            holder.add(box(0.12, 0.01, 0.03, m.metal, 0.12, 0, 0), blade);
            rotor.add(holder);
        }
        fan.add(rotor, cylinder(0.08, 0.05, 0.08, m.plastic, 0, -0.55, 0));
        group.add(fan);
        spin = 4.5;
        // The air goes down and spreads over the room.
        breeze = drift({
            count: 30, length: 1.3, speed: 0.35, size: 0.012, color: '#cfe8ff', opacity: 0.55,
            emit: ([angle, reach, spread]) => ({
                origin: new THREE.Vector3(0.1 + Math.cos(angle * Math.PI * 2) * reach * 0.6, CEILING - 0.6, 0.75 + Math.sin(angle * Math.PI * 2) * reach * 0.6),
                direction: new THREE.Vector3(Math.cos(angle * Math.PI * 2) * spread * 0.4, -1, Math.sin(angle * Math.PI * 2) * spread * 0.4).normalize(),
            }),
        });
        group.add(breeze.group);
    } else {
        const stand = type === 'stand';
        const radius = stand ? 0.22 : 0.15;
        const fan = new THREE.Group();
        let height;
        if (stand) {
            fan.position.set(0.45, 0, 0.55);
            height = 1.15;
            fan.add(cylinder(0.18, 0.2, 0.04, m.plastic, 0, 0.02, 0));
        } else {
            const { group: desk, top } = table(m, { width: 0.6, depth: 0.45, height: 0.6, x: 0.45, z: 0.4 });
            group.add(desk);
            fan.position.set(0.45, top, 0.4);
            height = 0.3;
            fan.add(cylinder(0.1, 0.12, 0.03, m.plastic, 0, 0.015, 0));
        }
        fan.add(cylinder(0.018, 0.022, height, m.metal, 0, height / 2, 0, 10));
        const neck = new THREE.Group();
        neck.position.y = height;
        const { head, rotor: headRotor } = fanHead(m, radius, blades);
        head.position.set(0, radius * 0.15, 0);
        neck.add(head);
        fan.add(neck);
        group.add(fan);
        rotor = headRotor;
        // It turns from side to side, around the room's front-left.
        swing = (time) => { neck.rotation.y = -0.35 + Math.sin(time * 0.6) * 0.55; };

        breeze = drift({
            count: 26, length: 0.9, speed: 0.6, size: 0.012, color: '#cfe8ff', opacity: 0.55,
            emit: ([angle, reach]) => ({
                origin: new THREE.Vector3(Math.cos(angle * Math.PI * 2) * reach * radius, Math.sin(angle * Math.PI * 2) * reach * radius, radius * 0.25),
                direction: new THREE.Vector3((angle - 0.5) * 0.2, (reach - 0.5) * 0.15, 1).normalize(),
            }),
        });
        head.add(breeze.group);
    }

    let time = 0;

    return {
        group,
        update(dt, still = false) {
            time += dt;
            if (still) {
                swing?.(0);
            } else {
                rotor.rotation[type === 'ceiling' ? 'y' : 'z'] -= spin * dt;
                swing?.(time);
            }
            breeze.update(still ? 0 : dt, 0.85);
        },
    };
};
