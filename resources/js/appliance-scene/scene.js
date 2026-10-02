import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

/**
 * The chosen appliance in 3D, next to its options in the consumption diary (ADR-0019). The scene
 * only frames, lights and animates: each model module builds the appliance from its variant
 * ('12000.inverter'…) and says what to tell about it.
 *
 * A model module (one per appliance, shared pieces in parts.js) exports:
 *   frame: {target: THREE.Vector3, radius: number, polar?: number, azimuth?: number}, or a function of
 *          the variant that returns one; the same for variants that differ in size, so sizes compare;
 *   build(materials, variant): {group, update(dt, still)};
 *   note(variant): one sentence under the figure.
 */

const DEG = Math.PI / 180;
const FOV = 30;
const POP_SECONDS = 0.4;
const AZIMUTH = 28 * DEG;
const POLAR = 78 * DEG;
const SWING = 0.7;

/** Colors that follow the light or dark theme; the models' own colors (screens, bottles…) stay. */
const PALETTES = {
    light: {
        wall: '#efe7da', trim: '#d9ccb6', floor: '#cdbfa6', pad: '#bdb4a5', plastic: '#f8f7f3', casing: '#e3e5e2',
        dark: '#2e333a', metal: '#a7b0ba', insulation: '#f0efea', copper: '#c27a45',
        counter: '#d8d3ca', cabinet: '#ebe5da', wood: '#a97b50', steel: '#cdd2d7', glass: '#d6eef7',
    },
    dark: {
        wall: '#6b655c', trim: '#7d7568', floor: '#544d45', pad: '#6e685f', plastic: '#e8e6e0', casing: '#c6c9ca',
        dark: '#1d2127', metal: '#8d97a2', insulation: '#d9d7d1', copper: '#b06c3b',
        counter: '#928c83', cabinet: '#cdc6b9', wood: '#8b6442', steel: '#b2b8be', glass: '#a9cfe0',
    },
};

const clamp01 = (value) => Math.min(1, Math.max(0, value));
const easeOutBack = (x) => 1 + 2.4 * (x - 1) ** 3 + 1.4 * (x - 1) ** 2;

const createMaterials = () => {
    const standard = (options = {}) => new THREE.MeshStandardMaterial({ roughness: 0.75, metalness: 0, ...options });

    return {
        wall: standard({ roughness: 0.95 }),
        trim: standard(),
        floor: standard({ roughness: 0.9 }),
        pad: standard({ roughness: 0.95, flatShading: true }),
        plastic: standard({ roughness: 0.45 }),
        casing: standard({ roughness: 0.5, metalness: 0.15 }),
        dark: standard({ roughness: 0.6, side: THREE.DoubleSide }),
        metal: standard({ roughness: 0.4, metalness: 0.6 }),
        insulation: standard({ roughness: 0.8 }),
        copper: standard({ roughness: 0.35, metalness: 0.7 }),
        counter: standard({ roughness: 0.55 }),
        cabinet: standard({ roughness: 0.6 }),
        wood: standard({ roughness: 0.7 }),
        steel: standard({ roughness: 0.3, metalness: 0.55 }),
        glass: standard({ roughness: 0.05, metalness: 0.1, transparent: true, opacity: 0.25, depthWrite: false }),
    };
};

/**
 * @param {HTMLElement} figure The [data-appliance-scene] figure of the diary sheet.
 * @return {{show: (model: object, config: {key: string, variant: string, label: string}) => void, dispose: () => void}}
 */
export const mountApplianceScene = (figure) => {
    const stage = figure.querySelector('[data-appliance-scene-stage]');

    if (!stage) {
        throw new Error('The appliance scene needs its [data-appliance-scene-stage].');
    }

    // First, so a browser without WebGL throws here and the sheet stays as it was.
    const coarsePointer = window.matchMedia('(pointer: coarse)').matches;
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
    renderer.setClearColor(0x000000, 0);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, coarsePointer ? 1.5 : 2));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFShadowMap;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const ui = {
        title: figure.querySelector('[data-appliance-model-title]'),
        note: figure.querySelector('[data-appliance-model-note]'),
    };

    const scene = new THREE.Scene();
    const materials = createMaterials();
    const applyPalette = () => {
        const palette = PALETTES[document.documentElement.classList.contains('dark') ? 'dark' : 'light'];
        Object.entries(palette).forEach(([key, color]) => materials[key].color.set(color));
    };
    applyPalette();

    // A key light that keeps its place beside the camera, and a soft fill.
    const hemisphere = new THREE.HemisphereLight('#fff8ee', '#9a8f80', 1.35);
    const key = new THREE.DirectionalLight('#fff4e4', 2.1);
    key.castShadow = true;
    key.shadow.mapSize.set(1024, 1024);
    Object.assign(key.shadow.camera, { left: -2, right: 2, top: 2, bottom: -2, near: 0.5, far: 20 });
    key.shadow.camera.updateProjectionMatrix();
    key.shadow.bias = -0.0005;
    key.shadow.normalBias = 0.02;
    scene.add(hemisphere, key, key.target);

    const camera = new THREE.PerspectiveCamera(FOV, 1, 0.05, 40);
    const canvas = renderer.domElement;
    canvas.className = 'solar-appliance-model__canvas';
    canvas.setAttribute('aria-hidden', 'true');

    const controls = new OrbitControls(camera, canvas);
    controls.enablePan = false;
    controls.enableZoom = false;
    controls.enableDamping = !reduceMotion;
    controls.dampingFactor = 0.08;
    controls.rotateSpeed = 0.55;
    canvas.style.touchAction = 'pan-y';

    const spherical = new THREE.Spherical();
    let framed = null;
    let framedKey = '';
    let current = null;
    let shownAt = 0;
    let elapsed = 0;

    /** Moves the camera only when the frame changes, so a new option keeps the user's turn. */
    const frameFor = (model, variant) => {
        const frame = typeof model.frame === 'function' ? model.frame(variant) : model.frame;
        const polar = frame.polar ?? POLAR;
        const azimuth = frame.azimuth ?? AZIMUTH;
        const signature = [frame.target.toArray().join(), frame.radius, polar, azimuth].join('|');
        framed = frame;
        if (signature === framedKey) {
            return;
        }
        framedKey = signature;

        // It stands against a wall: it turns a little, never around.
        controls.minAzimuthAngle = azimuth - SWING;
        controls.maxAzimuthAngle = azimuth + SWING;
        controls.minPolarAngle = polar - 0.35;
        controls.maxPolarAngle = polar + 0.12;
        controls.target.copy(frame.target);
        camera.position.setFromSpherical(spherical.set(frame.radius / Math.sin((FOV / 2) * DEG), polar, azimuth)).add(frame.target);
        camera.lookAt(frame.target);
        controls.update();
    };

    const placeLight = () => {
        spherical.setFromVector3(camera.position.clone().sub(controls.target));
        key.position.copy(controls.target).add(new THREE.Vector3().setFromSpherical(new THREE.Spherical(8, 0.75, spherical.theta + 0.7)));
        key.target.position.copy(controls.target);
    };

    const release = (object) => {
        const released = new Set();
        object.traverse((child) => {
            [child.geometry, ...[child.material ?? []].flat()].forEach((resource) => {
                // Shared materials stay: they belong to the scene, not to this model.
                if (resource && !released.has(resource) && !Object.values(materials).includes(resource)) {
                    released.add(resource);
                    resource.map?.dispose();
                    resource.dispose();
                }
            });
        });
    };

    const step = (dt) => {
        elapsed += dt;
        if (current) {
            // A small pop around the middle of the frame when the options change.
            const grow = reduceMotion ? 1 : clamp01((elapsed - shownAt) / POP_SECONDS);
            const scale = 0.86 + 0.14 * easeOutBack(grow);
            current.group.scale.setScalar(scale);
            current.group.position.copy(framed.target).multiplyScalar(1 - scale);
            current.update(dt, reduceMotion);
        }
        controls.update(dt);
        placeLight();
    };

    const render = () => renderer.render(scene, camera);

    stage.querySelectorAll('canvas').forEach((stale) => stale.remove());
    stage.prepend(canvas);
    figure.classList.add('is-3d');

    let frame = 0;
    let running = false;
    let onScreen = false;
    let lastTime = 0;

    const tick = (now) => {
        frame = requestAnimationFrame(tick);
        step(Math.min(0.1, Math.max(0, (now - lastTime) / 1000)));
        lastTime = now;
        render();
    };

    const start = () => {
        if (running || reduceMotion || !onScreen || document.hidden || !current) {
            return;
        }
        running = true;
        lastTime = performance.now();
        frame = requestAnimationFrame(tick);
    };

    const stop = () => {
        running = false;
        cancelAnimationFrame(frame);
    };

    const redraw = () => {
        if (!running) {
            step(0);
            render();
        }
    };

    const resize = () => {
        const { width, height } = stage.getBoundingClientRect();
        if (width < 10 || height < 10) {
            return;
        }
        renderer.setSize(width, height, false);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        redraw();
    };

    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(stage);

    // Only while the sheet is open and the model in view (a closed dialog is not on screen).
    const visibility = new IntersectionObserver(([entry]) => {
        onScreen = entry.isIntersecting;
        if (onScreen) {
            start();
        } else {
            stop();
        }
    }, { threshold: 0.05 });
    visibility.observe(stage);

    const onTabVisibility = () => (document.hidden ? stop() : start());
    document.addEventListener('visibilitychange', onTabVisibility);

    const themeObserver = new MutationObserver(() => {
        applyPalette();
        redraw();
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

    const renderTurn = () => {
        placeLight();
        render();
    };
    if (reduceMotion) {
        controls.addEventListener('change', renderTurn);
    }

    return {
        /** Shows `model` as `config.variant` makes it; a new variant replaces the old one with a small pop. */
        show(model, { variant, label }) {
            if (current) {
                scene.remove(current.group);
                release(current.group);
            }
            frameFor(model, variant);
            current = model.build(materials, variant);
            scene.add(current.group);
            shownAt = elapsed;

            if (ui.title) {
                ui.title.textContent = label || '';
            }
            if (ui.note) {
                ui.note.textContent = model.note(variant);
            }

            resize();
            redraw();
            start();
        },
        dispose() {
            stop();
            resizeObserver.disconnect();
            visibility.disconnect();
            themeObserver.disconnect();
            document.removeEventListener('visibilitychange', onTabVisibility);
            controls.removeEventListener('change', renderTurn);
            controls.dispose();
            if (current) {
                release(current.group);
            }
            Object.values(materials).forEach((material) => material.dispose());
            renderer.dispose();
            renderer.forceContextLoss();
            canvas.remove();
            figure.classList.remove('is-3d');
        },
    };
};
