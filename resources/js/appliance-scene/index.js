/**
 * The chosen appliance in 3D in the consumption diary (ADR-0019). The diary script writes the appliance
 * and its options in the figure's data-*; only the appliances in MODELS have a model, so only they show
 * the figure (.has-model). Three.js loads the first time one of them is chosen.
 */

/** One entry per appliance key of ApplianceCatalog; they arrive in groups (see ADR-0019). */
const MODELS = {
    // Group 1 · Clima
    air_conditioner: () => import('./air-conditioner.js'),
    // Group 2 · Frío
    fridge: () => import('./fridge.js'),
    freezer: () => import('./freezer.js'),
    beverage_cooler: () => import('./beverage-cooler.js'),
    display_case: () => import('./display-case.js'),
    // Group 3 · Sala y oficina
    tv: () => import('./tv.js'),
    fan: () => import('./fan.js'),
    lighting: () => import('./lighting.js'),
    computer: () => import('./computer.js'),
    router: () => import('./router.js'),
    // Group 4 · Cocina y patio
    washing_machine: () => import('./washing-machine.js'),
    water_pump: () => import('./water-pump.js'),
    microwave: () => import('./microwave.js'),
    blender: () => import('./blender.js'),
    iron: () => import('./iron.js'),
};

const figures = new Map();

export const initApplianceScenes = () => {
    // After wire:navigate the old page is gone: release its WebGL context.
    figures.forEach((entry, figure) => {
        if (!figure.isConnected) {
            entry.observer.disconnect();
            entry.scene?.dispose();
            figures.delete(figure);
        }
    });

    document.querySelectorAll('[data-appliance-scene]').forEach((figure) => {
        if (figures.has(figure)) {
            return;
        }
        const entry = { scene: null, mounting: null, request: 0, observer: null };

        const sync = () => {
            const key = figure.dataset.appliance;
            const loadModel = MODELS[key];
            figure.classList.toggle('has-model', Boolean(loadModel));
            if (!loadModel) {
                return;
            }

            // Only the latest choice counts: chips can change faster than a model loads.
            const request = ++entry.request;
            entry.mounting ??= import('./scene.js').then(({ mountApplianceScene }) => {
                entry.scene = mountApplianceScene(figure);

                return entry.scene;
            });

            Promise.all([entry.mounting, loadModel()])
                .then(([scene, model]) => {
                    if (request === entry.request && figure.isConnected) {
                        scene.show(model, { key, variant: figure.dataset.variant, label: figure.dataset.variantLabel });
                    }
                })
                .catch((error) => {
                    // Without WebGL the sheet stays as it was: the icon and the options.
                    figure.classList.add('is-flat');
                    console.warn('Appliance 3D model unavailable.', error);
                });
        };

        entry.observer = new MutationObserver(sync);
        entry.observer.observe(figure, { attributes: true, attributeFilter: ['data-appliance', 'data-variant'] });
        figures.set(figure, entry);
        sync();
    });
};
