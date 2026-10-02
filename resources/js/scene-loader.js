/**
 * Mounts the 3D scenes (ADR-0012, ADR-0018), loading Three.js only where there is one. With WebGL the
 * figure waits for its scene with a loader instead of flashing the flat sketch first (html.solar-can-3d,
 * set in partials/head); if the scene fails or takes too long, the figure shows the sketch (.is-flat)
 * until the scene arrives.
 */

/** On a slow connection the sketch beats an endless loader; the scene still replaces it when it comes. */
const FALLBACK_MS = 8000;

/**
 * @param {string} selector Figures with a scene.
 * @param {() => Promise<(figure: HTMLElement) => {dispose: () => void}>} load Imports the scene and returns its mount.
 * @return {() => void} Call it on every page load and after wire:navigate.
 */
export const createSceneLoader = (selector, load) => {
    const scenes = new Map();

    return () => {
        // After wire:navigate the old page is gone: release its WebGL contexts.
        scenes.forEach((scene, figure) => {
            if (!figure.isConnected) {
                scene?.dispose();
                scenes.delete(figure);
            }
        });

        document.querySelectorAll(selector).forEach((figure) => {
            if (scenes.has(figure)) {
                return;
            }
            scenes.set(figure, null);
            const fallback = window.setTimeout(() => figure.classList.add('is-flat'), FALLBACK_MS);

            load()
                .then((mount) => {
                    if (figure.isConnected && scenes.has(figure)) {
                        scenes.set(figure, mount(figure));
                        figure.classList.remove('is-flat');
                    }
                })
                .catch((error) => {
                    // The entry stays empty, so this page does not retry: the sketch takes its place.
                    figure.classList.add('is-flat');
                    console.warn('3D scene unavailable; the flat sketch stays.', error);
                })
                .finally(() => window.clearTimeout(fallback));
        });
    };
};
