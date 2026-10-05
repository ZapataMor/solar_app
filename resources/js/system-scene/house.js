import * as THREE from 'three';
import { seeded } from './textures.js';

/**
 * The cutaway house of the system animation, in meters: x across, y up, north at −z and south at +z.
 * A one-storey concrete house of La Guajira, cut open at the front like an architectural section (the cut
 * faces are dark). On the left, the utility room with the equipment; on the right, the living room with its
 * kitchen. The flat roof, with a parapet, carries the water tank and the panels on their rack.
 */

export const HOUSE = {
    width: 9,
    depth: 5,
    /** Height of the walls: the underside of the roof slab. */
    wall: 3.2,
    /** Thickness of the roof slab. */
    ceiling: 0.15,
    thickness: 0.2,
    parapet: 0.45,
    /** What is left of the front wall after the cut. */
    stub: 0.4,
    /** Full height at the back, down to `z`; from there to the front it is cut at knee height, so it hides nothing. */
    partition: { x: -0.3, thickness: 0.16, z: -0.6 },
    window: { x: [2.0, 3.0], y: [1.25, 2.25] },
};

/** Inner face of the back wall: devices and cables hang from it. */
export const BACK = -HOUSE.depth / 2 + HOUSE.thickness;
/** Inner face of the side walls. */
export const INNER_X = HOUSE.width / 2 - HOUSE.thickness;
/** The plane of the cut. */
export const FRONT = HOUSE.depth / 2;
/** Underside of the roof slab, where the light hangs from, and its top, where the panels stand. */
export const CEILING_Y = HOUSE.wall;
export const ROOF_Y = HOUSE.wall + HOUSE.ceiling;
/** The two rooms along x: the utility room on the left, the living room on the right. */
export const ROOMS = {
    utility: [-INNER_X, HOUSE.partition.x - HOUSE.partition.thickness / 2],
    living: [HOUSE.partition.x + HOUSE.partition.thickness / 2, INNER_X],
};

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

/** A box between its bounds; `material` is one material, or one per face: {px, nx, py, ny, pz, nz, rest}. */
export const block = ([x0, x1], [y0, y1], [z0, z1], material, options) => {
    const faces = material.isMaterial ? material : ['px', 'nx', 'py', 'ny', 'pz', 'nz'].map((face) => material[face] ?? material.rest);
    const object = mesh(new THREE.BoxGeometry(x1 - x0, y1 - y0, z1 - z0), faces, options);
    object.position.set((x0 + x1) / 2, (y0 + y1) / 2, (z0 + z1) / 2);

    return object;
};

/** The kitchen window in the back wall: an aluminum frame, a mullion and glass to see the desert through. */
const buildWindow = (m) => {
    const group = new THREE.Group();
    const [x0, x1] = HOUSE.window.x;
    const [y0, y1] = HOUSE.window.y;
    const z = [-FRONT + HOUSE.thickness / 2 - 0.035, -FRONT + HOUSE.thickness / 2 + 0.035];
    const bar = 0.05;
    const middle = (x0 + x1) / 2;
    group.add(
        block([x0, x1], [y0, y0 + bar], z, m.frame),
        block([x0, x1], [y1 - bar, y1], z, m.frame),
        block([x0, x0 + bar], [y0, y1], z, m.frame),
        block([x1 - bar, x1], [y0, y1], z, m.frame),
        block([middle - bar / 2, middle + bar / 2], [y0, y1], z, m.frame),
        block([x0 + bar, x1 - bar], [y0 + bar, y1 - bar], [z[0] + 0.03, z[0] + 0.035], m.windowGlass, { cast: false }),
        // The sill, inside.
        block([x0 - 0.06, x1 + 0.06], [y0 - 0.03, y0], [BACK - 0.02, BACK + 0.09], m.coping),
    );

    return group;
};

/** A black plastic water tank on the roof, as on most houses of the region. */
const buildWaterTank = (m, x, z) => {
    const group = new THREE.Group();
    const base = ROOF_Y + 0.12;
    const height = 1.0;
    group.add(block([x - 0.62, x + 0.62], [ROOF_Y, base], [z - 0.62, z + 0.62], m.slab));
    const body = mesh(new THREE.CylinderGeometry(0.58, 0.5, height, 32), m.tank);
    body.position.set(x, base + height / 2, z);
    group.add(body);
    [0.25, 0.5, 0.75].forEach((at) => {
        const ring = mesh(new THREE.TorusGeometry(0.512 + 0.08 * at, 0.014, 6, 40), m.tank);
        ring.rotation.x = Math.PI / 2;
        ring.position.set(x, base + height * at, z);
        group.add(ring);
    });
    const lid = mesh(new THREE.CylinderGeometry(0.24, 0.26, 0.07, 24), m.tank);
    lid.position.set(x, base + height + 0.035, z);
    group.add(lid);

    return group;
};

/** @return {{group: THREE.Group}} */
export const buildHouse = (m) => {
    const group = new THREE.Group();
    const half = HOUSE.width / 2;
    const { thickness: t, wall: height, partition, window: opening } = HOUSE;
    const [utilityFrom] = ROOMS.utility;
    const [, livingTo] = ROOMS.living;
    const between = [partition.x - partition.thickness / 2, partition.x + partition.thickness / 2];
    const stubZ = FRONT - t;
    const { cut, facade } = m;

    // Desert ground out to the horizon (the fog hides its edge), and the concrete base of the house.
    const ground = mesh(new THREE.CircleGeometry(90, 64), m.ground, { cast: false });
    ground.rotation.x = -Math.PI / 2;
    ground.position.y = -0.2;
    group.add(ground);
    group.add(block([-half - 0.25, half + 0.25], [-0.2, 0], [-FRONT - 0.25, FRONT + 0.25], m.slab));

    // Floors: polished concrete in the utility room, wooden planks in the living room.
    group.add(block([utilityFrom, between[0]], [0, 0.02], [BACK, stubZ], m.floorConcrete));
    group.add(block([between[1], livingTo], [0, 0.02], [BACK, stubZ], m.floorWood));

    // Side walls: inside, the color of their room; outside, the facade; at the front, the cut.
    group.add(block([-half, -half + t], [0, height], [-FRONT, FRONT], { px: m.utilityWall, pz: cut, rest: facade }));
    group.add(block([half - t, half], [0, height], [-FRONT, FRONT], { nx: m.livingWall, pz: cut, rest: facade }));

    // Back wall: the utility room's part, and the living room's around its window.
    const back = [-FRONT, BACK];
    const inside = (material) => ({ pz: material, rest: facade });
    group.add(
        block([utilityFrom, partition.x], [0, height], back, inside(m.utilityWall)),
        block([partition.x, opening.x[0]], [0, height], back, inside(m.livingWall)),
        block([opening.x[1], livingTo], [0, height], back, inside(m.livingWall)),
        block(opening.x, [0, opening.y[0]], back, inside(m.livingWall)),
        block(opening.x, [opening.y[1], height], back, inside(m.livingWall)),
        buildWindow(m),
    );

    // The partition between the rooms: whole at the back, cut low toward the front (the cut steps down, as in
    // a cutaway drawing), so it does not hide the equipment from the viewer.
    const sides = { nx: m.utilityWall, px: m.livingWall };
    group.add(
        block(between, [0, height], [BACK, partition.z], { ...sides, pz: cut, rest: m.trim }),
        block(between, [0, HOUSE.stub], [partition.z, FRONT], { ...sides, py: cut, pz: cut, rest: m.trim }),
    );

    // What is left of the front wall, cut at knee height.
    group.add(
        block([utilityFrom, between[0]], [0, HOUSE.stub], [stubZ, FRONT], { nz: m.utilityWall, pz: facade, rest: cut }),
        block([between[1], livingTo], [0, HOUSE.stub], [stubZ, FRONT], { nz: m.livingWall, pz: facade, rest: cut }),
    );

    // The roof slab, and its parapet on three sides (the front one went with the cut).
    const top = ROOF_Y + HOUSE.parapet;
    const parapet = 0.15;
    group.add(
        block([-half, half], [height, ROOF_Y], [-FRONT, FRONT], { ny: m.ceiling, py: m.roofTop, pz: cut, rest: facade }),
        block([-half, half], [ROOF_Y, top], [-FRONT, -FRONT + parapet], { pz: m.parapetIn, py: m.coping, rest: facade }),
        block([-half, -half + parapet], [ROOF_Y, top], [-FRONT + parapet, FRONT], { px: m.parapetIn, py: m.coping, pz: cut, rest: facade }),
        block([half - parapet, half], [ROOF_Y, top], [-FRONT + parapet, FRONT], { nx: m.parapetIn, py: m.coping, pz: cut, rest: facade }),
    );

    // A darker plinth along the outside of the walls, where the dust and the rain splash.
    const plinth = 0.35;
    const flat = { cast: false };
    group.add(
        block([-half - 0.012, -half], [0, plinth], [-FRONT, FRONT - 0.005], m.plinth, flat),
        block([half, half + 0.012], [0, plinth], [-FRONT, FRONT - 0.005], m.plinth, flat),
        block([-half, half], [0, plinth], [-FRONT - 0.012, -FRONT], m.plinth, flat),
    );

    group.add(buildWaterTank(m, 3.2, -1.45));

    return { group };
};

/** The utility pole outside, where the grid cable ends; `side` is where the line leaves (−1 left, 1 right). */
export const buildPole = (m, x, z, side = 1) => {
    const group = new THREE.Group();
    const height = 5.6;
    const pole = mesh(new THREE.CylinderGeometry(0.11, 0.14, height, 10), m.pole);
    pole.position.set(x, height / 2, z);
    group.add(pole);
    group.add(box(1.5, 0.1, 0.1, m.pole, x, height - 0.35, z));
    [-0.6, 0.6].forEach((dx) => group.add(mesh(new THREE.CylinderGeometry(0.04, 0.05, 0.16, 8), m.casing, { cast: false }).translateX(x + dx).translateY(height - 0.24).translateZ(z)));

    // The line to the neighborhood, with a little sag.
    const wire = new THREE.CatmullRomCurve3([
        new THREE.Vector3(x + side * 0.6, height - 0.14, z),
        new THREE.Vector3(x + side * 2.4, height - 0.5, z),
        new THREE.Vector3(x + side * 4.2, height - 0.2, z),
    ]);
    group.add(mesh(new THREE.TubeGeometry(wire, 20, 0.015, 5), m.wire, { cast: false }));

    return group;
};

/** A cardón, the columnar cactus of La Guajira: a ribbed trunk with rounded ends and a few arms. */
const cardon = (m, height, random) => {
    const group = new THREE.Group();
    const column = (radius, length) => {
        const piece = new THREE.Group();
        const body = mesh(new THREE.CylinderGeometry(radius * 0.92, radius, length, 16, 1), m.cactus);
        body.position.y = length / 2;
        const cap = mesh(new THREE.SphereGeometry(radius * 0.92, 16, 10, 0, Math.PI * 2, 0, Math.PI / 2), m.cactus);
        cap.position.y = length;
        piece.add(body, cap);
        // Ribs: thin darker strips along the trunk.
        for (let rib = 0; rib < 8; rib++) {
            const angle = (rib / 8) * Math.PI * 2;
            const strip = mesh(new THREE.BoxGeometry(radius * 0.05, length * 0.96, radius * 0.1), m.cactusRib, { cast: false });
            strip.position.set(Math.cos(angle) * radius * 0.95, length / 2, Math.sin(angle) * radius * 0.95);
            strip.rotation.y = -angle;
            piece.add(strip);
        }

        return piece;
    };
    group.add(column(0.26, height));
    const arms = 2 + Math.round(random());
    for (let arm = 0; arm < arms; arm++) {
        const side = arm % 2 === 0 ? 1 : -1;
        const at = height * (0.38 + random() * 0.25);
        const reach = 0.5 + random() * 0.25;
        const elbow = mesh(new THREE.CylinderGeometry(0.15, 0.17, reach, 12), m.cactus);
        elbow.rotation.z = Math.PI / 2;
        elbow.position.set(side * (0.26 + reach / 2 - 0.05), at, 0);
        const upright = column(0.16, height * (0.3 + random() * 0.25));
        upright.position.set(side * (0.26 + reach - 0.05), at - 0.03, 0);
        group.add(elbow, upright);
    }

    return group;
};

/** A boulder: a rounded stone with its surface pushed in and out. */
const boulder = (m, size, random) => {
    const geometry = new THREE.IcosahedronGeometry(size, 2);
    const position = geometry.attributes.position;
    const vertex = new THREE.Vector3();
    const phase = random() * 10;
    for (let index = 0; index < position.count; index++) {
        vertex.fromBufferAttribute(position, index);
        const push = 1 + 0.18 * Math.sin(vertex.x * 3.1 + phase) * Math.cos(vertex.z * 2.7 + phase) + 0.07 * Math.sin(vertex.y * 7 + phase);
        vertex.multiplyScalar(push);
        position.setXYZ(index, vertex.x, vertex.y, vertex.z);
    }
    geometry.computeVertexNormals();
    const stone = mesh(geometry, m.rock);
    stone.scale.set(1, 0.55 + random() * 0.2, 0.8 + random() * 0.3);
    stone.position.y = size * 0.18;
    stone.rotation.y = random() * Math.PI;

    return stone;
};

/** Desert dressing, kept sparse: two cardones and two boulders. */
export const buildScenery = (m) => {
    const group = new THREE.Group();
    const random = seeded(5);
    const place = (object, x, z, turn = true) => {
        object.position.x = x;
        object.position.z = z;
        object.position.y += -0.2;
        if (turn) {
            object.rotation.y = random() * Math.PI * 2;
        }
        group.add(object);
    };

    [[5.7, 3.0, 2.9], [-7.3, 3.4, 2.4]].forEach(([x, z, height]) => place(cardon(m, height, random), x, z));
    [[4.6, 4.7, 0.55], [-5.2, 5.0, 0.45]].forEach(([x, z, size]) => place(boulder(m, size, random), x, z, false));

    return group;
};
