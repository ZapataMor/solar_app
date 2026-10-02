import * as THREE from 'three';
import { cylinder, drift, mesh, room } from './parts.js';

/**
 * Light bulb (ADR-0019) hanging from its cord, seen up close. Each type has its shape and its light:
 * a LED dome gives bright, cool light; the energy-saving spiral takes a moment to light up fully; the
 * incandescent filament glows warm and most of its energy goes away as heat, rising over it.
 */

const BULB_Y = 1.32;
const TYPES = ['led', 'cfl', 'incandescent'];

export const frame = { target: new THREE.Vector3(0, BULB_Y + 0.02, 0.38), radius: 0.2, polar: 84 * Math.PI / 180 };

const typeOf = (variant) => (TYPES.includes(variant) ? variant : 'led');

export const note = (variant) => ({
    led: 'LED: la misma luz con unos 9 W, y casi no calienta.',
    cfl: 'Ahorrador: unos 20 W; tarda un momento en encender del todo.',
    incandescent: 'Incandescente: 60 W, y la mayor parte se va en calor, no en luz.',
}[typeOf(variant)]);

/** A soft halo that always faces the camera. */
const halo = (color, size) => {
    const canvas = document.createElement('canvas');
    canvas.width = 128;
    canvas.height = 128;
    const context = canvas.getContext('2d');
    const gradient = context.createRadialGradient(64, 64, 0, 64, 64, 64);
    gradient.addColorStop(0, 'rgba(255, 255, 255, 0.9)');
    gradient.addColorStop(0.25, 'rgba(255, 255, 255, 0.35)');
    gradient.addColorStop(1, 'rgba(255, 255, 255, 0)');
    context.fillStyle = gradient;
    context.fillRect(0, 0, 128, 128);
    const texture = new THREE.CanvasTexture(canvas);
    const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map: texture, color, transparent: true, blending: THREE.AdditiveBlending, depthWrite: false }));
    sprite.scale.setScalar(size);

    return sprite;
};

/** The E27 screw base, pointing up from the bulb's top. */
const screwBase = (metal) => {
    const base = new THREE.Group();
    base.add(cylinder(0.0135, 0.0135, 0.026, metal, 0, 0.013, 0, 16));
    for (let index = 0; index < 4; index++) {
        const thread = mesh(new THREE.TorusGeometry(0.0138, 0.0012, 6, 20), metal, { cast: false });
        thread.rotation.x = Math.PI / 2;
        thread.position.y = 0.004 + index * 0.006;
        base.add(thread);
    }

    return base;
};

const ledBulb = (m, glow) => {
    const bulb = new THREE.Group();
    const dome = mesh(new THREE.SphereGeometry(0.03, 24, 16), glow);
    dome.position.y = -0.045;
    bulb.add(dome, cylinder(0.022, 0.016, 0.03, m.plastic, 0, -0.012, 0, 20), screwBase(m.metal));

    return bulb;
};

const cflBulb = (m, glow) => {
    const bulb = new THREE.Group();
    const turns = 3.5;
    const points = Array.from({ length: 120 }, (_, index) => {
        const t = index / 119;
        const angle = t * turns * Math.PI * 2;
        const radius = 0.022 - t * 0.004;

        return new THREE.Vector3(Math.cos(angle) * radius, -0.02 - t * 0.075, Math.sin(angle) * radius);
    });
    bulb.add(mesh(new THREE.TubeGeometry(new THREE.CatmullRomCurve3(points), 160, 0.0055, 8, false), glow));
    bulb.add(cylinder(0.02, 0.018, 0.024, m.plastic, 0, -0.008, 0, 20), screwBase(m.metal));

    return bulb;
};

const incandescentBulb = (m, glow) => {
    const bulb = new THREE.Group();
    const glass = new THREE.MeshStandardMaterial({ color: '#fff4e0', roughness: 0.05, transparent: true, opacity: 0.22, depthWrite: false });
    const shell = mesh(new THREE.SphereGeometry(0.03, 24, 16), glass, { cast: false });
    shell.position.y = -0.045;
    bulb.add(shell, cylinder(0.016, 0.02, 0.022, glass, 0, -0.012, 0, 16));
    // The filament between its two support wires.
    const wire = new THREE.MeshStandardMaterial({ color: '#8a8a8a', roughness: 0.4, metalness: 0.8 });
    [-1, 1].forEach((side) => bulb.add(cylinder(0.0007, 0.0007, 0.032, wire, side * 0.007, -0.03, 0, 6)));
    const coil = Array.from({ length: 60 }, (_, index) => {
        const t = index / 59;

        return new THREE.Vector3(-0.009 + t * 0.018, -0.047 + Math.sin(t * Math.PI * 10) * 0.0025, Math.cos(t * Math.PI * 10) * 0.0025);
    });
    bulb.add(mesh(new THREE.TubeGeometry(new THREE.CatmullRomCurve3(coil), 120, 0.0011, 6, false), glow, { cast: false }));
    bulb.add(screwBase(m.metal));

    return bulb;
};

export const build = (m, variant) => {
    const type = typeOf(variant);
    const group = room(m, { height: 1.8, width: 1.6, depth: 0.9, x: 0 });
    const settings = {
        led: { color: '#f2f7ff', light: 1.6, halo: 0.16 },
        cfl: { color: '#eaf4ff', light: 1.2, halo: 0.14 },
        incandescent: { color: '#ffc77a', light: 1.1, halo: 0.13 },
    }[type];

    const glow = new THREE.MeshStandardMaterial({ color: settings.color, emissive: new THREE.Color(settings.color), emissiveIntensity: 1, roughness: 0.4 });
    const pendant = new THREE.Group();
    pendant.position.set(0, BULB_Y, 0.38);
    pendant.add(cylinder(0.002, 0.002, 0.5, m.dark, 0, 0.32, 0, 6));
    pendant.add(cylinder(0.018, 0.021, 0.05, m.dark, 0, 0.055, 0, 16));
    const bulb = { led: ledBulb, cfl: cflBulb, incandescent: incandescentBulb }[type](m, glow);
    pendant.add(bulb);

    const light = new THREE.PointLight(settings.color, settings.light, 2.5, 1.6);
    light.position.y = -0.045;
    const shine = halo(settings.color, settings.halo);
    shine.position.y = -0.045;
    pendant.add(light, shine);
    group.add(pendant);

    // Incandescent: its heat rises over it.
    const heat = type === 'incandescent' ? drift({
        count: 18, length: 0.16, speed: 0.5, size: 0.0035, color: '#ffb36b', opacity: 0.7,
        emit: ([across, depth, spread]) => ({
            origin: new THREE.Vector3((across - 0.5) * 0.04, -0.02, (depth - 0.5) * 0.04),
            direction: new THREE.Vector3((spread - 0.5) * 0.4, 1, 0).normalize(),
        }),
    }) : null;
    if (heat) {
        pendant.add(heat.group);
    }

    let time = 0;

    return {
        group,
        update(dt, still = false) {
            time += dt;
            // The energy-saving bulb warms up for a couple of seconds; the others are on at once.
            let level = 1;
            if (type === 'cfl' && !still) {
                level = 0.45 + 0.55 * Math.min(1, time / 2.2);
            }
            if (type === 'incandescent' && !still) {
                level = 0.96 + 0.04 * Math.sin(time * 9);
            }
            glow.emissiveIntensity = level;
            light.intensity = settings.light * level;
            shine.material.opacity = level;
            heat?.update(still ? 0 : dt, 0.9);
        },
    };
};
