import { createSceneLoader } from '../scene-loader.js';

/**
 * Mounts the 3D illustration (ADR-0012) on every [data-solar-scene] figure. Three.js is fetched only on
 * the pages that have one; without WebGL the flat sketch rendered by the server stays.
 */
export const initSolarScenes = createSceneLoader('[data-solar-scene]', () => import('./scene.js').then(({ mountSolarScene }) => mountSolarScene));
