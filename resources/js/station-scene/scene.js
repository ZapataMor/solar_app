import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { buildNasaStation } from './earth.js';
import { buildAmbientStation, buildGround, buildLocalStation, cellTexture } from './stations.js';

/**
 * The station behind the selected tab of the climate data page, in 3D (ADR-0018). One canvas for the
 * three: each station is built the first time it is shown, and changing tab shrinks one and grows
 * the next. It draws what the figure's data-* say (selected station, latest wind, NASA point).
 */

const DEG = Math.PI / 180;
const FOV = 30;
const OUT_SECONDS = 0.32;
const IN_DELAY = 0.18;
const IN_SECONDS = 0.62;
const CAMERA_SECONDS = 0.8;
/** One turn in about 50 s (OrbitControls: 2 = 30 s). */
const AUTO_ROTATE_SPEED = 1.2;
const RESUME_ROTATION_MS = 4000;
const GROUND_POLAR = [0.4, 1.42];
const NASA_SWING = 0.55;

const PALETTES = {
    light: {
        ground: '#e6d3ac', groundSide: '#cbb187', cactus: '#5c8a4b', rock: '#bca37b', concrete: '#cfc8bb',
        white: '#f7f5f0', whiteBothSides: '#f7f5f0', metal: '#a7b0ba', dark: '#3a3f47', cup: '#3a3f47', cabinet: '#d9dde2', dust: '#a8987a',
    },
    dark: {
        ground: '#6e5d44', groundSide: '#51442f', cactus: '#4f7a40', rock: '#8f7b5c', concrete: '#8b847a',
        white: '#e4dfd5', whiteBothSides: '#e4dfd5', metal: '#8d97a2', dark: '#22262c', cup: '#2a2f36', cabinet: '#b4bac1', dust: '#c2b08a',
    },
};

const clamp01 = (value) => Math.min(1, Math.max(0, value));
const easeInCubic = (x) => x * x * x;
const easeOutBack = (x) => 1 + 2.4 * (x - 1) ** 3 + 1.4 * (x - 1) ** 2;
const lerp = (from, to, k) => from + (to - from) * k;

const createMaterials = () => {
    const standard = (options = {}) => new THREE.MeshStandardMaterial({ flatShading: true, roughness: 0.8, metalness: 0, ...options });

    return {
        ground: standard(), groundSide: standard(), cactus: standard(), rock: standard(), concrete: standard(),
        white: standard({ roughness: 0.6 }),
        whiteBothSides: standard({ roughness: 0.6, side: THREE.DoubleSide }),
        metal: standard({ flatShading: false, roughness: 0.45, metalness: 0.55 }),
        dark: standard({ roughness: 0.7 }),
        cup: standard({ roughness: 0.6, side: THREE.DoubleSide }),
        cabinet: standard({ roughness: 0.55, metalness: 0.2 }),
        dust: standard({ roughness: 1, transparent: true, opacity: 0.75 }),
        cells: new THREE.MeshStandardMaterial({ map: cellTexture(), roughness: 0.35, metalness: 0.25 }),
        dome: new THREE.MeshStandardMaterial({
            color: '#ece6ff', roughness: 0.15, transparent: true, opacity: 0.8, emissive: new THREE.Color('#8b5cf6'), emissiveIntensity: 0.4,
        }),
    };
};

const number = (value) => (value === undefined || value === '' ? NaN : Number(value));

const readData = (figure) => ({
    station: figure.dataset.station || 'ambient',
    windSpeed: number(figure.dataset.windSpeed),
    windDirection: number(figure.dataset.windDirection),
    latitude: Number.isFinite(number(figure.dataset.latitude)) ? number(figure.dataset.latitude) : 11.5444,
    longitude: Number.isFinite(number(figure.dataset.longitude)) ? number(figure.dataset.longitude) : -72.9072,
});

/**
 * @param {HTMLElement} figure The [data-station-scene] figure (api-data/partials/station-figure.blade.php).
 * @return {{dispose: () => void}}
 */
export const mountStationScene = (figure) => {
    const stage = figure.querySelector('[data-station-scene-stage]');

    if (!stage) {
        throw new Error('The station scene needs its [data-station-scene-stage].');
    }

    // First, so a browser without WebGL throws here and keeps the sketches.
    const coarsePointer = window.matchMedia('(pointer: coarse)').matches;
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
    renderer.setClearColor(0x000000, 0);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, coarsePointer ? 1.5 : 2));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFShadowMap;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let data = readData(figure);

    const scene = new THREE.Scene();
    const materials = createMaterials();
    const applyPalette = () => {
        const palette = PALETTES[document.documentElement.classList.contains('dark') ? 'dark' : 'light'];
        Object.entries(palette).forEach(([key, color]) => materials[key].color.set(color));
    };
    applyPalette();

    const ground = buildGround(materials);
    ground.visible = false;
    scene.add(ground);

    const builders = {
        ambient: () => {
            const station = buildAmbientStation(materials);
            station.setWind(data.windSpeed, data.windDirection);

            return station;
        },
        'weather-station': () => buildLocalStation(materials),
        nasa: () => buildNasaStation(materials, data),
    };
    const built = {};
    const stationFor = (key) => {
        if (!built[key]) {
            built[key] = (builders[key] ?? builders.ambient)();
            built[key].group.visible = false;
            scene.add(built[key].group);
        }

        return built[key];
    };

    // Light: a key light that keeps its place beside the camera (a turntable under studio light),
    // higher over the ground stations and more frontal on the Earth.
    const hemisphere = new THREE.HemisphereLight('#fff6e6', '#9c8a6c', 1.3);
    const key = new THREE.DirectionalLight('#fff3df', 2.3);
    key.castShadow = true;
    key.shadow.mapSize.set(1024, 1024);
    Object.assign(key.shadow.camera, { left: -3, right: 3, top: 3, bottom: -3, near: 0.5, far: 30 });
    key.shadow.camera.updateProjectionMatrix();
    key.shadow.bias = -0.0005;
    key.shadow.normalBias = 0.02;
    scene.add(hemisphere, key, key.target);

    const camera = new THREE.PerspectiveCamera(FOV, 16 / 10, 0.1, 60);
    const canvas = renderer.domElement;
    canvas.className = 'solar-station__canvas';
    canvas.setAttribute('aria-hidden', 'true');

    const controls = new OrbitControls(camera, canvas);
    controls.enablePan = false;
    controls.enableZoom = false;
    controls.enableDamping = !reduceMotion;
    controls.dampingFactor = 0.08;
    controls.rotateSpeed = 0.6;
    controls.autoRotateSpeed = AUTO_ROTATE_SPEED;
    // One finger scrolls the page vertically and turns the station horizontally.
    canvas.style.touchAction = 'pan-y';

    const framing = (station) => ({
        target: station.frame.target.clone(),
        radius: station.frame.radius / Math.sin((FOV / 2) * DEG),
        polar: station.frame.polar ?? 72 * DEG,
    });

    // Where the camera starts: from the south-east, a little above.
    const spherical = new THREE.Spherical();
    const placeCamera = (target, radius, polar, azimuth) => {
        controls.target.copy(target);
        spherical.set(radius, polar, azimuth);
        camera.position.setFromSpherical(spherical).add(target);
        camera.lookAt(target);
    };

    let currentKey = null;
    let leavingKey = null;
    let leavingFrom = 1;
    let enteringFrom = 0;
    let groundFrom = 0;
    let groundTo = 0;
    let lightFrom = 0;
    let lightTo = 0;
    let cameraFrom = null;
    let cameraTo = null;
    let transitionStart = 0;
    let transitioning = false;
    let elapsed = 0;
    let resumeTimer = 0;
    // 0 over the ground stations, 1 on the Earth: how frontal the key light is.
    let frontal = 0;

    const snapshotCamera = () => {
        spherical.setFromVector3(camera.position.clone().sub(controls.target));

        return { target: controls.target.clone(), radius: spherical.radius, polar: spherical.phi, azimuth: spherical.theta };
    };

    const settle = () => {
        transitioning = false;
        if (leavingKey) {
            built[leavingKey].group.visible = false;
            leavingKey = null;
        }
        const station = built[currentKey];
        const { azimuth, polar } = cameraTo;
        controls.minAzimuthAngle = station.onGround ? -Infinity : azimuth - NASA_SWING;
        controls.maxAzimuthAngle = station.onGround ? Infinity : azimuth + NASA_SWING;
        controls.minPolarAngle = station.onGround ? GROUND_POLAR[0] : polar - 0.35;
        controls.maxPolarAngle = station.onGround ? GROUND_POLAR[1] : polar + 0.35;
        controls.autoRotate = station.autoRotate && !reduceMotion;
        controls.enabled = true;
        controls.update();
    };

    /** Shows the station of `stationKey`: the one on screen shrinks and this one grows. */
    const show = (stationKey) => {
        const next = builders[stationKey] ? stationKey : 'ambient';
        if (next === currentKey) {
            return;
        }

        const station = stationFor(next);
        Object.entries(built).forEach(([builtKey, other]) => {
            if (builtKey !== next && builtKey !== currentKey) {
                other.group.visible = false;
            }
        });

        leavingKey = currentKey;
        leavingFrom = leavingKey ? built[leavingKey].group.scale.x : 1;
        enteringFrom = station.group.visible ? station.group.scale.x : 0;
        currentKey = next;
        groundFrom = ground.visible ? ground.scale.x : 0;
        groundTo = station.onGround ? 1 : 0;
        lightFrom = frontal;
        lightTo = station.onGround ? 0 : 1;

        cameraFrom = snapshotCamera();
        const frame = framing(station);
        cameraTo = { target: frame.target, radius: frame.radius, polar: frame.polar, azimuth: cameraFrom.azimuth };
        if (station.face) {
            // The Earth turns so its point faces where the camera will be.
            station.face(new THREE.Vector3().setFromSpherical(new THREE.Spherical(1, cameraTo.polar, cameraTo.azimuth)));
        }

        station.group.visible = true;
        transitionStart = elapsed;
        transitioning = true;
        controls.enabled = false;
        controls.autoRotate = false;
    };

    const poseTransition = () => {
        const t = reduceMotion ? Infinity : elapsed - transitionStart;
        const out = clamp01(t / OUT_SECONDS);
        const grow = clamp01((t - (leavingKey ? IN_DELAY : 0)) / IN_SECONDS);

        if (leavingKey) {
            const leaving = built[leavingKey].group;
            leaving.scale.setScalar(Math.max(0.001, leavingFrom * (1 - easeInCubic(out))));
            leaving.visible = out < 1;
        }
        built[currentKey].group.scale.setScalar(Math.max(0.001, lerp(enteringFrom, 1, easeOutBack(grow))));

        const groundScale = groundTo > groundFrom ? lerp(groundFrom, groundTo, easeOutBack(grow)) : lerp(groundFrom, groundTo, easeInCubic(out));
        ground.visible = groundScale > 0.002;
        ground.scale.setScalar(Math.max(0.001, groundScale));

        const k = THREE.MathUtils.smoothstep(t, 0, CAMERA_SECONDS);
        frontal = lerp(lightFrom, lightTo, k);
        const turn = Math.atan2(Math.sin(cameraTo.azimuth - cameraFrom.azimuth), Math.cos(cameraTo.azimuth - cameraFrom.azimuth));
        placeCamera(
            cameraFrom.target.clone().lerp(cameraTo.target, k),
            lerp(cameraFrom.radius, cameraTo.radius, k),
            lerp(cameraFrom.polar, cameraTo.polar, k),
            cameraFrom.azimuth + turn * k,
        );

        if (t >= Math.max(OUT_SECONDS, IN_DELAY + IN_SECONDS, CAMERA_SECONDS)) {
            settle();
        }
    };

    const placeLight = () => {
        spherical.setFromVector3(camera.position.clone().sub(controls.target));
        const polar = lerp(0.62, 1.12, frontal);
        const light = new THREE.Vector3().setFromSpherical(new THREE.Spherical(12, polar, spherical.theta + lerp(0.8, 0.55, frontal)));
        key.position.copy(controls.target).add(light);
        key.target.position.copy(controls.target);
        hemisphere.intensity = lerp(1.3, 0.75, frontal);
    };

    /** Advances the animation by dt seconds and poses everything; `still` is the reduced-motion pose. */
    const step = (dt) => {
        elapsed += dt;
        if (transitioning) {
            poseTransition();
        } else if (controls.enabled) {
            controls.update(dt);
        }
        Object.values(built).forEach((station) => {
            if (station.group.visible) {
                station.update(dt, reduceMotion);
            }
        });
        placeLight();
    };

    const render = () => renderer.render(scene, camera);

    // The canvas goes in front of the sketches.
    stage.querySelectorAll('canvas').forEach((stale) => stale.remove());
    stage.prepend(canvas);
    stage.hidden = false;
    figure.classList.add('is-3d');

    placeCamera(new THREE.Vector3(0, 1.15, 0), 9, 72 * DEG, 32 * DEG);
    show(data.station);

    let frame = 0;
    let running = false;
    let onScreen = false;
    let lastTime = 0;

    const tick = (now) => {
        frame = requestAnimationFrame(tick);
        // At most 0.1 s per frame, so coming back to the tab does not skip the animation.
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

    // The tab (showApiDataTab) and the sync (new wind) speak through the figure's attributes.
    const dataObserver = new MutationObserver(() => {
        data = readData(figure);
        built.ambient?.setWind(data.windSpeed, data.windDirection);
        show(data.station);
        redraw();
    });
    dataObserver.observe(figure, { attributes: true, attributeFilter: ['data-station', 'data-wind-speed', 'data-wind-direction'] });

    // Dragging pauses the turntable; it resumes a moment after letting go.
    const pauseRotation = () => {
        window.clearTimeout(resumeTimer);
        controls.autoRotate = false;
    };
    const resumeRotation = () => {
        window.clearTimeout(resumeTimer);
        resumeTimer = window.setTimeout(() => {
            controls.autoRotate = !transitioning && Boolean(built[currentKey]?.autoRotate) && !reduceMotion;
        }, RESUME_ROTATION_MS);
    };
    controls.addEventListener('start', pauseRotation);
    controls.addEventListener('end', resumeRotation);
    // Without the loop, draw when the user turns the station (the light follows the camera).
    const renderTurn = () => {
        placeLight();
        render();
    };
    if (reduceMotion) {
        controls.addEventListener('change', renderTurn);
    }

    resize();
    redraw();

    return {
        dispose() {
            stop();
            window.clearTimeout(resumeTimer);
            resizeObserver.disconnect();
            visibility.disconnect();
            themeObserver.disconnect();
            dataObserver.disconnect();
            document.removeEventListener('visibilitychange', onTabVisibility);
            controls.removeEventListener('start', pauseRotation);
            controls.removeEventListener('end', resumeRotation);
            controls.removeEventListener('change', renderTurn);
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
        },
    };
};
