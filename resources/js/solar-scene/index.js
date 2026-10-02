/**
 * Mounts the 3D illustration (ADR-0012) on every [data-solar-scene] figure. Three.js is fetched only on
 * the pages that have one; without WebGL the flat sketch rendered by the server stays.
 */
const scenes = new Map();

export const initSolarScenes = () => {
    // After wire:navigate the old page is gone: release its WebGL contexts.
    scenes.forEach((scene, figure) => {
        if (!figure.isConnected) {
            scene?.dispose();
            scenes.delete(figure);
        }
    });

    document.querySelectorAll('[data-solar-scene]').forEach((figure) => {
        if (scenes.has(figure)) {
            return;
        }
        scenes.set(figure, null);

        import('./scene.js')
            .then(({ mountSolarScene }) => {
                if (figure.isConnected && scenes.has(figure)) {
                    scenes.set(figure, mountSolarScene(figure));
                }
            })
            .catch((error) => {
                // The entry stays empty, so this page does not retry: the sketch is already there.
                console.warn('3D illustration unavailable; the flat sketch stays.', error);
            });
    });
};
