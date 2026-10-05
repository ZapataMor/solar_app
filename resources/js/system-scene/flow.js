import * as THREE from 'three';
import { clamp, smoothstep } from './simulation.js';
import { glow, lightShaft, seeded } from './textures.js';

/**
 * What moves in the system animation: current along the cables, sunlight toward the panels.
 * Colors tell the kind of current: direct (DC), alternating (AC), what goes out to the grid and what
 * comes in from it.
 */

export const FLOW_COLORS = {
    dc: '#ffb020',
    ac: '#38bdf8',
    grid: '#4ade80',
    import: '#c084fc',
    sun: '#ffd34d',
};

const UP = new THREE.Vector3(0, 1, 0);
const CABLE_RADIUS = 0.024;

/** A path through `points` whose corners are rounded, so the cable bends instead of kinking. */
export const route = (points, fillet = 0.12) => {
    const path = new THREE.CurvePath();
    let from = points[0].clone();

    for (let index = 1; index < points.length - 1; index++) {
        const corner = points[index];
        const before = corner.clone().sub(points[index - 1]);
        const after = points[index + 1].clone().sub(corner);
        const reach = Math.min(fillet, before.length() / 2, after.length() / 2);
        const start = corner.clone().addScaledVector(before.normalize(), -reach);
        const end = corner.clone().addScaledVector(after.normalize(), reach);

        if (start.distanceTo(from) > 1e-4) {
            path.add(new THREE.LineCurve3(from, start));
        }
        path.add(new THREE.QuadraticBezierCurve3(start, corner.clone(), end));
        from = end;
    }
    path.add(new THREE.LineCurve3(from, points[points.length - 1].clone()));

    return path;
};

/**
 * A cable with current running along it. `setFlow(value)` says how much (−1 to 1: the sign is the
 * direction, a negative one runs backwards) and `update(dt)` moves the pulses. With no flow the cable is
 * just a cable. The pulses are green going out to the grid and violet coming in from it.
 *
 * @param {THREE.Vector3[]} points In the direction the current goes when the flow is positive.
 */
export const cable = (points, kind, m, { gap = 0.5 } = {}) => {
    const path = route(points);
    const length = path.getLength();
    const object = new THREE.Group();

    const tube = new THREE.Mesh(new THREE.TubeGeometry(path, Math.max(12, Math.round(length * 14)), CABLE_RADIUS, 6), m.wire);
    tube.castShadow = true;
    object.add(tube);

    const count = Math.max(2, Math.round(length / gap));
    const forward = new THREE.Color(FLOW_COLORS[kind]);
    const backward = new THREE.Color(FLOW_COLORS.import);
    const material = new THREE.MeshBasicMaterial({ color: forward, toneMapped: false });
    const beads = new THREE.InstancedMesh(new THREE.CapsuleGeometry(CABLE_RADIUS * 1.5, 0.1, 3, 8), material, count);
    beads.frustumCulled = false;
    beads.visible = false;
    object.add(beads);

    const dummy = new THREE.Object3D();
    let flow = 0;
    let target = 0;
    let phase = 0;

    const setFlow = (value) => {
        target = clamp(value, -1, 1);
    };

    const update = (dt, time) => {
        // Smooth, so a change fades in and out instead of jumping (at once on a redraw).
        flow = dt > 0 ? flow + (target - flow) * (1 - Math.exp(-dt * 5)) : target;
        const magnitude = Math.abs(flow);
        const visible = smoothstep(0.015, 0.09, magnitude);
        beads.visible = visible > 0.01;
        if (!beads.visible) {
            return;
        }

        phase += (dt * (0.4 + 0.9 * magnitude) * Math.sign(flow)) / length;
        material.color.copy(flow >= 0 ? forward : backward);
        for (let index = 0; index < count; index++) {
            const along = (((index / count + phase) % 1) + 1) % 1;
            dummy.position.copy(path.getPointAt(along));
            // The pulse lies along the cable.
            dummy.quaternion.setFromUnitVectors(UP, path.getTangentAt(along));
            // Pulses swell a little as they pass, so the current reads as pulses.
            dummy.scale.setScalar(visible * (0.85 + 0.3 * Math.sin((index / count) * Math.PI * 2 + time * 3) ** 2));
            dummy.updateMatrix();
            beads.setMatrixAt(index, dummy.matrix);
        }
        beads.instanceMatrix.needsUpdate = true;
    };

    return { object, setFlow, update, tube, length };
};

/**
 * Sunlight, seen as shafts: soft beams of parallel light falling on the panels, with specks of dust
 * drifting in them and a glint on each panel. `set(direction, strength)` follows the sun: `direction`
 * points from the sun toward the panels, and `strength` is 0 at night.
 *
 * @param {THREE.Vector3[]} targets World points on the panels, where the shafts end.
 */
export const sunlight = (targets, roofFrame, roofPoints, panelY) => {
    const group = new THREE.Group();
    const random = seeded(91);
    const length = 11;
    const shaft = lightShaft();
    const quaternion = new THREE.Quaternion();
    const direction = new THREE.Vector3(0, -1, 0);
    const side = new THREE.Vector3(1, 0, 0);
    const lift = new THREE.Vector3(0, 0, 1);
    const tint = new THREE.Color('#fff0bd');
    const beams = [];
    let strength = 0;

    // A wide shaft per column of the array, and a narrow one between them.
    const columns = targets.filter((_, index) => index < targets.length / 2);
    const entries = [
        ...columns.map((target) => ({ target, width: 1.1, base: 0.34 })),
        ...columns.map((target) => ({ target: target.clone().add(new THREE.Vector3(0.85, 0, 0.25)), width: 0.45, base: 0.42 })),
    ];
    entries.forEach(({ target, width, base }, index) => {
        const material = new THREE.MeshBasicMaterial({
            map: shaft, color: tint, transparent: true, opacity: 0, depthWrite: false, side: THREE.DoubleSide, fog: false,
        });
        const beam = new THREE.Group();
        [0, Math.PI / 2].forEach((turn) => {
            const plane = new THREE.Mesh(new THREE.PlaneGeometry(width, length), material);
            plane.rotation.y = turn;
            beam.add(plane);
        });
        group.add(beam);
        beams.push({ group: beam, material, target, base, phase: index * 1.7 });
    });

    // Dust in the light: specks along the shafts that drift slowly.
    const specks = 140;
    const positions = new Float32Array(specks * 3);
    const seeds = Array.from({ length: specks }, () => ({
        along: random(), across: (random() - 0.5) * 2.4, depth: (random() - 0.5) * 1.4, speed: 0.01 + random() * 0.02, base: targets[Math.floor(random() * targets.length)],
    }));
    const dustMaterial = new THREE.PointsMaterial({ map: glow(), color: '#fff2c4', size: 0.1, transparent: true, opacity: 0.55, blending: THREE.AdditiveBlending, depthWrite: false, fog: false });
    const dust = new THREE.Points(new THREE.BufferGeometry().setAttribute('position', new THREE.BufferAttribute(positions, 3)), dustMaterial);
    dust.frustumCulled = false;
    group.add(dust);

    // A glint on each panel where the light lands.
    const glints = roofPoints.map(({ x, z }, index) => {
        const material = new THREE.MeshBasicMaterial({ map: glow(), color: '#fff0c0', transparent: true, opacity: 0, blending: THREE.AdditiveBlending, depthWrite: false, fog: false });
        const spot = new THREE.Mesh(new THREE.PlaneGeometry(1.1, 0.7), material);
        spot.rotation.x = -Math.PI / 2;
        spot.position.set(x, panelY + 0.004, z);
        roofFrame.add(spot);

        return { material, phase: index * 0.9 };
    });

    /** Turns the shafts to the new direction of the light. */
    const aim = (next) => {
        direction.copy(next);
        quaternion.setFromUnitVectors(UP, direction.clone().negate());
        beams.forEach((beam) => {
            beam.group.quaternion.copy(quaternion);
            beam.group.position.copy(beam.target).addScaledVector(direction, -length / 2);
        });
        side.crossVectors(direction, UP);
        if (side.lengthSq() < 1e-6) {
            side.set(1, 0, 0);
        }
        side.normalize();
        lift.crossVectors(side, direction).normalize();
    };
    aim(direction);

    const set = (next, power, color) => {
        if (next.distanceToSquared(direction) > 1e-8) {
            aim(next);
        }
        strength = power;
        group.visible = power > 0.01;
        if (color) {
            tint.set(color);
        }
    };

    const point = new THREE.Vector3();
    const update = (time) => {
        if (!group.visible) {
            return;
        }
        beams.forEach(({ material, base, phase }) => {
            material.color.copy(tint);
            material.opacity = base * strength * (0.82 + 0.18 * Math.sin(time * 0.9 + phase));
        });
        dustMaterial.opacity = 0.55 * strength;
        for (let index = 0; index < specks; index++) {
            const seed = seeds[index];
            const along = (seed.along + time * seed.speed) % 1;
            point.copy(seed.base).addScaledVector(direction, -along * length * 0.8)
                .addScaledVector(side, seed.across + Math.sin(time * 0.3 + index) * 0.08)
                .addScaledVector(lift, seed.depth + Math.cos(time * 0.25 + index * 2) * 0.08);
            positions.set([point.x, point.y, point.z], index * 3);
        }
        dust.geometry.attributes.position.needsUpdate = true;
        glints.forEach(({ material, phase }) => {
            material.opacity = strength * (0.2 + 0.1 * Math.sin(time * 1.1 + phase));
        });
    };

    return { group, set, update };
};
