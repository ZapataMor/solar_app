/**
 * Mounts the 3D data stations (ADR-0018) on the [data-station-scene] figure of the climate data page.
 * Three.js is fetched only there; without WebGL the flat sketches drawn by the server stay.
 */
const scenes = new Map();

export const initStationScenes = () => {
    // After wire:navigate the old page is gone: release its WebGL context.
    scenes.forEach((scene, figure) => {
        if (!figure.isConnected) {
            scene?.dispose();
            scenes.delete(figure);
        }
    });

    document.querySelectorAll('[data-station-scene]').forEach((figure) => {
        if (scenes.has(figure)) {
            return;
        }
        scenes.set(figure, null);

        import('./scene.js')
            .then(({ mountStationScene }) => {
                if (figure.isConnected && scenes.has(figure)) {
                    scenes.set(figure, mountStationScene(figure));
                }
            })
            .catch((error) => {
                // The entry stays empty, so this page does not retry: the sketches are already there.
                console.warn('3D stations unavailable; the flat sketches stay.', error);
            });
    });
};
