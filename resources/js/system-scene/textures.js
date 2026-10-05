import * as THREE from 'three';

/**
 * Procedural textures for the system animation (no image files). Most are drawn in grayscale, so the
 * palette tints them through the material's color; the rug and the solar cells bring their own colors.
 */

/** A small seeded generator, so the textures look the same on every load. */
export const seeded = (seed) => () => {
    seed = (seed + 0x6d2b79f5) | 0;
    let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;

    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
};

const make = (width, height, paint, { repeat = [1, 1], color = true } = {}) => {
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    paint(canvas.getContext('2d'), width, height);
    const texture = new THREE.CanvasTexture(canvas);
    texture.wrapS = THREE.RepeatWrapping;
    texture.wrapT = THREE.RepeatWrapping;
    texture.repeat.set(...repeat);
    texture.anisotropy = 8;
    if (color) {
        texture.colorSpace = THREE.SRGBColorSpace;
    }

    return texture;
};

const gray = (value) => `rgb(${value | 0}, ${value | 0}, ${value | 0})`;

/** Speckle over the whole canvas. */
const speckle = (context, width, height, random, { base, spread, count }) => {
    context.fillStyle = gray(base);
    context.fillRect(0, 0, width, height);
    for (let index = 0; index < count; index++) {
        context.fillStyle = `${gray(base + (random() - 0.5) * 2 * spread)}`;
        context.globalAlpha = 0.35 + random() * 0.5;
        context.fillRect(random() * width, random() * height, 1 + random() * 2, 1 + random() * 2);
    }
    context.globalAlpha = 1;
};

/** Soft stains, so large surfaces do not look flat. */
const blotches = (context, width, height, random, { count, strength }) => {
    for (let index = 0; index < count; index++) {
        const x = random() * width;
        const y = random() * height;
        const radius = (0.08 + random() * 0.22) * width;
        const gradient = context.createRadialGradient(x, y, 0, x, y, radius);
        const shade = random() > 0.5 ? 255 : 0;
        gradient.addColorStop(0, `rgba(${shade}, ${shade}, ${shade}, ${strength * random()})`);
        gradient.addColorStop(1, `rgba(${shade}, ${shade}, ${shade}, 0)`);
        context.fillStyle = gradient;
        context.fillRect(x - radius, y - radius, radius * 2, radius * 2);
    }
};

/** Rendered plaster. */
export const stucco = (repeat = [4, 2]) => make(256, 256, (context, w, h) => {
    const random = seeded(11);
    speckle(context, w, h, random, { base: 232, spread: 22, count: 9000 });
    blotches(context, w, h, random, { count: 26, strength: 0.07 });
}, { repeat });

/** Concrete slab with control joints every half texture. */
export const concrete = (repeat = [6, 4]) => make(256, 256, (context, w, h) => {
    const random = seeded(23);
    speckle(context, w, h, random, { base: 214, spread: 18, count: 7000 });
    blotches(context, w, h, random, { count: 30, strength: 0.09 });
    context.strokeStyle = 'rgba(60, 60, 60, 0.55)';
    context.lineWidth = 2;
    context.strokeRect(1, 1, w - 2, h - 2);
}, { repeat });

/** A floor of wooden planks, staggered, with their grain (grayscale: the material's color tints it). */
export const woodPlanks = (repeat = [2, 3]) => make(512, 512, (context, w, h) => {
    const random = seeded(61);
    const rows = 8;
    const rowHeight = h / rows;
    for (let row = 0; row < rows; row++) {
        const y = row * rowHeight;
        let x = -random() * w * 0.6;
        while (x < w) {
            const length = w * (0.4 + random() * 0.45);
            const tone = 170 + random() * 60;
            const gradient = context.createLinearGradient(0, y, 0, y + rowHeight);
            gradient.addColorStop(0, gray(tone + 8));
            gradient.addColorStop(1, gray(tone - 10));
            context.fillStyle = gradient;
            context.fillRect(x, y, length, rowHeight);
            context.strokeStyle = 'rgba(40, 25, 10, 0.12)';
            context.lineWidth = 1;
            for (let line = 0; line < 7; line++) {
                const grain = y + 3 + random() * (rowHeight - 6);
                context.beginPath();
                context.moveTo(x, grain);
                context.bezierCurveTo(x + length * 0.33, grain + (random() - 0.5) * 5, x + length * 0.66, grain + (random() - 0.5) * 5, x + length, grain + (random() - 0.5) * 3);
                context.stroke();
            }
            context.fillStyle = 'rgba(30, 20, 10, 0.45)';
            context.fillRect(x, y, 2, rowHeight);
            x += length;
        }
        context.fillStyle = 'rgba(30, 20, 10, 0.5)';
        context.fillRect(0, y, w, 2);
    }
}, { repeat });

/** A plywood board, where the equipment is mounted: light wood with long, faint grain. */
export const plywood = () => make(256, 256, (context, w, h) => {
    const random = seeded(67);
    speckle(context, w, h, random, { base: 214, spread: 10, count: 2500 });
    context.lineWidth = 1;
    for (let line = 0; line < 60; line++) {
        const y = random() * h;
        context.strokeStyle = `rgba(90, 60, 30, ${0.05 + random() * 0.1})`;
        context.beginPath();
        context.moveTo(0, y);
        context.bezierCurveTo(w * 0.3, y + (random() - 0.5) * 18, w * 0.7, y + (random() - 0.5) * 18, w, y + (random() - 0.5) * 6);
        context.stroke();
    }
});

/** A woven rug with Wayuu-style figures (diamonds and zigzags): in color, it is not tinted. */
export const wayuuRug = () => make(256, 384, (context, w, h) => {
    const random = seeded(83);
    const inset = 22;
    context.fillStyle = '#e6d8bb';
    context.fillRect(0, 0, w, h);

    // Bands of diamonds…
    [0.25, 0.5, 0.75].forEach((at, index) => {
        const y = h * at;
        const size = 17;
        context.fillStyle = index === 1 ? '#b4532a' : '#2c6e6a';
        context.fillRect(inset, y - size, w - inset * 2, size * 2);
        context.fillStyle = index === 1 ? '#e6d8bb' : '#d3a03a';
        for (let x = inset + size; x <= w - inset - size; x += size * 2) {
            context.beginPath();
            context.moveTo(x, y - size + 4);
            context.lineTo(x + size - 4, y);
            context.lineTo(x, y + size - 4);
            context.lineTo(x - size + 4, y);
            context.closePath();
            context.fill();
        }
    });
    // …zigzags between them…
    context.strokeStyle = '#b4532a';
    context.lineWidth = 3;
    [0.375, 0.625].forEach((at) => {
        const y = h * at;
        context.beginPath();
        for (let x = inset, up = true; x <= w - inset; x += 10, up = !up) {
            context.lineTo(x, y + (up ? -6 : 6));
        }
        context.stroke();
    });
    // …a border, and the weave over everything.
    context.lineWidth = 12;
    context.strokeRect(6, 6, w - 12, h - 12);
    context.strokeStyle = '#2c6e6a';
    context.lineWidth = 3;
    context.strokeRect(16, 16, w - 32, h - 32);
    context.fillStyle = 'rgba(0, 0, 0, 0.05)';
    for (let y = 0; y < h; y += 3) {
        context.fillRect(0, y, w, 1);
    }
    for (let index = 0; index < 2500; index++) {
        context.fillStyle = `rgba(255, 255, 255, ${0.06 * random()})`;
        context.fillRect(random() * w, random() * h, 1, 1);
    }
});

/** Desert sand with the ripples the wind leaves. */
export const sand = (repeat = [26, 26]) => make(512, 512, (context, w, h) => {
    const random = seeded(51);
    speckle(context, w, h, random, { base: 232, spread: 26, count: 22000 });
    blotches(context, w, h, random, { count: 40, strength: 0.1 });
    context.strokeStyle = 'rgba(120, 100, 70, 0.07)';
    context.lineWidth = 3;
    for (let line = -h; line < h * 2; line += 22) {
        context.beginPath();
        context.moveTo(0, line + random() * 6);
        context.bezierCurveTo(w * 0.3, line - 14, w * 0.7, line + 14, w, line + random() * 6);
        context.stroke();
    }
}, { repeat });

/** Solar cells: monocrystalline, with fine busbars; 10 × 6 cells per panel. */
export const solarCells = () => make(640, 384, (context, w, h) => {
    const random = seeded(71);
    const columns = 10;
    const rows = 6;
    const margin = 14;
    context.fillStyle = '#d9dee4';
    context.fillRect(0, 0, w, h);
    const cellWidth = (w - margin * 2) / columns;
    const cellHeight = (h - margin * 2) / rows;

    for (let row = 0; row < rows; row++) {
        for (let column = 0; column < columns; column++) {
            const x = margin + column * cellWidth;
            const y = margin + row * cellHeight;
            const tone = random() * 14;
            const gradient = context.createLinearGradient(x, y, x + cellWidth, y + cellHeight);
            gradient.addColorStop(0, `rgb(${22 + tone}, ${40 + tone}, ${84 + tone})`);
            gradient.addColorStop(1, `rgb(${14 + tone}, ${28 + tone}, ${62 + tone})`);
            context.fillStyle = gradient;
            context.beginPath();
            const cut = cellWidth * 0.1;
            context.moveTo(x + cut + 2, y + 2);
            context.lineTo(x + cellWidth - cut - 2, y + 2);
            context.lineTo(x + cellWidth - 2, y + cut + 2);
            context.lineTo(x + cellWidth - 2, y + cellHeight - cut - 2);
            context.lineTo(x + cellWidth - cut - 2, y + cellHeight - 2);
            context.lineTo(x + cut + 2, y + cellHeight - 2);
            context.lineTo(x + 2, y + cellHeight - cut - 2);
            context.lineTo(x + 2, y + cut + 2);
            context.closePath();
            context.fill();
            // Busbars and fingers.
            context.strokeStyle = 'rgba(200, 214, 232, 0.55)';
            context.lineWidth = 1.2;
            [0.33, 0.66].forEach((at) => {
                context.beginPath();
                context.moveTo(x + cellWidth * at, y + 3);
                context.lineTo(x + cellWidth * at, y + cellHeight - 3);
                context.stroke();
            });
            context.strokeStyle = 'rgba(150, 175, 210, 0.16)';
            context.lineWidth = 0.7;
            for (let finger = 1; finger < 9; finger++) {
                context.beginPath();
                context.moveTo(x + 3, y + (cellHeight / 9) * finger);
                context.lineTo(x + cellWidth - 3, y + (cellHeight / 9) * finger);
                context.stroke();
            }
        }
    }
}, { repeat: [1, 1] });

/** A soft round glow (white, transparent at the edge). */
export const glow = () => make(128, 128, (context, w, h) => {
    const gradient = context.createRadialGradient(w / 2, h / 2, 0, w / 2, h / 2, w / 2);
    gradient.addColorStop(0, 'rgba(255, 255, 255, 1)');
    gradient.addColorStop(0.35, 'rgba(255, 255, 255, 0.35)');
    gradient.addColorStop(1, 'rgba(255, 255, 255, 0)');
    context.fillStyle = gradient;
    context.fillRect(0, 0, w, h);
});

/** A shaft of light seen from the side: soft across, fading along (0 at the top, full near the end). */
export const lightShaft = () => make(64, 256, (context, w, h) => {
    const image = context.createImageData(w, h);
    for (let y = 0; y < h; y++) {
        const along = y / (h - 1);
        // The canvas top is the shaft's far end (the sky).
        const fade = Math.min(1, along / 0.55) ** 1.6 * Math.min(1, (1 - along) / 0.03);
        for (let x = 0; x < w; x++) {
            const across = (x / (w - 1)) * 2 - 1;
            const value = Math.exp(-(across * across) * 3.2) * fade;
            const at = (y * w + x) * 4;
            image.data[at] = 255;
            image.data[at + 1] = 255;
            image.data[at + 2] = 255;
            image.data[at + 3] = Math.round(value * 255);
        }
    }
    context.putImageData(image, 0, 0);
});

/** A cumulus cloud seen from the side: a flat base and a soft, domed top (white, transparent around). */
export const cloudPuff = (seed) => make(512, 256, (context, w, h) => {
    const random = seeded(seed);
    const puffs = 34;
    for (let index = 0; index < puffs; index++) {
        const along = random();
        const dome = Math.sin(along * Math.PI);
        const radius = h * (0.12 + 0.2 * dome * (0.5 + 0.5 * random()));
        const x = w * (0.1 + 0.8 * along);
        const y = h * 0.66 - dome * h * 0.2 * random() - radius * 0.15;
        const gradient = context.createRadialGradient(x, y, 0, x, y, radius);
        gradient.addColorStop(0, 'rgba(255, 255, 255, 0.5)');
        gradient.addColorStop(0.55, 'rgba(255, 255, 255, 0.26)');
        gradient.addColorStop(1, 'rgba(255, 255, 255, 0)');
        context.fillStyle = gradient;
        context.fillRect(x - radius, y - radius, radius * 2, radius * 2);
    }
    // The base is a little darker and fades out sharply, like the underside of a cloud.
    context.globalCompositeOperation = 'destination-out';
    const cut = context.createLinearGradient(0, h * 0.62, 0, h * 0.86);
    cut.addColorStop(0, 'rgba(0, 0, 0, 0)');
    cut.addColorStop(1, 'rgba(0, 0, 0, 1)');
    context.fillStyle = cut;
    context.fillRect(0, h * 0.62, w, h * 0.24);
    context.globalCompositeOperation = 'source-over';
}, { repeat: [1, 1] });
