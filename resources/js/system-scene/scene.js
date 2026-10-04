import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';
import { createAtmosphere } from './atmosphere.js';
import { bindControls } from './controls.js';
import { FRAME, buildSystem } from './model.js';
import { createSimulation, DEG } from './simulation.js';
import { concrete, plywood, sand, stucco, wayuuRug, woodPlanks } from './textures.js';

/**
 * Mounts the system animation (see model.js): the renderer, the camera, the pointing at parts and the loop
 * that gives the simulation its time (simulation.js), the sky its hour (atmosphere.js) and the model what
 * to show. The controls of the page (controls.js) talk to the same simulation.
 */

const FOV = 30;

/**
 * The scene has its own sky and ground, so its colors do not depend on the light or dark theme of the page.
 * The walls are colored on purpose (white walls in full sun flatten the picture): a terracotta facade, a sage
 * living room and a blue-gray utility room; the cut faces are charcoal, as in an architectural section.
 */
const PALETTE = {
    ground: '#d6bb8a', cactus: '#4f7a45', cactusRib: '#3d6236', rock: '#a08a68',
    slab: '#b9b2a6', roofTop: '#a59f94', floorConcrete: '#8f8b84', floorWood: '#a8784e',
    facade: '#b8674a', utilityWall: '#7d8e9a', livingWall: '#a3ad89', ceiling: '#e2dacd', parapetIn: '#c7b6a2', coping: '#d8d0c3',
    plinth: '#6b4636', cut: '#2b2f34', trim: '#d9ccb6',
    board: '#c9a77a', rack: '#3a4047', frame: '#3b4046', tank: '#1d2124',
    sofa: '#b9842f', cushion: '#d3a246', cabinet: '#e7dfd2', cabinetDoor: '#f1ebe1', counterTop: '#5a534d', rug: '#ffffff',
    panelFrame: '#cdd3da', casing: '#e9ebe8', plastic: '#f8f7f3', dark: '#2e333a', metal: '#a7b0ba', copper: '#c27a45',
    battery: '#3b5b7a', batteryTop: '#2c3f56', inverter: '#16958a', accent: '#16958a', wood: '#7a4f33',
    steel: '#cdd2d7', glass: '#d6eef7', windowGlass: '#cfe6f2', wire: '#23272d', pole: '#8a6a47',
};

const createMaterials = () => {
    const standard = (options = {}) => new THREE.MeshStandardMaterial({ roughness: 0.75, metalness: 0, ...options });
    const m = Object.fromEntries(Object.entries(PALETTE).map(([key, hex]) => [key, standard({ color: hex })]));
    const textured = (key, map, options = {}) => standard({ color: PALETTE[key], map, bumpMap: map, ...options });
    const plaster = stucco();

    return Object.assign(m, {
        ground: textured('ground', sand(), { roughness: 1, bumpScale: 1.6 }),
        facade: textured('facade', plaster, { roughness: 0.95, bumpScale: 0.8 }),
        utilityWall: textured('utilityWall', plaster, { roughness: 0.95, bumpScale: 0.8 }),
        livingWall: textured('livingWall', plaster, { roughness: 0.95, bumpScale: 0.8 }),
        parapetIn: textured('parapetIn', plaster, { roughness: 0.95, bumpScale: 0.8 }),
        ceiling: textured('ceiling', plaster, { roughness: 0.95, bumpScale: 0.4 }),
        slab: textured('slab', concrete([5, 3]), { roughness: 0.9, bumpScale: 0.6 }),
        roofTop: textured('roofTop', concrete([6, 4]), { roughness: 0.92, bumpScale: 0.6 }),
        floorConcrete: textured('floorConcrete', concrete([3, 3]), { roughness: 0.45, bumpScale: 0.4 }),
        floorWood: textured('floorWood', woodPlanks([2, 3]), { roughness: 0.55, bumpScale: 0.5 }),
        board: textured('board', plywood(), { roughness: 0.8, bumpScale: 0.3 }),
        rug: standard({ color: PALETTE.rug, map: wayuuRug(), roughness: 0.95 }),
        cut: standard({ color: PALETTE.cut, roughness: 0.9 }),
        tank: standard({ color: PALETTE.tank, roughness: 0.45 }),
        sofa: standard({ color: PALETTE.sofa, roughness: 0.95 }),
        cushion: standard({ color: PALETTE.cushion, roughness: 0.95 }),
        counterTop: standard({ color: PALETTE.counterTop, roughness: 0.35, metalness: 0.1 }),
        frame: standard({ color: PALETTE.frame, roughness: 0.4, metalness: 0.6 }),
        rack: standard({ color: PALETTE.rack, roughness: 0.5, metalness: 0.5 }),
        casing: standard({ color: PALETTE.casing, roughness: 0.5, metalness: 0.15 }),
        dark: standard({ color: PALETTE.dark, roughness: 0.6, side: THREE.DoubleSide }),
        metal: standard({ color: PALETTE.metal, roughness: 0.4, metalness: 0.6 }),
        panelFrame: standard({ color: PALETTE.panelFrame, roughness: 0.35, metalness: 0.75 }),
        copper: standard({ color: PALETTE.copper, roughness: 0.35, metalness: 0.7 }),
        steel: standard({ color: PALETTE.steel, roughness: 0.3, metalness: 0.55 }),
        battery: standard({ color: PALETTE.battery, roughness: 0.5 }),
        wire: standard({ color: PALETTE.wire, roughness: 0.55 }),
        glass: standard({ color: PALETTE.glass, roughness: 0.05, metalness: 0.1, transparent: true, opacity: 0.25, depthWrite: false }),
        windowGlass: standard({ color: PALETTE.windowGlass, roughness: 0.05, metalness: 0.2, transparent: true, opacity: 0.18, depthWrite: false }),
    });
};

/**
 * @param {HTMLElement} figure The [data-system-scene] figure: it holds a [data-system-stage], a [data-system-tip]
 *        and the controls (see controls.js).
 * @return {{dispose: () => void}}
 */
export const mountSystemScene = (figure) => {
    const stage = figure.querySelector('[data-system-stage]');
    const tip = figure.querySelector('[data-system-tip]');

    if (!stage) {
        throw new Error('The system scene needs its [data-system-stage].');
    }

    // First, so a browser without WebGL throws here and the page keeps its message.
    const coarsePointer = window.matchMedia('(pointer: coarse)').matches;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
    renderer.setClearColor(0x000000, 0);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, coarsePointer ? 1.5 : 2));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;

    const scene = new THREE.Scene();
    const materials = createMaterials();

    // Light from a room-like environment gives the metals and the glass something to reflect.
    const pmrem = new THREE.PMREMGenerator(renderer);
    const environment = pmrem.fromScene(new RoomEnvironment(), 0.04);
    scene.environment = environment.texture;

    const atmosphere = createAtmosphere({ scene, renderer, target: new THREE.Vector3(0, 1.5, 0), coarsePointer });
    const system = buildSystem(materials);
    scene.add(system.group);

    const camera = new THREE.PerspectiveCamera(FOV, 1, 0.1, 260);
    const canvas = renderer.domElement;
    canvas.className = 'solar-system-scene__canvas';
    canvas.setAttribute('aria-hidden', 'true');

    const controls = new OrbitControls(camera, canvas);
    controls.target.copy(FRAME.target);
    controls.enablePan = false;
    controls.enableZoom = false;
    controls.enableDamping = !reduceMotion;
    controls.dampingFactor = 0.08;
    controls.rotateSpeed = 0.6;
    // The house is open to the south: it can be turned a little, never from behind. It stays low, because a
    // ceiling closes the room from above: the higher the camera, the less of the back wall it shows.
    controls.minAzimuthAngle = -50 * DEG;
    controls.maxAzimuthAngle = 50 * DEG;
    controls.minPolarAngle = 80 * DEG;
    controls.maxPolarAngle = 94 * DEG;
    canvas.style.touchAction = 'pan-y';

    const distance = FRAME.radius / Math.sin((FOV / 2) * DEG);
    camera.position.setFromSpherical(new THREE.Spherical(distance, 85 * DEG, 14 * DEG)).add(FRAME.target);
    camera.lookAt(FRAME.target);
    controls.update();

    // The simulation: it keeps the hour, the batteries and the conditions the user sets.
    const simulation = createSimulation();
    let latest = null;
    let elapsed = 0;
    let frame = 0;
    let running = false;
    let onScreen = false;
    let lastTime = 0;
    let tipTimer = 0;

    // Pointing at a part tells what it is, and what it is doing now.
    const raycaster = new THREE.Raycaster();
    const pointer = new THREE.Vector2();
    let hovered = null;

    const partOf = (object) => {
        for (let node = object; node; node = node.parent) {
            if (system.hoverables.includes(node)) {
                return node;
            }
        }

        return null;
    };

    const writeTip = () => {
        if (!tip || !hovered) {
            return;
        }
        const { title, text, live } = hovered.userData.info;
        tip.querySelector('[data-system-tip-title]').textContent = title;
        tip.querySelector('[data-system-tip-text]').textContent = text;
        const now = tip.querySelector('[data-system-tip-live]');
        now.textContent = latest && live ? live(latest) : '';
        now.hidden = !now.textContent;
    };

    const hideTip = () => {
        hovered = null;
        canvas.style.cursor = '';
        if (tip) {
            tip.hidden = true;
        }
    };

    const point = (event) => {
        const rect = canvas.getBoundingClientRect();
        pointer.set(((event.clientX - rect.left) / rect.width) * 2 - 1, -((event.clientY - rect.top) / rect.height) * 2 + 1);
        raycaster.setFromCamera(pointer, camera);
        const hit = raycaster.intersectObjects(system.hoverables, true).find(({ object }) => !object.isLine);
        hovered = hit ? partOf(hit.object) : null;
        canvas.style.cursor = hovered ? 'help' : '';

        if (!tip) {
            return;
        }
        if (!hovered) {
            hideTip();

            return;
        }
        writeTip();
        tip.hidden = false;
        const bounds = stage.getBoundingClientRect();
        const x = Math.min(event.clientX - bounds.left + 14, bounds.width - tip.offsetWidth - 8);
        const y = Math.min(event.clientY - bounds.top + 14, bounds.height - tip.offsetHeight - 8);
        tip.style.transform = `translate(${Math.max(8, x)}px, ${Math.max(8, y)}px)`;
    };
    canvas.addEventListener('pointermove', point);
    canvas.addEventListener('pointerdown', point);
    canvas.addEventListener('pointerleave', hideTip);

    const ui = bindControls(figure, simulation, {
        reduceMotion,
        // The user did something: show it now when the loop is not running (reduced motion, off screen).
        onChange: () => {
            redraw();
            ui.sync(latest, 1, true);
        },
    });

    const step = (dt) => {
        elapsed += dt;
        latest = simulation.step(dt, elapsed);
        controls.update(dt);
        atmosphere.update(latest, camera, dt);
        system.update(dt, elapsed, latest);
        ui.sync(latest, dt, dt === 0);

        tipTimer -= dt;
        if (dt === 0 || tipTimer <= 0) {
            tipTimer = 0.2;
            writeTip();
        }
    };
    const render = () => renderer.render(scene, camera);

    stage.querySelectorAll('canvas').forEach((stale) => stale.remove());
    stage.prepend(canvas);
    figure.classList.add('is-3d');

    const tick = (now) => {
        frame = requestAnimationFrame(tick);
        step(Math.min(0.1, Math.max(0, (now - lastTime) / 1000)));
        lastTime = now;
        render();
    };
    const start = () => {
        if (running || reduceMotion || !onScreen || document.hidden) {
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
    function redraw() {
        if (!running) {
            step(0);
            render();
        }
    }

    const resize = () => {
        const { width, height } = stage.getBoundingClientRect();
        if (width < 10 || height < 10) {
            return;
        }
        renderer.setSize(width, height, false);
        camera.aspect = width / height;
        // A narrow stage (a phone) zooms out to keep the whole house in view.
        camera.zoom = Math.min(1, camera.aspect / 1.3);
        camera.updateProjectionMatrix();
        redraw();
    };
    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(stage);

    // Only while it is on screen.
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

    const onTurn = () => render();
    if (reduceMotion) {
        controls.addEventListener('change', onTurn);
    }

    // To try it by hand from the console: `figure.systemScene.advance(10)` lets ten seconds go by at once.
    figure.systemScene = {
        simulation,
        advance(seconds) {
            for (let time = 0; time < seconds; time += 0.05) {
                step(Math.min(0.05, seconds - time));
            }
            render();
        },
    };

    step(0);
    resize();

    return {
        dispose() {
            stop();
            ui.dispose();
            delete figure.systemScene;
            resizeObserver.disconnect();
            visibility.disconnect();
            document.removeEventListener('visibilitychange', onTabVisibility);
            canvas.removeEventListener('pointermove', point);
            canvas.removeEventListener('pointerdown', point);
            canvas.removeEventListener('pointerleave', hideTip);
            controls.removeEventListener('change', onTurn);
            controls.dispose();
            scene.traverse((object) => {
                object.geometry?.dispose();
                [object.material ?? []].flat().forEach((material) => {
                    material.map?.dispose();
                    material.bumpMap?.dispose();
                    material.dispose();
                });
            });
            environment.dispose();
            pmrem.dispose();
            renderer.dispose();
            renderer.forceContextLoss();
            canvas.remove();
            figure.classList.remove('is-3d');
        },
    };
};
