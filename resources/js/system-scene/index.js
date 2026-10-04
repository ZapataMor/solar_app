import { createSceneLoader } from '../scene-loader.js';

/** The system animation of the 3D designer (development only): loads Three.js where there is a [data-system-scene]. */
export const initSystemScenes = createSceneLoader('[data-system-scene]', () => import('./scene.js').then(({ mountSystemScene }) => mountSystemScene));
