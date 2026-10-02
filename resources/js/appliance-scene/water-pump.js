import * as THREE from 'three';
import { box, cylinder, DEG, mesh, room } from './parts.js';

/**
 * Water pump (ADR-0019) on the patio: it draws water from the tank and sends it up the pipe to the
 * roof tank; the water runs inside the pipes. 1 HP has a bigger motor and moves more water, faster.
 */

const POWERS = {
    half_hp: { motor: 0.075, length: 0.22, flow: 0.35, drops: 18 },
    one_hp: { motor: 0.095, length: 0.28, flow: 0.6, drops: 30 },
};

const PUMP = new THREE.Vector3(0.38, 0, 0.5);
const PIPE = 0.022;

export const frame = { target: new THREE.Vector3(0.1, 0.55, 0.45), radius: 0.82 };

const powerOf = (variant) => (POWERS[variant] ? variant : 'half_hp');

export const note = (variant) => (powerOf(variant) === 'one_hp'
    ? '1 HP: más presión y caudal, para varios pisos o un negocio; gasta casi el doble.'
    : '½ HP: suficiente para subir el agua al tanque de una casa.');

/** Water running inside a pipe: drops that follow its curve. */
const flowing = (curve, count) => {
    const group = new THREE.Group();
    const material = new THREE.MeshBasicMaterial({ color: '#5ab4ff', transparent: true, opacity: 0.9 });
    const geometry = new THREE.SphereGeometry(PIPE * 0.55, 8, 6);
    const drops = Array.from({ length: count }, (_, index) => {
        const drop = new THREE.Mesh(geometry, material);
        group.add(drop);

        return { drop, at: index / count };
    });

    return {
        group,
        update(advance) {
            drops.forEach((item) => {
                item.at = (item.at + advance) % 1;
                curve.getPointAt(item.at, item.drop.position);
            });
        },
    };
};

export const build = (m, variant) => {
    const power = POWERS[powerOf(variant)];
    const group = room(m, { height: 1.7, floor: m.pad });
    const plastic = new THREE.MeshStandardMaterial({ color: '#2f6fb3', roughness: 0.5 });
    const pipeMaterial = new THREE.MeshStandardMaterial({ color: '#f2f2ee', roughness: 0.5, transparent: true, opacity: 0.55 });

    // The tank on the left, with its lid.
    const tank = new THREE.Group();
    tank.position.set(-0.42, 0, 0.45);
    tank.add(cylinder(0.3, 0.27, 0.75, new THREE.MeshStandardMaterial({ color: '#1f2a33', roughness: 0.7 }), 0, 0.375, 0, 28));
    tank.add(cylinder(0.31, 0.31, 0.05, new THREE.MeshStandardMaterial({ color: '#1f2a33', roughness: 0.6 }), 0, 0.775, 0, 28));
    [0.2, 0.4, 0.6].forEach((y) => {
        const band = mesh(new THREE.TorusGeometry(0.29, 0.008, 6, 40), m.dark, { cast: false });
        band.rotation.x = Math.PI / 2;
        band.position.y = y;
        tank.add(band);
    });
    group.add(tank);

    // The pump: motor with fins, the pump body and a base.
    const pump = new THREE.Group();
    pump.position.copy(PUMP);
    pump.add(box(power.length + 0.16, 0.025, 0.16, m.dark, 0, 0.0125, 0));
    const motor = cylinder(power.motor, power.motor, power.length, plastic, 0.05, power.motor + 0.04, 0, 24);
    motor.rotation.z = Math.PI / 2;
    pump.add(motor);
    for (let fin = 0; fin < 6; fin++) {
        const ring = mesh(new THREE.TorusGeometry(power.motor + 0.004, 0.003, 4, 24), plastic, { cast: false });
        ring.rotation.y = Math.PI / 2;
        ring.position.set(0.05 - power.length / 2 + 0.03 + fin * (power.length - 0.06) / 5, power.motor + 0.04, 0);
        pump.add(ring);
    }
    // The fan cover at the back of the motor, where its fan turns.
    const fanCover = cylinder(power.motor * 0.95, power.motor * 0.95, 0.03, m.dark, 0.05 + power.length / 2 + 0.015, power.motor + 0.04, 0, 24);
    fanCover.rotation.z = Math.PI / 2;
    const fan = new THREE.Group();
    fan.position.set(0.05 + power.length / 2 + 0.032, power.motor + 0.04, 0);
    for (let blade = 0; blade < 4; blade++) {
        const holder = new THREE.Group();
        holder.rotation.x = (blade * Math.PI) / 2;
        holder.add(box(0.004, power.motor * 0.8, 0.02, m.metal, 0, power.motor * 0.4, 0));
        fan.add(holder);
    }
    const volute = cylinder(power.motor * 1.15, power.motor * 1.15, 0.06, m.metal, 0.05 - power.length / 2 - 0.035, power.motor + 0.04, 0, 24);
    volute.rotation.z = Math.PI / 2;
    pump.add(fanCover, fan, volute);
    group.add(pump);

    // Pipes: from the bottom of the tank into the pump, and from the pump up the wall to the roof tank.
    const inletX = PUMP.x + 0.05 - power.length / 2 - 0.065;
    const centerY = power.motor + 0.04;
    const suction = new THREE.CatmullRomCurve3([
        new THREE.Vector3(-0.15, 0.1, 0.45),
        new THREE.Vector3(0.05, 0.1, 0.48),
        new THREE.Vector3(inletX - 0.05, centerY, PUMP.z),
        new THREE.Vector3(inletX, centerY, PUMP.z),
    ]);
    const discharge = new THREE.CatmullRomCurve3([
        new THREE.Vector3(inletX + 0.02, centerY + power.motor * 1.1, PUMP.z),
        new THREE.Vector3(inletX + 0.02, centerY + 0.25, PUMP.z - 0.05),
        new THREE.Vector3(inletX + 0.04, centerY + 0.4, 0.12),
        new THREE.Vector3(inletX + 0.05, 0.8, 0.05),
        new THREE.Vector3(inletX + 0.05, 1.7, 0.05),
    ]);
    group.add(
        mesh(new THREE.TubeGeometry(suction, 40, PIPE, 10, false), pipeMaterial, { cast: false }),
        mesh(new THREE.TubeGeometry(discharge, 60, PIPE, 10, false), pipeMaterial, { cast: false }),
    );
    const intake = flowing(suction, Math.round(power.drops * 0.5));
    const lift = flowing(discharge, power.drops);
    group.add(intake.group, lift.group);

    // A pressure gauge on the outlet.
    const gauge = cylinder(0.028, 0.028, 0.015, m.metal, inletX + 0.07, centerY + 0.22, PUMP.z + 0.02, 18);
    gauge.rotation.x = Math.PI / 2;
    const needle = box(0.003, 0.022, 0.002, new THREE.MeshBasicMaterial({ color: '#d63c2f' }), 0, 0.008, 0);
    const dial = new THREE.Group();
    dial.position.set(inletX + 0.07, centerY + 0.22, PUMP.z + 0.03);
    dial.rotation.z = -(variant === 'one_hp' ? 70 : 35) * DEG;
    dial.add(needle);
    group.add(gauge, cylinder(0.024, 0.024, 0.002, new THREE.MeshBasicMaterial({ color: '#f5f5f0' }), inletX + 0.07, centerY + 0.22, PUMP.z + 0.028, 18).rotateX(Math.PI / 2), dial);

    let time = 0;

    return {
        group,
        update(dt, still = false) {
            time += dt;
            if (still) {
                intake.update(0);
                lift.update(0);

                return;
            }
            fan.rotation.x += 30 * dt;
            intake.update(dt * power.flow);
            lift.update(dt * power.flow * 0.6);
            // The motor hums: a tiny tremble.
            pump.position.y = Math.sin(time * 90) * 0.0008;
        },
    };
};
