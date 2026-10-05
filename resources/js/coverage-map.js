/**
 * Coverage map of the installer form (ADR-0022): the municipalities of La Guajira, where a click
 * covers or uncovers one. The checkboxes are the real field; the map only clicks them, so the form
 * works the same without this file.
 *
 * It draws the real map, the same street tiles as the project form, with the municipalities over it.
 * Each shape also carries its own name, because the city label a tile prints sits inside a
 * municipality far larger than the city: the municipality of Riohacha is a third of the coast.
 *
 * The outlines are the simplified ones (26 KB): scripts/maps/simplify-geojson.py makes them from the
 * IGAC file, whose 4 MB of detail freeze the tab every time the map is restyled.
 *
 * Leaflet comes from the CDN on demand, like the map of the project form: it is not in the bundle.
 */

const GEOJSON_URL = '/maps/la_guajira_municipios_simple.geojson';
const LEAFLET_JS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
const TILES = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';

// The same palette as the map of the project form: there, gold is a municipality and burnt orange the
// one chosen; here, the one covered.
const COVERED = { color: '#a85b1e', fillColor: '#c87427', fillOpacity: 0.56, weight: 3 };
const UNCOVERED = { color: '#7a6653', fillColor: '#e1a751', fillOpacity: 0.28, weight: 1 };

/** Share of the department a municipality must span, on both axes, to carry its name always. */
const ROOM_FOR_A_NAME = 0.17;

const loadLeaflet = () => new Promise((resolve, reject) => {
    if (window.L) {
        resolve(window.L);

        return;
    }

    const script = document.createElement('script');
    script.src = LEAFLET_JS;
    script.onload = () => resolve(window.L);
    script.onerror = () => reject(new Error('Leaflet no se pudo cargar'));
    document.head.appendChild(script);
});

const findProperty = (properties, keys) => {
    for (const key of keys) {
        const value = properties?.[key];

        if (value !== undefined && value !== null && String(value).trim() !== '') {
            return String(value).trim();
        }
    }

    return '';
};

const nameOf = (feature) => findProperty(feature?.properties, ['NOMBRE_MPI', 'MPIO_CNMBR', 'MUNICIPIO', 'name', 'nombre', 'mpio_cnmbr']);

const daneCodeOf = (feature) => findProperty(feature?.properties, ['MPIO_CDPMP', 'COD_DANE', 'DANE', 'DIVIPOLA', 'mpio_cdpmp', 'divipola'])
    .replace(/\.0$/, '');

/** "San Juan del César" and "SAN JUAN DEL CESAR" are the same municipality. */
const plain = (text) => text.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

/** Nothing is fetched until the map is about to be seen: the form opens on its first fields. */
const whenVisible = (element) => new Promise((resolve) => {
    if (!('IntersectionObserver' in window)) {
        resolve();

        return;
    }

    const observer = new IntersectionObserver(([entry]) => {
        if (entry.isIntersecting) {
            observer.disconnect();
            resolve();
        }
    }, { rootMargin: '300px' });

    observer.observe(element);
});

/**
 * @param {HTMLElement} root The [data-coverage-map] block: the map, its note and the checkboxes.
 */
export const mountCoverageMap = async (root) => {
    const canvas = root.querySelector('[data-coverage-canvas]');
    const note = root.querySelector('[data-coverage-note]');
    const boxes = [...root.querySelectorAll('input[type="checkbox"][data-dane-code]')];

    if (!canvas || boxes.length === 0) {
        return;
    }

    const boxFor = (feature) => {
        const code = daneCodeOf(feature);
        const name = plain(nameOf(feature));

        return boxes.find((box) => (code && box.dataset.daneCode === code) || plain(box.dataset.name ?? '') === name) ?? null;
    };

    try {
        await whenVisible(canvas);

        const L = await loadLeaflet();
        const response = await fetch(GEOJSON_URL);

        if (!response.ok) {
            throw new Error(`GeoJSON HTTP ${response.status}`);
        }

        // SVG, not canvas: fifteen simplified shapes are few enough that the browser hit-tests and
        // repaints them on its own, which is what makes the hover feel immediate.
        const map = L.map(canvas, { scrollWheelZoom: false, zoomSnap: 0.25 });

        L.tileLayer(TILES, {
            maxZoom: 12,
            attribution: '&copy; OpenStreetMap',
            // Tiles only load once panning stops, and a ring of them is kept: fewer requests while
            // the cursor runs over the municipalities.
            updateWhenIdle: true,
            keepBuffer: 4,
        }).addTo(map);

        const layers = new Map();

        /** Only what changed is restyled; repainting all fifteen on every click is what felt slow. */
        const paint = (only = null) => (only ? [only] : [...layers.keys()]).forEach((layer) => {
            layer.setStyle(layers.get(layer).checked ? COVERED : UNCOVERED);
        });

        const labelled = [];
        const municipalities = L.geoJSON(await response.json(), {
            style: UNCOVERED,
            onEachFeature: (feature, layer) => {
                const box = boxFor(feature);

                if (!box) {
                    // A shape with no municipality in the app: drawn, but it cannot be chosen.
                    return;
                }

                layers.set(layer, box);
                labelled.push(layer);
                layer.on('click', () => {
                    box.checked = !box.checked;
                    // Bubbles to the fieldset, which keeps the counter, and back here as a repaint.
                    box.dispatchEvent(new Event('change', { bubbles: true }));
                });
            },
        }).addTo(map);

        map.fitBounds(municipalities.getBounds(), { padding: [10, 10] });

        // The seven municipalities of the south are small and packed together: their names on top of
        // each other read worse than no name. Only a shape with room carries its name for good; the
        // rest answer on hover, which is instant now that the browser does the hit-testing.
        const whole = municipalities.getBounds();
        const wide = Math.abs(whole.getEast() - whole.getWest()) * ROOM_FOR_A_NAME;
        const tall = Math.abs(whole.getNorth() - whole.getSouth()) * ROOM_FOR_A_NAME;

        labelled.forEach((layer) => {
            const bounds = layer.getBounds();
            const permanent = Math.abs(bounds.getEast() - bounds.getWest()) > wide
                && Math.abs(bounds.getNorth() - bounds.getSouth()) > tall;

            layer.bindTooltip(layers.get(layer).dataset.name, {
                permanent,
                direction: 'center',
                className: 'solar-coverage-label',
                sticky: !permanent,
            });
        });

        paint();

        // The chips, the map itself and "select all" all change the same checkboxes.
        root.addEventListener('change', (event) => {
            const changed = [...layers].find(([, box]) => box === event.target);

            paint(changed?.[0] ?? null);
        });
        canvas.classList.add('is-ready');
    } catch (error) {
        // The checkboxes are the field, so the form still works: only the map is missing.
        root.classList.add('is-mapless');

        if (note) {
            note.textContent = 'El mapa no cargó; elige los municipios en la lista.';
        }

        console.warn('Coverage map unavailable; the municipality list stays.', error);
    }
};
