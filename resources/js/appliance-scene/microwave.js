import * as THREE from 'three';
import { box, canvasPlane, counter, cylinder, mesh, room, rounded } from './parts.js';

/**
 * Microwave (ADR-0019) on the kitchen counter: through the door the plate turns with a cup, the light
 * is on while it heats and the display counts down. It draws a lot, but only for a few minutes.
 */

const WIDTH = 0.48;
const HEIGHT = 0.28;
const DEPTH = 0.37;
const DOOR = WIDTH * 0.72;
/** The heating cycle (s): heating, then a short pause with the door closed. */
const HEAT = 6;
const PAUSE = 1.6;

export const frame = { target: new THREE.Vector3(0.12, 1.02, 0.34), radius: 0.46 };

export const note = () => 'Gasta mucho mientras funciona (1.100 W), pero solo unos minutos al día.';

export const build = (m) => {
    const group = room(m, { height: 1.7 });
    const { group: kitchen, top } = counter(m, { width: 1.4, x: 0.1 });
    group.add(kitchen);
    // Backsplash tiles behind the counter.
    group.add(box(1.44, 0.5, 0.01, new THREE.MeshStandardMaterial({ color: '#d9e4e8', roughness: 0.4 }), 0.1, top + 0.25, 0.005));

    const oven = new THREE.Group();
    oven.position.set(0.1, top, 0.3);
    group.add(oven);

    // The cavity: dark outside, lit inside while it heats.
    const inside = new THREE.MeshStandardMaterial({ color: '#f3e3b5', roughness: 0.7, emissive: new THREE.Color('#ffd27a'), emissiveIntensity: 0.6 });
    const cavityWidth = DOOR - 0.05;
    const cavity = new THREE.Group();
    cavity.position.set(-WIDTH / 2 + DOOR / 2, HEIGHT / 2, 0);
    cavity.add(
        box(cavityWidth, 0.01, DEPTH - 0.06, inside, 0, -HEIGHT / 2 + 0.03, 0),
        box(cavityWidth, 0.01, DEPTH - 0.06, inside, 0, HEIGHT / 2 - 0.03, 0),
        box(0.01, HEIGHT - 0.06, DEPTH - 0.06, inside, -cavityWidth / 2, 0, 0),
        box(0.01, HEIGHT - 0.06, DEPTH - 0.06, inside, cavityWidth / 2, 0, 0),
        box(cavityWidth, HEIGHT - 0.06, 0.01, inside, 0, 0, -DEPTH / 2 + 0.03),
    );
    // The turning plate with a cup on it.
    const plate = new THREE.Group();
    plate.position.y = -HEIGHT / 2 + 0.045;
    plate.add(cylinder(0.13, 0.13, 0.006, new THREE.MeshStandardMaterial({ color: '#e8f2f4', roughness: 0.1, transparent: true, opacity: 0.8 }), 0, 0, 0, 32));
    plate.add(cylinder(0.04, 0.032, 0.075, new THREE.MeshStandardMaterial({ color: '#c8102e', roughness: 0.5 }), 0.06, 0.04, 0, 18));
    const handle = mesh(new THREE.TorusGeometry(0.02, 0.005, 6, 14, Math.PI), new THREE.MeshStandardMaterial({ color: '#c8102e', roughness: 0.5 }));
    handle.rotation.z = -Math.PI / 2;
    handle.position.set(0.1, 0.045, 0);
    plate.add(handle);
    cavity.add(plate);
    oven.add(cavity);

    // The shell around the cavity, open at the door.
    const shell = m.casing;
    oven.add(
        rounded(WIDTH, 0.03, DEPTH, 0.01, shell, 0, HEIGHT - 0.015, 0, 2),
        box(WIDTH, 0.03, DEPTH, shell, 0, 0.015, 0),
        box(0.012, HEIGHT, DEPTH, shell, -WIDTH / 2 + 0.006, HEIGHT / 2, 0),
        box(WIDTH - DOOR, HEIGHT, DEPTH, m.dark, WIDTH / 2 - (WIDTH - DOOR) / 2, HEIGHT / 2, 0),
        box(WIDTH, HEIGHT, 0.012, shell, 0, HEIGHT / 2, -DEPTH / 2 + 0.006),
    );
    [-1, 1].forEach((side) => oven.add(cylinder(0.012, 0.012, 0.012, m.dark, side * (WIDTH / 2 - 0.05), -0.006, DEPTH / 2 - 0.05, 8)));

    // The door: frame and a dark window with its dotted screen.
    const pane = canvasPlane(DOOR - 0.07, HEIGHT - 0.07, (context, w, h) => {
        context.fillStyle = 'rgba(20, 22, 26, 0.55)';
        context.fillRect(0, 0, w, h);
        context.fillStyle = 'rgba(0, 0, 0, 0.35)';
        for (let y = 2; y < h; y += 6) {
            for (let x = 2; x < w; x += 6) {
                context.fillRect(x, y, 2, 2);
            }
        }
    }, { transparent: true });
    pane.plane.position.set(-WIDTH / 2 + DOOR / 2, HEIGHT / 2, DEPTH / 2 + 0.012);
    const doorFrame = new THREE.Group();
    doorFrame.position.set(-WIDTH / 2 + DOOR / 2, HEIGHT / 2, DEPTH / 2 + 0.006);
    doorFrame.add(
        box(DOOR, 0.035, 0.012, m.dark, 0, HEIGHT / 2 - 0.0175, 0),
        box(DOOR, 0.035, 0.012, m.dark, 0, -HEIGHT / 2 + 0.0175, 0),
        box(0.035, HEIGHT - 0.07, 0.012, m.dark, -DOOR / 2 + 0.0175, 0, 0),
        box(0.035, HEIGHT - 0.07, 0.012, m.dark, DOOR / 2 - 0.0175, 0, 0),
    );
    oven.add(doorFrame, pane.plane);

    // Control panel: the countdown and the keypad.
    const panelX = WIDTH / 2 - (WIDTH - DOOR) / 2;
    const display = canvasPlane(0.09, 0.035, (context, w, h, seconds) => {
        context.fillStyle = '#0d1a12';
        context.fillRect(0, 0, w, h);
        context.fillStyle = '#5dff9a';
        context.font = `bold ${Math.round(h * 0.7)}px system-ui, sans-serif`;
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        const left = Math.max(0, Math.ceil(seconds));
        context.fillText(`${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`, w / 2, h / 2 + 2);
    });
    display.plane.position.set(panelX, HEIGHT - 0.06, DEPTH / 2 + 0.002);
    oven.add(display.plane);
    const keys = new THREE.MeshStandardMaterial({ color: '#4b525c', roughness: 0.5 });
    for (let row = 0; row < 4; row++) {
        for (let column = 0; column < 3; column++) {
            oven.add(box(0.022, 0.016, 0.006, keys, panelX - 0.03 + column * 0.03, HEIGHT - 0.11 - row * 0.03, DEPTH / 2 + 0.003));
        }
    }

    let time = 0;
    let sinceRepaint = 1;

    return {
        group,
        update(dt, still = false) {
            time += dt;
            sinceRepaint += dt;
            const moment = time % (HEAT + PAUSE);
            const heating = still || moment < HEAT;
            if (heating && !still) {
                plate.rotation.y += 0.8 * dt;
            }
            inside.emissiveIntensity = heating ? 0.6 : 0.05;
            if (sinceRepaint > 0.25 || still) {
                sinceRepaint = 0;
                // Shown as if it heated for 1:30; the cycle here is shorter.
                display.repaint(heating ? 90 * (1 - moment / HEAT) : 0);
            }
        },
    };
};
