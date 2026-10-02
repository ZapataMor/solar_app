import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { buildProperty, cactus, layoutSlots, panelSize, RACK_TILT, rock } from './buildings.js';

/**
 * 3D illustration of "Mi sistema" (ADR-0012): the building rises, the panels are installed one by
 * one and a day of sun goes by. It only draws the numbers the server puts in the figure's data-*
 * attributes; it calculates nothing about the business.
 */

const DEG = Math.PI / 180;
const MAX_SLOTS = 120;
const MAX_GHOSTS = 30;

const BUILD_SECONDS = 0.9;
const DROP_SECONDS = 0.45;
const GHOSTS_SECONDS = 0.5;
const DAY_SECONDS = 12;
const NIGHT_SECONDS = 3;
/** The day starts at mid-morning; with reduced motion the sun stays at about 11 a. m. */
const MORNING_ANGLE = 0.3 * Math.PI;
const REST_ANGLE = 0.42 * Math.PI;
/** How far south the sun's path leans (La Guajira is at about 11° N). */
const SUN_PATH_TILT = 25 * DEG;
const MOON_DIRECTION = new THREE.Vector3(-0.35, 0.8, 0.45).normalize();

const PALETTES = {
    light: {
        ground: '#e6d3ac', groundSide: '#cbb187', wall: '#f5ece0', roof: '#b9643a', trim: '#8f4f2e',
        door: '#7b4b2d', glass: '#8fbfd6', awning: '#c87427', awningLight: '#f6efe2', sign: '#c87427',
        cactus: '#5c8a4b', rock: '#bca37b', frame: '#c9d0d8', slot: '#7a6653', ghost: '#b96755',
        sky: { night: '#25334b', dawn: '#f2c9a0', day: '#d6eaf8', dusk: '#f0b483' },
    },
    dark: {
        ground: '#6e5d44', groundSide: '#51442f', wall: '#ddd2bf', roof: '#a2552f', trim: '#7a4428',
        door: '#5f3b24', glass: '#6f97ab', awning: '#d89b45', awningLight: '#e9dfcd', sign: '#d89b45',
        cactus: '#4f7a40', rock: '#8f7b5c', frame: '#aeb6c0', slot: '#c5b59f', ghost: '#e39178',
        sky: { night: '#0b1422', dawn: '#5d4436', day: '#2d475f', dusk: '#62412f' },
    },
};

const kwhFormat = new Intl.NumberFormat('es-CO', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
const moneyFormat = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 });

const clamp01 = (value) => Math.min(1, Math.max(0, value));
const smoothstep = (from, to, value) => {
    const x = clamp01((value - from) / (to - from));

    return x * x * (3 - 2 * x);
};
const easeOutCubic = (x) => 1 - (1 - x) ** 3;
const easeOutBack = (x) => 1 + 2.4 * (x - 1) ** 3 + 1.4 * (x - 1) ** 2;

const positive = (value) => {
    const number = Number(value);

    return Number.isFinite(number) && number > 0 ? number : 0;
};

const readSceneData = (figure) => ({
    propertyType: figure.dataset.propertyType || 'house',
    panelsInstalled: Math.round(positive(figure.dataset.panelsInstalled)),
    panelsFit: Math.round(positive(figure.dataset.panelsFit)),
    panelsMissing: Math.round(positive(figure.dataset.panelsMissing)),
    roofAreaM2: positive(figure.dataset.roofAreaM2),
    panelAreaM2: positive(figure.dataset.panelAreaM2),
    dailyKwh: positive(figure.dataset.dailyKwh),
});

/** Short, so it fits next to the energy of the day: the page already says it in full. */
const summaryText = ({ panelsInstalled: installed, panelsFit: fit, panelsMissing: missing }) => {
    const done = `${installed} ${installed === 1 ? 'instalado' : 'instalados'}`;

    if (installed > 0 && missing > 0) {
        return `${done} · faltan ${missing}`;
    }
    if (installed > 0 && fit > installed) {
        return `${done} · caben ${fit - installed} más`;
    }
    if (installed > 0) {
        return `${installed} ${installed === 1 ? 'panel instalado' : 'paneles instalados'}`;
    }
    if (missing > 0) {
        return `No cabe ninguno · faltan ${missing}`;
    }

    return fit > 0 ? `Caben ${fit} · agrega tus equipos` : '';
};

/** "9:30 a. m." for a sun angle (0 = 6 a. m., π = 6 p. m.), in quarters of an hour. */
const clockText = (angle) => {
    const minutes = Math.round(((6 + (12 * angle) / Math.PI) * 60) / 15) * 15;
    const hours = Math.floor(minutes / 60) % 24;

    return `${((hours + 11) % 12) + 1}:${String(minutes % 60).padStart(2, '0')} ${hours < 12 ? 'a. m.' : 'p. m.'}`;
};

/** Solar cells drawn on the panel's face: 6 × 10 cells, rotated for panels lying in landscape. */
const cellTexture = (landscape) => {
    const canvas = document.createElement('canvas');
    canvas.width = 96;
    canvas.height = 160;
    const context = canvas.getContext('2d');
    const gradient = context.createLinearGradient(0, 0, 96, 160);
    gradient.addColorStop(0, '#2f5a8e');
    gradient.addColorStop(1, '#19325a');
    context.fillStyle = gradient;
    context.fillRect(0, 0, 96, 160);
    context.strokeStyle = 'rgba(214, 228, 245, 0.5)';
    context.lineWidth = 2;
    for (let x = 0; x <= 96; x += 16) {
        context.beginPath();
        context.moveTo(x, 0);
        context.lineTo(x, 160);
        context.stroke();
    }
    for (let y = 0; y <= 160; y += 16) {
        context.beginPath();
        context.moveTo(0, y);
        context.lineTo(96, y);
        context.stroke();
    }

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.anisotropy = 4;
    if (landscape) {
        texture.center.set(0.5, 0.5);
        texture.rotation = Math.PI / 2;
    }

    return texture;
};

const createMaterials = () => {
    const standard = (options = {}) => new THREE.MeshStandardMaterial({ flatShading: true, roughness: 0.85, metalness: 0, ...options });
    const cells = (landscape) => new THREE.MeshStandardMaterial({
        map: cellTexture(landscape), roughness: 0.32, metalness: 0.25, emissive: new THREE.Color('#7fb2ff'), emissiveIntensity: 0,
    });

    return {
        ground: standard(), groundSide: standard(), wall: standard(), roof: standard(), trim: standard(), door: standard(),
        glass: standard({ flatShading: false, roughness: 0.25, metalness: 0.1, emissive: new THREE.Color('#ffcf7a'), emissiveIntensity: 0 }),
        awning: standard(), awningLight: standard(), sign: standard(), cactus: standard(), rock: standard(),
        frame: standard({ flatShading: false, roughness: 0.4, metalness: 0.6 }),
        cells: cells(false),
        cellsLandscape: cells(true),
        slot: new THREE.LineBasicMaterial({ transparent: true, opacity: 0.7 }),
        ghost: new THREE.MeshBasicMaterial({ transparent: true, opacity: 0, depthWrite: false }),
        ghostEdge: new THREE.LineDashedMaterial({ dashSize: 0.22, gapSize: 0.14, transparent: true, opacity: 0 }),
    };
};

const paletteFor = (dark) => {
    const palette = PALETTES[dark ? 'dark' : 'light'];

    return {
        ...palette,
        ghostEdge: palette.ghost,
        sky: Object.fromEntries(Object.entries(palette.sky).map(([key, color]) => [key, new THREE.Color(color)])),
    };
};

/**
 * @param {HTMLElement} figure The [data-solar-scene] figure (see solar-projects/system.blade.php).
 * @return {{replay: () => void, dispose: () => void}}
 */
export const mountSolarScene = (figure) => {
    const stage = figure.querySelector('[data-solar-scene-stage]');
    const placeholder = figure.querySelector('.solar-scene__placeholder');

    if (!stage) {
        throw new Error('The solar scene needs its [data-solar-scene-stage].');
    }

    // First, so a browser without WebGL throws here and keeps the sketch untouched.
    const coarsePointer = window.matchMedia('(pointer: coarse)').matches;
    const renderer = new THREE.WebGLRenderer({ antialias: true, powerPreference: 'low-power' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, coarsePointer ? 1.5 : 2));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFShadowMap;

    const data = readSceneData(figure);
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const unitRoot = figure.closest('[data-unit-root]');
    const ui = {
        status: figure.querySelector('[data-solar-scene-status]'),
        replay: stage.querySelector('[data-solar-scene-replay]'),
        day: figure.querySelector('[data-solar-scene-day]'),
        sun: figure.querySelector('[data-solar-scene-sun]'),
        energy: figure.querySelector('[data-solar-scene-energy]'),
        clock: figure.querySelector('[data-solar-scene-clock]'),
    };

    const scene = new THREE.Scene();
    const sky = new THREE.Color();
    scene.background = sky;
    const materials = createMaterials();
    let palette = paletteFor(document.documentElement.classList.contains('dark'));

    const applyPalette = () => {
        palette = paletteFor(document.documentElement.classList.contains('dark'));
        Object.entries(materials).forEach(([key, material]) => {
            if (typeof palette[key] === 'string') {
                material.color.set(palette[key]);
            }
        });
    };
    applyPalette();

    // The building, with every spot that fits outlined on its roof.
    const property = buildProperty(data.propertyType, data.roofAreaM2, materials);
    scene.add(property.group);

    const slotCount = Math.min(MAX_SLOTS, Math.max(data.panelsFit, data.panelsInstalled));
    const installedCount = Math.min(slotCount, data.panelsInstalled);
    const layout = layoutSlots(property.roof, slotCount, data.panelAreaM2);
    const outline = new THREE.BufferGeometry().setFromPoints([
        new THREE.Vector3(-layout.width / 2, 0, -layout.depth / 2),
        new THREE.Vector3(layout.width / 2, 0, -layout.depth / 2),
        new THREE.Vector3(layout.width / 2, 0, layout.depth / 2),
        new THREE.Vector3(-layout.width / 2, 0, layout.depth / 2),
    ]);
    layout.slots.forEach((slot) => {
        const spot = new THREE.LineLoop(outline, materials.slot);
        spot.position.set(slot.x, 0.03, slot.z);
        spot.scale.z = Math.cos(layout.rack);
        property.roof.frame.add(spot);
    });

    // The installed panels; on a flat roof each one stands on a rack.
    const panelGeometry = new THREE.BoxGeometry(layout.width, 0.05, layout.depth);
    const cells = layout.landscape ? materials.cellsLandscape : materials.cells;
    const panelMaterials = [materials.frame, materials.frame, cells, materials.frame, materials.frame, materials.frame];
    const rise = (layout.depth / 2) * Math.sin(layout.rack);
    const lift = layout.rack > 0 ? rise + 0.3 : 0.04;
    // Rack legs under the high (north) and low (south) edges.
    const reach = (layout.depth / 2) * Math.cos(layout.rack) - 0.05;
    const legs = layout.rack > 0
        ? [[lift + rise, -reach], [lift - rise, reach]].map(([height, z]) => ({ geometry: new THREE.BoxGeometry(layout.width * 0.8, height, 0.05), y: height / 2, z }))
        : [];

    const units = layout.slots.slice(0, installedCount).map((slot) => {
        const unit = new THREE.Group();
        const panel = new THREE.Mesh(panelGeometry, panelMaterials);
        panel.castShadow = true;
        panel.receiveShadow = true;
        panel.position.y = lift;
        panel.rotation.x = layout.rack;
        unit.add(panel);

        legs.forEach(({ geometry, y, z }) => {
            const leg = new THREE.Mesh(geometry, materials.frame);
            leg.position.set(0, y, z);
            leg.castShadow = true;
            unit.add(leg);
        });

        unit.position.set(slot.x, 0, slot.z);
        unit.visible = false;
        property.roof.frame.add(unit);

        return unit;
    });

    scene.updateMatrixWorld(true);
    const buildingBounds = new THREE.Box3().setFromObject(property.group);

    // The panels that would be needed but do not fit, as outlines on the ground beside the building.
    const ghosts = new THREE.Group();
    const ghostCount = Math.min(MAX_GHOSTS, data.panelsMissing);
    if (ghostCount > 0) {
        const { long, short } = panelSize(data.panelAreaM2);
        const geometry = new THREE.BoxGeometry(long, 0.04, short);
        const edges = new THREE.EdgesGeometry(geometry);
        const cols = Math.min(ghostCount, Math.max(2, Math.ceil(Math.sqrt(ghostCount * 1.5))));
        const rows = Math.ceil(ghostCount / cols);
        const pitch = short * Math.cos(RACK_TILT) + 0.8;
        const startX = buildingBounds.max.x + 1.8 + long / 2;
        const startZ = (buildingBounds.min.z + buildingBounds.max.z) / 2 - ((rows - 1) * pitch) / 2;

        for (let index = 0; index < ghostCount; index++) {
            const ghost = new THREE.Group();
            const line = new THREE.LineSegments(edges, materials.ghostEdge);
            if (index === 0) {
                line.computeLineDistances(); // the dashes; every ghost shares these edges
            }
            ghost.add(new THREE.Mesh(geometry, materials.ghost), line);
            ghost.position.set(startX + (index % cols) * (long + 0.25), 0.45, startZ + Math.floor(index / cols) * pitch);
            ghost.rotation.x = RACK_TILT;
            ghosts.add(ghost);
        }
        ghosts.visible = false;
        scene.add(ghosts);
        scene.updateMatrixWorld(true);
    }

    const content = buildingBounds.clone();
    if (ghostCount > 0) {
        content.union(new THREE.Box3().setFromObject(ghosts));
    }
    const center = content.getCenter(new THREE.Vector3()).setY(0);
    const size = content.getSize(new THREE.Vector3());
    const radius = Math.hypot(size.x, size.z) / 2 + 3;

    // Ground: a round patch of Guajira sand with a couple of cardones and some rocks.
    const ground = new THREE.Mesh(new THREE.CylinderGeometry(radius, radius * 1.03, 0.6, 48), [materials.groundSide, materials.ground, materials.groundSide]);
    ground.position.set(center.x, -0.3, center.z);
    ground.receiveShadow = true;
    scene.add(ground);

    const keepOut = content.clone().expandByScalar(0.9);
    const decorations = [
        [cactus(materials, 3.2), 0.82, 0.78],
        [cactus(materials, 2.3), 1.3, 0.74],
        [rock(materials, 0.55), 0.62, 0.86],
        [rock(materials, 0.4), 1.72, 0.8],
        [rock(materials, 0.3), 0.97, 0.88],
    ].map(([object, turns, distance]) => {
        const holder = new THREE.Group();
        const angle = turns * Math.PI;
        let reach = distance * radius;
        const place = () => holder.position.set(center.x + Math.cos(angle) * reach, 0, center.z + Math.sin(angle) * reach);
        place();
        while (keepOut.containsPoint(holder.position.clone().setY(1)) && reach < radius - 0.8) {
            reach += 0.5;
            place();
        }
        holder.add(object);
        scene.add(holder);

        return holder;
    });

    // Light: the sun (it moves through the day) and the sky.
    const hemisphere = new THREE.HemisphereLight('#fff6e6', '#a08a68', 1.4);
    const sun = new THREE.DirectionalLight('#fff4e2', 2.4);
    sun.castShadow = true;
    sun.shadow.mapSize.set(1024, 1024);
    Object.assign(sun.shadow.camera, { left: -radius, right: radius, top: radius, bottom: -radius, near: 0.5, far: radius * 5 });
    sun.shadow.camera.updateProjectionMatrix();
    sun.shadow.bias = -0.0004;
    sun.shadow.normalBias = 0.03;
    sun.target.position.copy(center);
    scene.add(hemisphere, sun, sun.target);

    // Camera from the south-east, so the roof that faces south is in view.
    const camera = new THREE.PerspectiveCamera(32, 4 / 3, 0.1, radius * 20);
    const target = new THREE.Vector3(center.x, Math.min(size.y, 7) * 0.35, center.z);
    const distance = (Math.max(content.getBoundingSphere(new THREE.Sphere()).radius, radius * 0.72) / Math.sin(16 * DEG)) * 0.92;
    const direction = new THREE.Vector3(Math.sin(38 * DEG) * Math.cos(27 * DEG), Math.sin(27 * DEG), Math.cos(38 * DEG) * Math.cos(27 * DEG));
    camera.position.copy(target).addScaledVector(direction, distance);

    const canvas = renderer.domElement;
    canvas.className = 'solar-scene__canvas';
    canvas.setAttribute('aria-hidden', 'true');

    const controls = new OrbitControls(camera, canvas);
    controls.target.copy(target);
    controls.enablePan = false;
    // Zoom only after the scene is touched, so scrolling the page over it keeps scrolling the page.
    controls.enableZoom = false;
    controls.enableDamping = !reduceMotion;
    controls.dampingFactor = 0.08;
    controls.rotateSpeed = 0.6;
    controls.minDistance = distance * 0.55;
    controls.maxDistance = distance * 1.35;
    controls.minPolarAngle = 0.25;
    controls.maxPolarAngle = 1.36;
    controls.update();
    // One finger scrolls the page vertically and turns the scene horizontally.
    canvas.style.touchAction = 'pan-y';

    // Timeline: building → panels one by one → panels that do not fit → a day of sun, again and again.
    const step = installedCount > 0 ? Math.min(0.22, Math.max(0.05, 2.6 / installedCount)) : 0;
    const panelsStart = BUILD_SECONDS + 0.2;
    const panelsEnd = installedCount > 0 ? panelsStart + (installedCount - 1) * step + DROP_SECONDS : panelsStart;
    const ghostsStart = panelsEnd + 0.15;
    const dayStart = ghostsStart + (ghostCount > 0 ? GHOSTS_SECONDS : 0) + 0.4;
    const finalTime = dayStart;

    // The energy of the day only makes sense with panels; its room is kept while it waits.
    const hasDay = data.dailyKwh > 0 && installedCount > 0;
    if (ui.day) {
        ui.day.hidden = !hasDay;
    }

    const sunDirection = new THREE.Vector3();
    const warmLight = new THREE.Color('#ffb46e');
    const whiteLight = new THREE.Color('#fff4e2');

    const setText = (element, text) => {
        if (element && element.textContent !== text) {
            element.textContent = text;
        }
        if (element) {
            element.hidden = text === '';
        }
    };

    const energyText = (kwh, wholeDay) => {
        const rate = Number(unitRoot?.dataset.rate) || 0;
        const amount = unitRoot?.dataset.unit === 'money' && rate > 0
            ? `$${moneyFormat.format(Math.round((kwh * rate) / 100) * 100)}`
            : `${kwhFormat.format(kwh)} kWh`;

        return `≈ ${amount} ${wholeDay ? 'al día' : 'hoy'}`;
    };

    const lightTheDay = (state) => {
        if (state.day) {
            const height = Math.sin(state.angle);
            // Never quite horizontal: grazing light only draws shadow artifacts.
            const elevation = Math.max(height, Math.sin(8 * DEG));
            sunDirection.set(Math.cos(state.angle), elevation * Math.cos(SUN_PATH_TILT), elevation * Math.sin(SUN_PATH_TILT)).normalize();
            sun.intensity = 0.15 + 2.5 * height ** 0.7;
            sun.color.copy(warmLight).lerp(whiteLight, smoothstep(0, 0.5, height));
            hemisphere.intensity = 0.55 + smoothstep(0, 0.3, height);
            sky.copy(state.angle < Math.PI / 2 ? palette.sky.dawn : palette.sky.dusk).lerp(palette.sky.day, smoothstep(0, 0.35, height));
            materials.glass.emissiveIntensity = 0.9 * (1 - smoothstep(0, 0.18, height));
            // The cells shine brighter the higher the sun: they are producing.
            materials.cells.emissiveIntensity = 0.16 * height * height;
        } else {
            sunDirection.copy(MOON_DIRECTION);
            sun.intensity = 0.15;
            sun.color.set('#a9bce0');
            hemisphere.intensity = 0.55;
            if (state.progress < 0.25) {
                sky.copy(palette.sky.dusk).lerp(palette.sky.night, state.progress / 0.25);
            } else if (state.progress > 0.75) {
                sky.copy(palette.sky.night).lerp(palette.sky.dawn, (state.progress - 0.75) / 0.25);
            } else {
                sky.copy(palette.sky.night);
            }
            materials.glass.emissiveIntensity = 0.9;
            materials.cells.emissiveIntensity = 0;
        }
        materials.cellsLandscape.emissiveIntensity = materials.cells.emissiveIntensity;
        sun.position.copy(center).addScaledVector(sunDirection, radius * 2.5);
    };

    const dayAt = (seconds) => {
        const cycle = DAY_SECONDS + NIGHT_SECONDS;
        const moment = (((seconds + (MORNING_ANGLE / Math.PI) * DAY_SECONDS) % cycle) + cycle) % cycle;

        return moment < DAY_SECONDS
            ? { day: true, angle: (moment / DAY_SECONDS) * Math.PI }
            : { day: false, progress: (moment - DAY_SECONDS) / NIGHT_SECONDS };
    };

    const pose = (time) => {
        const t = reduceMotion ? finalTime : time;

        property.group.scale.set(1, Math.max(0.001, easeOutBack(clamp01(t / BUILD_SECONDS))), 1);
        decorations.forEach((holder, index) => holder.scale.setScalar(Math.max(0.001, easeOutBack(clamp01((t - 0.3 - index * 0.08) / 0.5)))));

        let started = 0;
        units.forEach((unit, index) => {
            const progress = clamp01((t - panelsStart - index * step) / DROP_SECONDS);
            unit.visible = progress > 0;
            unit.position.y = (1 - easeOutCubic(progress)) * 2.4;
            started += progress > 0 ? 1 : 0;
        });

        const ghostsShown = clamp01((t - ghostsStart) / GHOSTS_SECONDS);
        ghosts.visible = ghostsShown > 0;
        materials.ghost.opacity = 0.38 * ghostsShown;
        materials.ghostEdge.opacity = 0.95 * ghostsShown;

        let state = { day: true, angle: MORNING_ANGLE };
        if (reduceMotion) {
            state = { day: true, angle: REST_ANGLE };
        } else if (t >= dayStart) {
            state = dayAt(t - dayStart);
        }
        lightTheDay(state);

        // Overlay texts: install progress, then the summary and the energy of the day.
        setText(ui.status, t < panelsStart ? '' : t < panelsEnd ? `Panel ${Math.max(1, started)} de ${installedCount}` : summaryText(data));

        const showDay = hasDay && (reduceMotion || t >= dayStart);
        ui.day?.classList.toggle('is-waiting', !showDay);
        if (showDay) {
            const wholeDay = reduceMotion;
            const produced = !state.day || wholeDay ? data.dailyKwh : (data.dailyKwh * (1 - Math.cos(state.angle))) / 2;
            setText(ui.energy, energyText(produced, wholeDay));
            setText(ui.clock, wholeDay ? 'Lo que darían tus paneles' : state.day ? clockText(state.angle) : 'Noche');
            ui.day?.toggleAttribute('data-night', !state.day);
            if (ui.sun && state.day) {
                ui.sun.setAttribute('cx', (32 - 28 * Math.cos(state.angle)).toFixed(2));
                ui.sun.setAttribute('cy', (32 - 28 * Math.sin(state.angle)).toFixed(2));
            }
        }
    };

    const render = () => renderer.render(scene, camera);

    // Show the stage instead of the sketch, then size it.
    stage.querySelectorAll('canvas').forEach((stale) => stale.remove());
    stage.prepend(canvas);
    figure.classList.add('is-3d');
    stage.hidden = false;
    if (placeholder) {
        placeholder.hidden = true;
    }

    let elapsed = 0;
    let frame = 0;
    let running = false;
    let onScreen = false;
    let lastTime = 0;

    const tick = (now) => {
        frame = requestAnimationFrame(tick);
        // At most 0.1 s per frame, so coming back to the tab does not skip the animation.
        elapsed += Math.min(0.1, Math.max(0, (now - lastTime) / 1000));
        lastTime = now;
        pose(elapsed);
        controls.update();
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

    const redraw = () => {
        if (!running) {
            pose(elapsed);
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

    // Only animate while it is on screen and the tab is visible.
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

    unitRoot?.addEventListener('unit:changed', redraw);

    const enableZoom = () => {
        controls.enableZoom = true;
    };
    const disableZoom = () => {
        controls.enableZoom = false;
    };
    canvas.addEventListener('pointerdown', enableZoom);
    stage.addEventListener('pointerleave', disableZoom);
    if (reduceMotion) {
        // Without the loop, draw when the user turns the scene.
        controls.addEventListener('change', render);
    }

    const replay = () => {
        elapsed = 0;
        redraw();
        start();
    };
    if (ui.replay) {
        ui.replay.hidden = reduceMotion;
        ui.replay.addEventListener('click', replay);
    }

    resize();
    redraw();

    return {
        replay,
        dispose() {
            stop();
            resizeObserver.disconnect();
            visibility.disconnect();
            themeObserver.disconnect();
            document.removeEventListener('visibilitychange', onTabVisibility);
            unitRoot?.removeEventListener('unit:changed', redraw);
            ui.replay?.removeEventListener('click', replay);
            canvas.removeEventListener('pointerdown', enableZoom);
            stage.removeEventListener('pointerleave', disableZoom);
            controls.dispose();

            const released = new Set();
            scene.traverse((object) => {
                [object.geometry, ...[object.material ?? []].flat()].forEach((resource) => {
                    if (resource && !released.has(resource)) {
                        released.add(resource);
                        resource.map?.dispose();
                        resource.dispose();
                    }
                });
            });
            renderer.dispose();
            renderer.forceContextLoss();
            canvas.remove();

            figure.classList.remove('is-3d');
            stage.hidden = true;
            if (placeholder) {
                placeholder.hidden = false;
            }
        },
    };
};
