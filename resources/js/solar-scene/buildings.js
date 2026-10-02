import * as THREE from 'three';

/**
 * Low-poly pieces of the 3D illustration (ADR-0012), in meters, with north at −z and south at +z.
 *
 * Each building returns its group and the roof surface where the panels go: an Object3D on the
 * roof whose local x runs across it, local +z runs down the slope (toward the south) and local +y
 * points out of the roof.
 */

const DEG = Math.PI / 180;

/** The drawing never shrinks below this, so a small roof still looks like a building. */
const MIN_ROOF_AREA_M2 = 24;
const DEFAULT_ROOF_AREA_M2 = 36;
const DEFAULT_PANEL_AREA_M2 = 2.6;

/** Panels on a flat roof stand on racks facing south, tilted about the latitude of La Guajira. */
export const RACK_TILT = 11 * DEG;

const mesh = (geometry, material) => {
    const object = new THREE.Mesh(geometry, material);
    object.castShadow = true;
    object.receiveShadow = true;

    return object;
};

const box = (width, height, depth, material, x = 0, y = 0, z = 0) => {
    const object = mesh(new THREE.BoxGeometry(width, height, depth), material);
    object.position.set(x, y, z);

    return object;
};

/** A solid with a (z, y) profile extruded along x and centered on x = 0: walls and gables in one piece. */
const prism = (profile, length, material) => {
    const shape = new THREE.Shape(profile.map(([z, y]) => new THREE.Vector2(z, y)));
    const geometry = new THREE.ExtrudeGeometry(shape, { depth: length, bevelEnabled: false });
    geometry.rotateY(-Math.PI / 2);
    geometry.translate(length / 2, 0, 0);

    return mesh(geometry, material);
};

/** Down-slope and outward directions of a roof that faces `side` (1 south, −1 north). */
const slopeAxes = (pitch, side = 1) => ({
    down: new THREE.Vector3(0, -Math.sin(pitch), side * Math.cos(pitch)),
    normal: new THREE.Vector3(0, Math.cos(pitch), side * Math.sin(pitch)),
});

const roofSurface = (group, center, pitch, width, depth, rack = 0) => {
    const frame = new THREE.Object3D();
    frame.position.copy(center);
    frame.rotation.x = pitch;
    group.add(frame);

    return { frame, width, depth, rack };
};

/** A framed window on the south (+z) or east (+x) wall; `offset` is that wall's coordinate. */
const addWindow = (group, m, wall, along, y, width, height, offset) => {
    if (wall === 'south') {
        group.add(box(width + 0.18, height + 0.18, 0.06, m.trim, along, y, offset + 0.03));
        group.add(box(width, height, 0.08, m.glass, along, y, offset + 0.05));
    } else {
        group.add(box(0.06, height + 0.18, width + 0.18, m.trim, offset + 0.03, y, along));
        group.add(box(0.08, height, width, m.glass, offset + 0.05, y, along));
    }
};

/** House: gable roof; the panels lie on the south slope, whose area is the roof area of the project. */
const house = (area, m) => {
    const group = new THREE.Group();
    const pitch = 18 * DEG;
    const width = Math.sqrt(area * 1.7);
    const slope = area / width;
    const half = slope * Math.cos(pitch);
    const wall = 2.8;
    const ridge = wall + slope * Math.sin(pitch);
    const eave = 0.45;
    const thickness = 0.16;

    group.add(prism([[-half, 0], [half, 0], [half, wall], [0, ridge], [-half, wall]], width, m.wall));

    [1, -1].forEach((side) => {
        const { down, normal } = slopeAxes(pitch, side);
        const length = slope + eave;
        const slab = box(width + eave * 2, thickness, length, m.roof);
        slab.position.set(0, ridge, 0).addScaledVector(down, length / 2).addScaledVector(normal, thickness / 2);
        slab.rotation.x = side * pitch;
        group.add(slab);
    });
    group.add(box(width + eave * 2 + 0.1, 0.16, 0.42, m.trim, 0, ridge + thickness + 0.02, 0));

    group.add(box(1, 2.1, 0.1, m.door, -width * 0.12, 1.05, half + 0.05));
    addWindow(group, m, 'south', width * 0.25, 1.55, 1.3, 1.05, half);
    if (width >= 7.5) {
        addWindow(group, m, 'south', -width * 0.36, 1.55, 1.3, 1.05, half);
    }
    addWindow(group, m, 'east', 0, 1.55, 1.4, 1.05, width / 2);

    const { down, normal } = slopeAxes(pitch);
    const center = new THREE.Vector3(0, ridge, 0).addScaledVector(down, slope / 2 + 0.1).addScaledVector(normal, thickness + 0.005);

    return { group, roof: roofSurface(group, center, pitch, width - 0.7, slope - 0.45) };
};

/** Business: flat roof with a parapet and a storefront; the panels stand on racks. */
const business = (area, m) => {
    const group = new THREE.Group();
    const width = Math.sqrt(area * 1.5);
    const depth = area / width;
    const outerWidth = width + 1.4;
    const outerDepth = depth + 1.4;
    const height = 3.9;
    const parapet = 0.55;
    const thickness = 0.22;
    const front = outerDepth / 2;
    const parapetY = height + parapet / 2;

    group.add(box(outerWidth, height, outerDepth, m.wall, 0, height / 2, 0));
    group.add(
        box(outerWidth, parapet, thickness, m.trim, 0, parapetY, front - thickness / 2),
        box(outerWidth, parapet, thickness, m.trim, 0, parapetY, -front + thickness / 2),
        box(thickness, parapet, outerDepth - thickness * 2, m.trim, outerWidth / 2 - thickness / 2, parapetY, 0),
        box(thickness, parapet, outerDepth - thickness * 2, m.trim, -outerWidth / 2 + thickness / 2, parapetY, 0),
    );

    // Storefront: glass, door, sign and a striped awning that slopes toward the street.
    group.add(box(outerWidth * 0.55, 2.1, 0.1, m.glass, -outerWidth * 0.12, 1.2, front + 0.05));
    group.add(box(1.1, 2.3, 0.12, m.door, outerWidth * 0.3, 1.15, front + 0.06));
    group.add(box(outerWidth * 0.6, 0.62, 0.12, m.sign, 0, height - 0.5, front + 0.06));

    const awning = new THREE.Group();
    const stripes = Math.max(6, Math.round((outerWidth * 0.9) / 0.6));
    const stripe = (outerWidth * 0.9) / stripes;
    for (let index = 0; index < stripes; index++) {
        awning.add(box(stripe, 0.06, 1.3, index % 2 === 0 ? m.awning : m.awningLight, -outerWidth * 0.45 + stripe * (index + 0.5), 0, 0.65));
    }
    awning.position.set(0, 2.75, front);
    awning.rotation.x = 20 * DEG;
    group.add(awning);
    addWindow(group, m, 'east', 0, 1.7, 1.6, 1, outerWidth / 2);

    return { group, roof: roofSurface(group, new THREE.Vector3(0, height + 0.01, 0), 0, width, depth, RACK_TILT) };
};

/** Institution: a long school building with a single slope facing south, and a flagpole. */
const institution = (area, m) => {
    const group = new THREE.Group();
    const pitch = 10 * DEG;
    const width = Math.sqrt(area * 2.4);
    const slope = area / width;
    const half = (slope * Math.cos(pitch)) / 2;
    const low = 3.3;
    const high = low + slope * Math.sin(pitch);
    const eave = 0.5;
    const thickness = 0.16;
    const { normal } = slopeAxes(pitch);
    const middle = new THREE.Vector3(0, (low + high) / 2, 0);

    group.add(prism([[-half, 0], [half, 0], [half, low], [-half, high]], width, m.wall));
    group.add(box(width + 0.04, 0.8, half * 2 + 0.04, m.trim, 0, 0.4, 0));

    const slab = box(width + eave * 2, thickness, slope + eave * 2, m.roof);
    slab.position.copy(middle).addScaledVector(normal, thickness / 2);
    slab.rotation.x = pitch;
    group.add(slab);

    // Classroom windows along the front, with two doors.
    const bays = Math.max(3, Math.floor(width / 2.6));
    const step = width / bays;
    for (let index = 0; index < bays; index++) {
        const x = -width / 2 + step * (index + 0.5);
        if (index === 1 || index === bays - 2) {
            group.add(box(1.1, 2.2, 0.1, m.door, x, 1.1, half + 0.05));
        } else {
            addWindow(group, m, 'south', x, 1.85, Math.min(1.6, step * 0.6), 1.1, half);
        }
    }

    const poleX = -width / 2 - 1.2;
    const poleZ = half + 2.2;
    const pole = mesh(new THREE.CylinderGeometry(0.06, 0.07, 6.5, 8), m.frame);
    pole.position.set(poleX, 3.25, poleZ);
    group.add(pole, box(1.4, 0.85, 0.04, m.awning, poleX + 0.76, 6, poleZ));

    const center = middle.clone().addScaledVector(normal, thickness + 0.005);

    return { group, roof: roofSurface(group, center, pitch, width - 0.8, slope - 0.4) };
};

const BUILDERS = { house, business, institution };

/**
 * @param {string} propertyType house | business | institution
 * @param {number} roofAreaM2 Roof area of the project: it sets the size of the building.
 */
export const buildProperty = (propertyType, roofAreaM2, materials) => {
    const area = roofAreaM2 > 0 ? Math.max(MIN_ROOF_AREA_M2, roofAreaM2) : DEFAULT_ROOF_AREA_M2;

    return (BUILDERS[propertyType] ?? house)(area, materials);
};

/** Real size of a panel from its area (about 2 : 1, e.g. 2.28 × 1.14 m for 2.6 m²). */
export const panelSize = (panelAreaM2) => {
    const area = panelAreaM2 > 0.5 ? panelAreaM2 : DEFAULT_PANEL_AREA_M2;
    const long = Math.sqrt(area * 2);

    return { long, short: area / long };
};

/**
 * Spots for `count` panels on a roof surface: centered rows filled from the eave up. Panels keep
 * their real size unless the drawn roof is too small for them (it is an illustration, not a plan).
 *
 * @return {{slots: {x: number, z: number}[], width: number, depth: number, rack: number, landscape: boolean}}
 */
export const layoutSlots = (roof, count, panelAreaM2) => {
    const { long, short } = panelSize(panelAreaM2);
    const gap = 0.06;
    const rowPitch = (depth) => (roof.rack > 0 ? depth * Math.cos(roof.rack) + 0.55 : depth + gap);

    const plan = [
        { width: short, depth: long, landscape: false },
        { width: long, depth: short, landscape: true },
    ]
        .map((option) => {
            const cols = Math.max(1, Math.min(count, Math.floor((roof.width + gap) / (option.width + gap))));
            const rows = Math.ceil(count / cols);
            const scale = Math.min(
                1,
                roof.width / (cols * option.width + (cols - 1) * gap),
                roof.depth / (rows * rowPitch(option.depth)),
            );

            return { ...option, cols, rows, scale };
        })
        // The orientation that needs the least shrinking; portrait on a tie.
        .reduce((best, option) => (option.scale > best.scale + 0.01 ? option : best));

    const width = plan.width * plan.scale;
    const depth = plan.depth * plan.scale;
    const spacing = (plan.width + gap) * plan.scale;
    const pitch = rowPitch(plan.depth) * plan.scale;
    const slots = [];

    for (let index = 0; index < count; index++) {
        const row = Math.floor(index / plan.cols);
        const inRow = Math.min(plan.cols, count - row * plan.cols);
        const col = index - row * plan.cols;

        slots.push({
            x: (col - (inRow - 1) / 2) * spacing,
            z: ((plan.rows - 1) / 2 - row) * pitch,
        });
    }

    return { slots, width, depth, rack: roof.rack, landscape: plan.landscape };
};

/** A cardón, the columnar cactus of La Guajira. */
export const cactus = (m, height = 3) => {
    const group = new THREE.Group();
    const column = (radius, length, x, y) => {
        const object = mesh(new THREE.CylinderGeometry(radius * 0.85, radius, length, 7), m.cactus);
        object.position.set(x, y + length / 2, 0);

        return object;
    };

    group.add(column(0.28, height, 0, 0));
    [[1, 0.45, 1.1], [-1, 0.6, 0.8]].forEach(([side, at, length]) => {
        const arm = mesh(new THREE.CylinderGeometry(0.16, 0.18, 0.55, 6), m.cactus);
        arm.rotation.z = Math.PI / 2;
        arm.position.set(side * 0.38, height * at, 0);
        group.add(arm, column(0.18, length, side * 0.62, height * at - 0.05));
    });

    return group;
};

export const rock = (m, size = 0.6) => {
    const object = mesh(new THREE.DodecahedronGeometry(size, 0), m.rock);
    object.scale.set(1, 0.55, 0.85);
    object.position.y = size * 0.2;

    return object;
};
