import { createSceneLoader } from '../scene-loader.js';

/**
 * Mounts the 3D data stations (ADR-0018) on the [data-station-scene] figure of the climate data page.
 * Three.js is fetched only there; without WebGL the flat sketches drawn by the server stay.
 */
export const initStationScenes = createSceneLoader('[data-station-scene]', () => import('./scene.js').then(({ mountStationScene }) => mountStationScene));
