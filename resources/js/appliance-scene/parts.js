import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';

/**
 * Pieces shared by the appliance models (ADR-0019), in meters: y up, the wall's face at z = 0 and the
 * room toward +z. The materials (`m`) come from the scene and follow the light or dark theme.
 */

export const DEG = Math.PI / 180;
export const INVERTER_GREEN = '#16958a';

export const mesh = (geometry, material, { cast = true, receive = true } = {}) => {
    const object = new THREE.Mesh(geometry, material);
    object.castShadow = cast;
    object.receiveShadow = receive;

    return object;
};

export const box = (width, height, depth, material, x = 0, y = 0, z = 0) => {
    const object = mesh(new THREE.BoxGeometry(width, height, depth), material);
    object.position.set(x, y, z);

    return object;
};

export const rounded = (width, height, depth, radius, material, x = 0, y = 0, z = 0, segments = 3) => {
    const object = mesh(new RoundedBoxGeometry(width, height, depth, segments, radius), material);
    object.position.set(x, y, z);

    return object;
};

/** A vertical cylinder centered on (x, y, z). */
export const cylinder = (radiusTop, radiusBottom, height, material, x = 0, y = 0, z = 0, segments = 20) => {
    const object = mesh(new THREE.CylinderGeometry(radiusTop, radiusBottom, height, segments), material);
    object.position.set(x, y, z);

    return object;
};

/**
 * A plane facing +z painted on a canvas; `draw(context, width, height, time)` paints it. Call
 * `repaint(time)` to animate it (screens, displays).
 */
export const canvasPlane = (width, height, draw, { resolution = 256, transparent = false } = {}) => {
    const canvas = document.createElement('canvas');
    canvas.width = resolution;
    canvas.height = Math.max(8, Math.round((resolution * height) / width));
    const context = canvas.getContext('2d');
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.anisotropy = 4;
    const plane = mesh(
        new THREE.PlaneGeometry(width, height),
        new THREE.MeshBasicMaterial({ map: texture, transparent, toneMapped: false }),
        { cast: false, receive: false },
    );
    const repaint = (time = 0) => {
        context.clearRect(0, 0, canvas.width, canvas.height);
        draw(context, canvas.width, canvas.height, time);
        texture.needsUpdate = true;
    };
    repaint();

    return { plane, repaint };
};

/** Text on a plane; `pill` gives it a rounded background. */
export const label = (text, { width, height, color, background = null, pill = false, font = 'bold 40px system-ui, sans-serif' }) => canvasPlane(width, height, (context, w, h) => {
    if (background) {
        context.fillStyle = background;
        context.beginPath();
        context.roundRect(0, 0, w, h, pill ? h / 2 : 0);
        context.fill();
    }
    context.fillStyle = color;
    context.font = font;
    context.textAlign = 'center';
    context.textBaseline = 'middle';
    context.fillText(text, w / 2, h / 2 + 2);
}, { transparent: background === null || pill }).plane;

/** The "INVERTER" pill that inverter models carry. */
export const inverterBadge = (width = 0.18) => label('INVERTER', {
    width, height: width * 0.21, color: '#ffffff', background: INVERTER_GREEN, pill: true, font: 'bold 34px system-ui, sans-serif',
});

/** A piece of a room: wall with its baseboard and the floor in front. */
export const room = (m, { width = 2.3, height = 1.55, depth = 1.25, x = 0.05, floor = m.floor } = {}) => {
    const group = new THREE.Group();
    group.add(
        box(width, height, 0.1, m.wall, x, height / 2, -0.05),
        box(width, 0.08, 0.02, m.trim, x, 0.04, 0.01),
        box(width, 0.05, depth, floor, x, -0.025, depth / 2),
    );

    return group;
};

/** A kitchen counter against the wall; returns the group and the height of its top. */
export const counter = (m, { width = 1.4, depth = 0.6, height = 0.9, x = 0 } = {}) => {
    const group = new THREE.Group();
    const top = 0.04;
    group.add(
        box(width, height - top - 0.08, depth - 0.04, m.cabinet, x, (height - top + 0.08) / 2, depth / 2 - 0.02),
        box(width - 0.02, 0.08, depth - 0.1, m.dark, x, 0.04, depth / 2 - 0.06),
        box(width + 0.04, top, depth + 0.02, m.counter, x, height - top / 2, depth / 2),
    );
    // Cabinet doors: a seam every 0.45 m and a small handle each.
    const doors = Math.max(1, Math.round(width / 0.45));
    for (let index = 1; index < doors; index++) {
        group.add(box(0.006, height - top - 0.12, 0.004, m.trim, x - width / 2 + (index * width) / doors, (height - top + 0.08) / 2, depth - 0.038));
    }
    for (let index = 0; index < doors; index++) {
        group.add(box(0.1, 0.012, 0.012, m.metal, x - width / 2 + ((index + 0.5) * width) / doors, height - 0.14, depth - 0.03));
    }

    return { group, top: height };
};

/** A wooden table or desk; returns the group and the height of its top. */
export const table = (m, { width = 1.1, depth = 0.6, height = 0.74, x = 0, z = 0.45 } = {}) => {
    const group = new THREE.Group();
    group.add(box(width, 0.035, depth, m.wood, x, height - 0.0175, z));
    [[-1, -1], [1, -1], [-1, 1], [1, 1]].forEach(([sx, sz]) => {
        group.add(box(0.04, height - 0.035, 0.04, m.wood, x + sx * (width / 2 - 0.05), (height - 0.035) / 2, z + sz * (depth / 2 - 0.05)));
    });

    return { group, top: height };
};

/** An open box with its mouth toward +z: the body of a compartment and its lining at the back. */
export const openBox = (finish, lining, width, height, depth, wall = 0.04) => {
    const group = new THREE.Group();
    group.add(
        box(wall, height, depth, finish, -width / 2 + wall / 2, 0, 0),
        box(wall, height, depth, finish, width / 2 - wall / 2, 0, 0),
        box(width, wall, depth, finish, 0, height / 2 - wall / 2, 0),
        box(width, wall, depth, finish, 0, -height / 2 + wall / 2, 0),
        box(width, height, wall, finish, 0, 0, -depth / 2 + wall / 2),
        box(width - wall * 2, height - wall * 2, 0.005, lining, 0, 0, -depth / 2 + wall + 0.003),
    );

    return group;
};

/**
 * A door hinged on one side (`side` = -1 left, 1 right) whose free edge swings toward +z; place
 * `hinge` on the hinged edge, at the front. `glass` makes it a framed glass door.
 */
export const hingedDoor = (m, { width, height, thickness = 0.05, finish, side = -1, handle = true, glass = null }) => {
    const hinge = new THREE.Group();
    const panel = new THREE.Group();
    panel.position.x = -side * width / 2;
    if (glass) {
        const rim = 0.045;
        panel.add(
            box(width - 0.006, rim, thickness, finish, 0, height / 2 - rim / 2, thickness / 2),
            box(width - 0.006, rim, thickness, finish, 0, -height / 2 + rim / 2, thickness / 2),
            box(rim, height - rim * 2, thickness, finish, -width / 2 + rim / 2, 0, thickness / 2),
            box(rim, height - rim * 2, thickness, finish, width / 2 - rim / 2, 0, thickness / 2),
            box(width - rim * 2, height - rim * 2, 0.008, glass, 0, 0, thickness / 2),
        );
    } else {
        panel.add(rounded(width - 0.006, height - 0.006, thickness, 0.015, finish, 0, 0, thickness / 2));
    }
    if (handle) {
        panel.add(box(0.022, Math.min(0.42, height * 0.6), 0.03, m.metal, -side * (width / 2 - 0.06), 0, thickness + 0.025));
    }
    hinge.add(panel);

    return { hinge, panel, swing: (angle) => { hinge.rotation.y = side * angle; } };
};

/** 0 → 1 → 0 over a loop: closed, opening, open, closing (smoothed). */
export const openingCycle = (time, { closed = 2.5, swing = 0.9, open = 2.4 } = {}) => {
    const moment = time % (closed + swing * 2 + open);
    const raw = moment < closed ? 0
        : moment < closed + swing ? (moment - closed) / swing
            : moment < closed + swing + open ? 1
                : 1 - (moment - closed - swing - open) / swing;

    return raw * raw * (3 - 2 * raw);
};

/** Moves `value` toward `target`, a little each frame. */
export const approach = (value, target, dt, rate = 2) => value + (target - value) * Math.min(1, dt * rate);

/**
 * How hard a compressor works now: an inverter keeps a steady, gentle pace; a conventional one runs at
 * full power and stops, again and again (the cycle is shortened so it can be seen).
 */
export const compressorTarget = (time, inverter, { run = 5.5, rest = 3.5, still = false } = {}) => {
    if (inverter) {
        return 0.6;
    }

    return still || time % (run + rest) < run ? 1 : 0;
};

/**
 * Specks that drift and fade: cold air, mist, steam, dust. `emit(seed)` gives each one, once, its
 * origin and direction (THREE.Vector3) from three numbers in [0, 1); `update(dt, strength)` moves them.
 */
export const drift = ({ count, emit, length = 0.7, speed = 0.5, size = 0.014, color = '#8fd3ff', opacity = 0.7 }) => {
    const group = new THREE.Group();
    const material = new THREE.MeshBasicMaterial({ color, transparent: true, opacity, depthWrite: false });
    const geometry = new THREE.SphereGeometry(size, 6, 4);
    const specks = Array.from({ length: count }, (_, index) => {
        const object = mesh(geometry, material, { cast: false, receive: false });
        group.add(object);
        const seed = [(index * 0.618034) % 1, (index * 0.414214) % 1, (index * 0.732051) % 1];

        return { object, life: (index * 0.37) % 1, ...emit(seed) };
    });

    return {
        group,
        update(dt, strength = 1) {
            group.visible = strength > 0.02;
            specks.forEach((speck) => {
                speck.life = (speck.life + dt * speed * (0.4 + 0.6 * strength)) % 1;
                speck.object.position.copy(speck.origin).addScaledVector(speck.direction, speck.life * length);
                speck.object.scale.setScalar(Math.max(0.001, Math.sin(speck.life * Math.PI) * strength));
            });
        },
    };
};
