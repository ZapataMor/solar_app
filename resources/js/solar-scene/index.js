import { createSceneLoader } from '../scene-loader.js';

/**
 * Mounts the 3D illustration (ADR-0012) on every [data-solar-scene] figure. Three.js is fetched only on
 * the pages that have one; without WebGL the flat sketch rendered by the server stays.
 */
export const initSolarScenes = createSceneLoader('[data-solar-scene]', () => import('./scene.js').then(({ mountSolarScene }) => mountSolarScene));

/**
 * The landing's chooser (a house, a business, an institution): each button carries the example it draws.
 * It writes the figure's data-* and the scene draws them again, or on mounting if it is not there yet.
 */
document.addEventListener('click', (event) => {
    const choice = event.target.closest?.('[data-scene-property]');
    const figure = choice?.closest('[data-solar-scene]');

    if (!figure) {
        return;
    }

    Object.assign(figure.dataset, {
        propertyType: choice.dataset.sceneProperty,
        panelsInstalled: choice.dataset.scenePanels,
        panelsFit: choice.dataset.scenePanels,
        roofAreaM2: choice.dataset.sceneArea,
    });
    figure.querySelectorAll('[data-scene-property]').forEach((other) => other.setAttribute('aria-pressed', String(other === choice)));
    figure.solarScene?.refresh();
});
