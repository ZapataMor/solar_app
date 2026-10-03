import { createSceneLoader } from '../scene-loader.js';

/**
 * Mounts the 3D data stations (ADR-0018) on the [data-station-scene] figure of the climate data page.
 * Three.js is fetched only there; without WebGL the flat sketches drawn by the server stay.
 */
export const initStationScenes = createSceneLoader('[data-station-scene]', () => import('./scene.js').then(({ mountStationScene }) => mountStationScene));

/**
 * The landing's source cards (ADR-0012): choosing one changes the station of the figure in the same
 * [data-station-picker], the way the tabs do on the climate data page. The scene follows data-station.
 */
document.addEventListener('click', (event) => {
    const choice = event.target.closest?.('[data-station-choice]');
    const picker = choice?.closest('[data-station-picker]');

    if (!picker) {
        return;
    }

    picker.querySelector('[data-station-scene]')?.setAttribute('data-station', choice.dataset.stationChoice);
    picker.querySelectorAll('[data-station-choice]').forEach((other) => other.setAttribute('aria-pressed', String(other === choice)));
});
