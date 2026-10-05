import * as THREE from 'three';
import { clamp, lerp, smoothstep } from './simulation.js';
import { cloudPuff, seeded, glow } from './textures.js';

/**
 * Everything that changes with the time of day and the weather: the sky and its stars, the clouds, the
 * fog, and the lights (sun, moon, the soft light of the sky and the one inside the house). It follows the
 * readout of the simulation; it never decides what happens.
 */

const color = (hex) => new THREE.Color(hex);

/** The sky for the sun at a height (its sine): night, dusk, sunrise or sunset, golden hour and day. */
const SKY_KEYS = [
    { y: -0.3, top: color('#03060d'), mid: color('#070d1a'), horizon: color('#101a2c') },
    { y: -0.1, top: color('#0a1229'), mid: color('#1c2347'), horizon: color('#4a3a55') },
    { y: -0.02, top: color('#1c3263'), mid: color('#5b5b86'), horizon: color('#d2865a') },
    { y: 0.06, top: color('#2f5ea3'), mid: color('#8d9fc0'), horizon: color('#f0b27a') },
    { y: 0.25, top: color('#3a76bb'), mid: color('#78a9d9'), horizon: color('#ecd9b8') },
    { y: 0.6, top: color('#3f7fc0'), mid: color('#79afde'), horizon: color('#e6dfcf') },
];
const OVERCAST = { top: color('#8794a3'), mid: color('#aab4be'), horizon: color('#d6d6d0') };

const LOW_SUN = color('#ff8a3d');
const HIGH_SUN = color('#fff0d2');

const SKY_VERTEX = /* glsl */ `
    varying vec3 vDirection;
    void main() {
        vDirection = normalize(position);
        gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
    }
`;

/** A vertical gradient: horizon, middle and top. No tone mapping, so the colors are the ones asked for. */
const SKY_FRAGMENT = /* glsl */ `
    uniform vec3 uTop;
    uniform vec3 uMid;
    uniform vec3 uHorizon;
    varying vec3 vDirection;
    void main() {
        float h = clamp(vDirection.y, 0.0, 1.0);
        vec3 sky = mix(uHorizon, uMid, smoothstep(0.0, 0.16, h));
        sky = mix(sky, uTop, smoothstep(0.1, 0.7, h));
        gl_FragColor = vec4(sky, 1.0);
        #include <colorspace_fragment>
    }
`;

const sampleSky = (y, out) => {
    const last = SKY_KEYS.length - 1;
    if (y <= SKY_KEYS[0].y || y >= SKY_KEYS[last].y) {
        const key = y <= SKY_KEYS[0].y ? SKY_KEYS[0] : SKY_KEYS[last];
        out.top.copy(key.top);
        out.mid.copy(key.mid);
        out.horizon.copy(key.horizon);

        return;
    }
    let index = 0;
    while (SKY_KEYS[index + 1].y < y) {
        index++;
    }
    const from = SKY_KEYS[index];
    const to = SKY_KEYS[index + 1];
    const amount = (y - from.y) / (to.y - from.y);
    out.top.lerpColors(from.top, to.top, amount);
    out.mid.lerpColors(from.mid, to.mid, amount);
    out.horizon.lerpColors(from.horizon, to.horizon, amount);
};

/**
 * @param {{scene: THREE.Scene, renderer: THREE.WebGLRenderer, target: THREE.Vector3, coarsePointer: boolean}} options
 *        `target` is the middle of the house; the renderer is only needed for the exposure, which opens a little at night.
 */
export const createAtmosphere = ({ scene, renderer, target, coarsePointer }) => {
    scene.fog = new THREE.Fog('#e6dfcf', 32, 80);

    // The sun casts the shadows; the moon only lights. The light inside is a soft fill, and the light of the
    // sky comes from above. None of them is ever turned off (their intensity goes to 0): adding or removing
    // a light would make every material rebuild its shader.
    const sun = new THREE.DirectionalLight('#fff0d2', 0);
    sun.target.position.copy(target);
    sun.castShadow = true;
    sun.shadow.mapSize.set(coarsePointer ? 2048 : 4096, coarsePointer ? 2048 : 4096);
    Object.assign(sun.shadow.camera, { left: -10, right: 10, top: 8, bottom: -8, near: 1, far: 40 });
    sun.shadow.radius = 3;
    sun.shadow.camera.updateProjectionMatrix();
    sun.shadow.bias = -0.0004;
    sun.shadow.normalBias = 0.03;
    const moon = new THREE.DirectionalLight('#9db4ff', 0);
    moon.position.set(-7, 16, 9);
    const hemisphere = new THREE.HemisphereLight('#dcebff', '#9a8466', 0.6);
    // A soft daylight fill in each room: the roof slab keeps the sun out.
    const fills = [-2.3, 1.9].map((x) => {
        const fill = new THREE.PointLight('#fff2dc', 0, 9, 1.6);
        fill.position.set(x, 2.6, 1.0);

        return fill;
    });
    scene.add(sun, sun.target, moon, hemisphere, ...fills);

    // The sky: a dome that follows the camera, with stars and clouds on it.
    const sky = new THREE.Group();
    scene.add(sky);
    const uniforms = { uTop: { value: new THREE.Color() }, uMid: { value: new THREE.Color() }, uHorizon: { value: new THREE.Color() } };
    const dome = new THREE.Mesh(
        new THREE.SphereGeometry(120, 32, 16),
        new THREE.ShaderMaterial({ uniforms, vertexShader: SKY_VERTEX, fragmentShader: SKY_FRAGMENT, side: THREE.BackSide, depthWrite: false, fog: false }),
    );
    dome.renderOrder = -2;
    sky.add(dome);

    // Stars: more of them low in the north, where the picture looks.
    const random = seeded(7);
    const count = 2200;
    const positions = new Float32Array(count * 3);
    const colors = new Float32Array(count * 3);
    const tintStar = new THREE.Color();
    for (let index = 0; index < count; index++) {
        const around = (random() * 2 - 1) * 2.3;
        const height = 0.01 + 0.5 * random() ** 1.3;
        const flat = Math.sqrt(1 - height * height);
        positions.set([Math.sin(around) * flat * 112, height * 112, -Math.cos(around) * flat * 112], index * 3);
        tintStar.setHSL(0.58 + 0.1 * random(), 0.3 * random(), 0.4 + 0.6 * random() ** 2);
        colors.set([tintStar.r, tintStar.g, tintStar.b], index * 3);
    }
    const starsGeometry = new THREE.BufferGeometry();
    starsGeometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    starsGeometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
    const stars = new THREE.Points(starsGeometry, new THREE.PointsMaterial({
        map: glow(), size: 2.6, sizeAttenuation: false, vertexColors: true, transparent: true, depthWrite: false, fog: false, opacity: 0,
    }));
    stars.renderOrder = -1;
    stars.frustumCulled = false;
    sky.add(stars);

    // Clouds: soft cumulus on the northern horizon. They show when the sky is cloudy.
    const puffs = [cloudPuff(3), cloudPuff(8), cloudPuff(15)];
    const clouds = Array.from({ length: 9 }, (_, index) => {
        const material = new THREE.SpriteMaterial({ map: puffs[index % puffs.length], transparent: true, depthWrite: false, fog: false, toneMapped: false, opacity: 0 });
        const sprite = new THREE.Sprite(material);
        const size = 26 + random() * 16;
        sprite.scale.set(size, size / 2, 1);
        sprite.userData = { angle: -1.7 + (index / 8) * 3.4 + (random() - 0.5) * 0.2, height: 6 + random() * 9, drift: 0.004 + random() * 0.006, weight: 0.65 + random() * 0.35 };
        sky.add(sprite);

        return sprite;
    });

    const colorSky = { top: new THREE.Color(), mid: new THREE.Color(), horizon: new THREE.Color() };
    const cloudTint = new THREE.Color();
    const direction = new THREE.Vector3();
    const NIGHT_CLOUD = color('#27304a');
    const DAY_CLOUD = color('#ffffff');
    const DUSK_CLOUD = color('#ffc9a0');

    return {
        update(r, camera, dt) {
            const { sun: sunVector, daylight, cloud } = r;

            // Sky and fog.
            sampleSky(sunVector.y, colorSky);
            const overcast = cloud * 0.75 * daylight;
            if (overcast > 0) {
                colorSky.top.lerp(OVERCAST.top, overcast);
                colorSky.mid.lerp(OVERCAST.mid, overcast);
                colorSky.horizon.lerp(OVERCAST.horizon, overcast);
            }
            uniforms.uTop.value.copy(colorSky.top);
            uniforms.uMid.value.copy(colorSky.mid);
            uniforms.uHorizon.value.copy(colorSky.horizon);
            scene.fog.color.copy(colorSky.horizon);
            sky.position.copy(camera.position);

            // Stars show on clear nights.
            stars.material.opacity = clamp((-sunVector.y - 0.03) / 0.12) * (1 - cloud * 0.85);
            stars.visible = stars.material.opacity > 0.01;

            // Clouds drift slowly, and take the light of the hour.
            cloudTint.copy(NIGHT_CLOUD).lerp(DAY_CLOUD, daylight).lerp(DUSK_CLOUD, (1 - smoothstep(0.04, 0.4, sunVector.y)) * daylight * 0.8);
            clouds.forEach((sprite) => {
                const { angle, height, drift, weight } = sprite.userData;
                sprite.userData.angle = angle + dt * drift > 1.8 ? -1.8 : angle + dt * drift;
                sprite.position.set(Math.sin(angle) * 85, height, -Math.cos(angle) * 85);
                sprite.material.opacity = cloud * weight * 0.92;
                sprite.material.color.copy(cloudTint);
                sprite.visible = sprite.material.opacity > 0.01;
            });

            // Lights.
            const sunStrength = smoothstep(-0.02, 0.18, sunVector.y) * (1 - 0.72 * cloud);
            direction.set(sunVector.x, sunVector.y, sunVector.z);
            sun.position.copy(target).addScaledVector(direction, 20);
            sun.intensity = 2.9 * sunStrength;
            sun.color.copy(LOW_SUN).lerp(HIGH_SUN, smoothstep(0.02, 0.35, sunVector.y));
            moon.intensity = 0.34 * (1 - daylight);
            hemisphere.intensity = lerp(0.11, 0.62, daylight) + 0.12 * cloud * daylight;
            hemisphere.color.copy(colorSky.mid).lerp(DAY_CLOUD, 0.35);
            fills.forEach((fill) => {
                fill.intensity = 9 * daylight;
            });
            scene.environmentIntensity = lerp(0.07, 0.5, daylight);
            renderer.toneMappingExposure = lerp(1.25, 0.88, daylight);
        },
    };
};
