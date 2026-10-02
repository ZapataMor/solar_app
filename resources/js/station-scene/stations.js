import * as THREE from 'three';
import { cactus, rock } from '../solar-scene/buildings.js';

/**
 * Low-poly data stations of the climate data page (ADR-0018), in meters, with north at −z and south
 * at +z. Each builder returns its group, how to frame it and an `update(dt)` for its idle motion.
 * They are illustrations of what each station measures, not of a specific model.
 */

const DEG = Math.PI / 180;
export const ACCENT = '#f0b429';
const LED_ON = new THREE.Color('#4ade80');
const LED_OFF = new THREE.Color('#1f5130');

const easeOutCubic = (x) => 1 - (1 - x) ** 3;

/** A status LED of its own: each station blinks when it sends. */
const statusLed = () => {
    const material = new THREE.MeshBasicMaterial({ color: LED_OFF });
    const led = mesh(new THREE.SphereGeometry(0.014, 8, 6), material, { cast: false, receive: false });

    return { led, light: (on) => material.color.copy(on ? LED_ON : LED_OFF) };
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

/** A vertical cylinder whose base sits at `y`. */
const post = (radiusTop, radiusBottom, height, material, x = 0, y = 0, z = 0, segments = 12) => {
    const object = mesh(new THREE.CylinderGeometry(radiusTop, radiusBottom, height, segments), material);
    object.position.set(x, y + height / 2, z);

    return object;
};

/** A rod lying along x, centered on (x, y, z). */
const rod = (radius, length, material, x = 0, y = 0, z = 0) => {
    const object = mesh(new THREE.CylinderGeometry(radius, radius, length, 8), material);
    object.rotation.z = Math.PI / 2;
    object.position.set(x, y, z);

    return object;
};

/** Solar cells for the small panels that power the stations. */
export const cellTexture = () => {
    const canvas = document.createElement('canvas');
    canvas.width = 64;
    canvas.height = 64;
    const context = canvas.getContext('2d');
    const gradient = context.createLinearGradient(0, 0, 64, 64);
    gradient.addColorStop(0, '#2f5a8e');
    gradient.addColorStop(1, '#19325a');
    context.fillStyle = gradient;
    context.fillRect(0, 0, 64, 64);
    context.strokeStyle = 'rgba(214, 228, 245, 0.55)';
    context.lineWidth = 2;
    for (let at = 0; at <= 64; at += 16) {
        context.beginPath();
        context.moveTo(at, 0);
        context.lineTo(at, 64);
        context.moveTo(0, at);
        context.lineTo(64, at);
        context.stroke();
    }

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;

    return texture;
};

/** A box with cells on its top face (+y). */
const solarPanel = (width, depth, m) => {
    const panel = new THREE.Mesh(new THREE.BoxGeometry(width, 0.018, depth), [m.white, m.white, m.cells, m.white, m.white, m.white]);
    panel.castShadow = true;

    return panel;
};

/**
 * Data leaving the station: rings that open and fade, a burst every `period` seconds.
 *
 * @return {{group: THREE.Group, update: (time: number) => boolean}} update says whether a burst is starting.
 */
export const signalRings = ({ period = 4, rings = 2, gap = 0.35, duration = 1.7, from = 0.1, to = 1.05 } = {}) => {
    const group = new THREE.Group();
    const geometry = new THREE.RingGeometry(0.93, 1, 64);
    const items = Array.from({ length: rings }, (_, index) => {
        const material = new THREE.MeshBasicMaterial({ color: ACCENT, transparent: true, opacity: 0, side: THREE.DoubleSide, depthWrite: false });
        const ring = new THREE.Mesh(geometry, material);
        ring.rotation.x = -Math.PI / 2;
        ring.visible = false;
        group.add(ring);

        return { ring, material, offset: index * gap };
    });

    return {
        group,
        update(time) {
            items.forEach(({ ring, material, offset }) => {
                const progress = ((((time - offset) % period) + period) % period) / duration;
                ring.visible = progress < 1;
                if (ring.visible) {
                    ring.scale.setScalar(from + (to - from) * easeOutCubic(progress));
                    material.opacity = 0.8 * (1 - progress) ** 1.6;
                }
            });

            return ((time % period) + period) % period < 0.3;
        },
    };
};

/** Sand of La Guajira with a cardón and some rocks: the floor both ground stations share. */
export const buildGround = (m) => {
    const group = new THREE.Group();
    const disk = mesh(new THREE.CylinderGeometry(2.05, 2.1, 0.3, 56), [m.groundSide, m.ground, m.groundSide], { cast: false });
    disk.position.y = -0.15;
    group.add(disk);

    [
        [cactus(m, 2.6), -1.38, -1.12, 0.42, 0.7],
        [rock(m, 0.2), 1.3, -1.1, 1, 0],
        [rock(m, 0.14), 1.62, -0.5, 1, 0.8],
        [rock(m, 0.17), -1.6, 0.7, 1, 0.3],
    ].forEach(([object, x, z, scale, turn]) => {
        const holder = new THREE.Group();
        holder.add(object);
        holder.position.set(x, 0, z);
        holder.scale.setScalar(scale);
        holder.rotation.y = turn;
        group.add(holder);
    });

    return group;
};

/**
 * Ambient Weather: an all-in-one sensor array on a mast. Rain funnel and small solar panel on top,
 * a stack of plates that shades the thermometer, cups that spin with the wind and a vane that points
 * where it comes from (the latest reading, see setWind).
 */
export const buildAmbientStation = (m) => {
    const group = new THREE.Group();
    const top = 1.9;
    // The sensors are the point: drawn larger than life over a shorter mast.
    const zoom = 1.5;

    group.add(box(0.36, 0.12, 0.36, m.concrete, 0, 0.06, 0));
    group.add(post(0.04, 0.045, top - 0.12, m.metal, 0, 0.12, 0));
    group.add(box(0.13, 0.12, 0.13, m.metal, 0, top - 0.06, 0));

    const array = new THREE.Group();
    array.position.y = top;
    array.scale.setScalar(zoom);
    group.add(array);

    const funnelInside = mesh(new THREE.CircleGeometry(0.104, 24), m.dark, { cast: false });
    funnelInside.rotation.x = -Math.PI / 2;
    funnelInside.position.y = 0.16;
    const funnel = mesh(new THREE.CylinderGeometry(0.125, 0.105, 0.06, 24, 1, true), m.whiteBothSides);
    funnel.position.y = 0.17;
    const panel = solarPanel(0.17, 0.11, m);
    panel.position.set(0, 0.11, 0.165);
    panel.rotation.x = 35 * DEG;
    array.add(
        post(0.11, 0.12, 0.14, m.white, 0, 0, 0, 20),
        funnel,
        funnelInside,
        panel,
        rod(0.016, 0.9, m.white, 0, 0.05, 0),
    );

    // Radiation shield on the north side: plates that keep the sun off the thermometer.
    const shield = new THREE.Group();
    const plate = new THREE.CylinderGeometry(0.075, 0.1, 0.016, 20);
    for (let index = 0; index < 6; index++) {
        const layer = mesh(plate, m.white);
        layer.position.y = -index * 0.034;
        shield.add(layer);
    }
    shield.add(post(0.014, 0.014, 0.2, m.white, 0, -0.19, 0, 8));
    shield.position.set(0, -0.06, -0.18);
    array.add(shield, box(0.04, 0.05, 0.12, m.white, 0, -0.03, -0.1));

    // Anemometer at the east end: three cups.
    const rotor = new THREE.Group();
    rotor.position.set(0.44, 0.21, 0);
    rotor.add(post(0.026, 0.026, 0.03, m.white, 0, -0.015, 0));
    const cup = new THREE.SphereGeometry(0.036, 12, 8, 0, Math.PI * 2, 0, Math.PI / 2);
    for (let index = 0; index < 3; index++) {
        const arm = new THREE.Group();
        arm.rotation.y = (index * 2 * Math.PI) / 3;
        const shell = mesh(cup, m.cup);
        shell.position.x = 0.125;
        shell.rotation.x = Math.PI / 2;
        arm.add(rod(0.005, 0.12, m.white, 0.06, 0, 0), shell);
        rotor.add(arm);
    }
    array.add(post(0.011, 0.011, 0.16, m.white, 0.44, 0.05, 0, 8), rotor);

    // Vane at the west end: the pointer (local +x) faces where the wind comes from.
    const vane = new THREE.Group();
    vane.position.set(-0.44, 0.2, 0);
    const pointer = mesh(new THREE.ConeGeometry(0.024, 0.075, 10), m.cup);
    pointer.rotation.z = -Math.PI / 2;
    pointer.position.x = 0.2;
    vane.add(rod(0.007, 0.34, m.white, 0.01, 0, 0), pointer, box(0.12, 0.085, 0.006, m.white, -0.16, 0, 0));
    array.add(post(0.011, 0.011, 0.15, m.white, -0.44, 0.05, 0, 8), vane);

    const { led, light } = statusLed();
    led.position.set(0, 0.09, 0.118);
    array.add(led);

    const signal = signalRings({ period: 3.6 });
    signal.group.position.y = top + 0.15 * zoom;
    group.add(signal.group);

    let time = 0;
    let spin = 0;
    let sway = 0;
    let heading = null;
    let targetHeading = Math.PI / 2;

    return {
        group,
        onGround: true,
        autoRotate: true,
        frame: { target: new THREE.Vector3(0, 1.02, 0), radius: 2.02 },
        /** km/h and degrees of the latest reading; the vane turns to the new direction. */
        setWind(speedKmh, directionDegrees) {
            const speed = Number.isFinite(speedKmh) ? Math.max(0, speedKmh) : 0;
            // Calm: the cups stand still; up to ~40 km/h they turn faster with the wind.
            spin = speed < 1 ? 0 : Math.min(14, 1.2 + speed * 0.32);
            sway = speed < 1 ? 0 : Math.min(0.09, 0.015 + speed * 0.002);
            if (Number.isFinite(directionDegrees)) {
                targetHeading = Math.PI / 2 - directionDegrees * DEG;
                heading ??= targetHeading;
            }
        },
        update(dt, still = false) {
            time += dt;
            rotor.rotation.y -= spin * dt;
            heading ??= targetHeading;
            // The shortest turn toward the new direction, smoothly.
            const turn = Math.atan2(Math.sin(targetHeading - heading), Math.cos(targetHeading - heading));
            heading += turn * Math.min(1, dt * 2.5);
            vane.rotation.y = heading + (still ? 0 : sway * Math.sin(time * 2.3) + sway * 0.5 * Math.sin(time * 5.1));
            light(!still && signal.update(time));
            signal.group.visible = !still;
        },
    };
};

/**
 * Local station of the weather center: a louvered screen on legs for temperature and humidity, and a
 * mast with UVA and UVB sensors, an air-quality sensor (CO₂, PM2.5, PM10) with its fan, the logger
 * cabinet, a solar panel and the antenna that sends the readings.
 */
export const buildLocalStation = (m) => {
    const group = new THREE.Group();
    group.add(box(2, 0.08, 1.3, m.concrete, 0, 0.04, 0));

    // Louvered screen (abrigo meteorológico).
    const screen = new THREE.Group();
    screen.position.set(-0.5, 0.08, 0.08);
    [[-0.24, -0.18], [0.24, -0.18], [-0.24, 0.18], [0.24, 0.18]].forEach(([x, z]) => screen.add(box(0.045, 1, 0.045, m.white, x, 0.5, z)));
    screen.add(box(0.5, 0.03, 0.03, m.white, 0, 0.42, 0.18), box(0.5, 0.03, 0.03, m.white, 0, 0.42, -0.18));
    screen.add(box(0.62, 0.03, 0.46, m.white, 0, 1.015, 0));
    [[-0.29, -0.21], [0.29, -0.21], [-0.29, 0.21], [0.29, 0.21]].forEach(([x, z]) => screen.add(box(0.035, 0.5, 0.035, m.white, x, 1.25, z)));
    const frontSlat = new THREE.BoxGeometry(0.56, 0.01, 0.07);
    const sideSlat = new THREE.BoxGeometry(0.07, 0.01, 0.4);
    for (let index = 0; index < 9; index++) {
        const y = 1.06 + index * 0.052;
        [1, -1].forEach((side) => {
            const front = mesh(frontSlat, m.white);
            front.position.set(0, y, side * 0.215);
            front.rotation.x = side * 40 * DEG;
            const flank = mesh(sideSlat, m.white);
            flank.position.set(side * 0.295, y, 0);
            flank.rotation.z = -side * 40 * DEG;
            screen.add(front, flank);
        });
    }
    const roof = box(0.78, 0.03, 0.62, m.white, 0, 1.6, 0);
    roof.rotation.x = -5 * DEG;
    screen.add(box(0.7, 0.03, 0.54, m.white, 0, 1.53, 0), roof);
    group.add(screen);

    // Mast with the sensors.
    const mast = new THREE.Group();
    mast.position.set(0.55, 0.08, -0.15);
    mast.add(box(0.26, 0.03, 0.26, m.metal, 0, 0.015, 0), post(0.036, 0.04, 2.55, m.metal, 0, 0.03, 0));
    mast.add(box(0.8, 0.035, 0.035, m.metal, 0, 2.42, 0));
    const domes = [-0.36, 0.36].map((x) => {
        const dome = mesh(new THREE.SphereGeometry(0.048, 16, 10, 0, Math.PI * 2, 0, Math.PI / 2), m.dome, { cast: false });
        dome.position.set(x, 2.5, 0);
        mast.add(post(0.06, 0.06, 0.06, m.white, x, 2.44, 0, 16), dome);

        return dome;
    });
    mast.add(post(0.008, 0.008, 0.38, m.metal, 0, 2.58, 0, 6), mesh(new THREE.SphereGeometry(0.022, 10, 8), m.metal).translateY(2.97));

    const panel = solarPanel(0.46, 0.32, m);
    panel.position.set(0, 1.95, 0.22);
    panel.rotation.x = 30 * DEG;
    mast.add(box(0.05, 0.05, 0.2, m.metal, 0, 1.92, 0.1), panel);

    // Logger cabinet with its LED, and the air-quality sensor with its fan.
    mast.add(box(0.32, 0.4, 0.17, m.cabinet, 0, 1.25, 0.12), box(0.004, 0.34, 0.004, m.dark, 0.03, 1.25, 0.207));
    const { led, light } = statusLed();
    led.position.set(0.11, 1.4, 0.21);
    mast.add(led);
    mast.add(box(0.27, 0.2, 0.15, m.white, 0, 0.82, 0.11));
    [0.77, 0.81, 0.85, 0.89].forEach((y) => mast.add(box(0.09, 0.009, 0.004, m.dark, -0.06, y, 0.186)));
    const fan = new THREE.Group();
    fan.position.set(0.06, 0.82, 0.188);
    const housing = mesh(new THREE.CylinderGeometry(0.056, 0.056, 0.008, 20), m.dark, { cast: false });
    housing.rotation.x = Math.PI / 2;
    const blades = new THREE.Group();
    blades.position.z = 0.006;
    for (let index = 0; index < 3; index++) {
        const blade = box(0.046, 0.014, 0.004, m.metal, 0, 0, 0);
        blade.geometry.translate(0.025, 0, 0);
        blade.rotation.z = (index * 2 * Math.PI) / 3;
        blades.add(blade);
    }
    fan.add(housing, blades);
    mast.add(fan);
    group.add(mast);

    // Dust the air sensor counts: specks that drift up around it.
    const dust = new THREE.Group();
    const speck = new THREE.SphereGeometry(0.013, 6, 4);
    const specks = Array.from({ length: 9 }, (_, index) => {
        const object = mesh(speck, m.dust, { cast: false, receive: false });
        dust.add(object);

        return { object, phase: index / 9, angle: index * 2.4 };
    });
    dust.position.set(mast.position.x, 0, mast.position.z + 0.1);
    group.add(dust);

    const signal = signalRings({ period: 4.2 });
    signal.group.position.set(mast.position.x, 3.05, mast.position.z);
    group.add(signal.group);

    let time = 0;

    return {
        group,
        onGround: true,
        autoRotate: true,
        frame: { target: new THREE.Vector3(0.05, 1.12, 0), radius: 2.3 },
        update(dt, still = false) {
            time += dt;
            blades.rotation.z -= 9 * dt;
            // The UV sensors glow violet, softly pulsing while they measure.
            m.dome.emissiveIntensity = still ? 0.7 : 0.45 + 0.55 * Math.sin(time * 2.2) ** 2;
            domes.forEach((dome, index) => dome.scale.setScalar(1 + (still ? 0 : 0.04 * Math.sin(time * 2.2 + index))));
            specks.forEach(({ object, phase, angle }) => {
                const life = (time * 0.12 + phase) % 1;
                const turn = angle + time * 0.5;
                object.position.set(Math.cos(turn) * 0.28, 0.55 + life * 0.75, Math.sin(turn) * 0.2);
                object.scale.setScalar(Math.max(0.001, Math.sin(life * Math.PI)));
            });
            dust.visible = !still;
            // The logger blinks once a second and stays on while it sends.
            const sending = !still && signal.update(time);
            light(sending || (!still && time % 1 < 0.12));
            signal.group.visible = !still;
        },
    };
};
