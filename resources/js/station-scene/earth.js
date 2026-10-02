import * as THREE from 'three';
import { ACCENT, mesh } from './stations.js';

/**
 * NASA POWER (ADR-0018): a satellite in a near-polar orbit that measures the radiation over the point
 * the app asks NASA for (La Guajira). The Earth is a sphere with a hand-drawn map: coarse outlines,
 * [longitude, latitude], enough to recognize the continents from the side the camera looks at.
 */

const DEG = Math.PI / 180;
const EARTH_RADIUS = 1.2;
const ORBIT_RADIUS = 1.68;
const ORBIT_SECONDS = 10;
/** The satellite starts a little north of the point, so the first pass comes soon. */
const START_PHASE = -0.9;
/** Heading of the ground track at the point: southward, tilted west like a sun-synchronous pass. */
const TRACK_TILT = 20 * DEG;
const PING_SECONDS = 1.4;

const LAND = [
    // The Americas, from Punta Gallinas (La Guajira) westward along the Caribbean, around North
    // America, down the Pacific and back up the Atlantic.
    [[-71.7, 12.4], [-72.2, 12.2], [-72.9, 11.6], [-74.2, 11.3], [-74.9, 11], [-75.5, 10.4], [-75.8, 9.4], [-76.9, 8.5],
        [-77.4, 8.7], [-78.6, 9.4], [-79.9, 9.35], [-81.2, 8.8], [-82.2, 9.3], [-83, 10], [-83.6, 11], [-83.7, 12.5],
        [-83.4, 14], [-83.2, 15], [-84.5, 15.8], [-86, 15.9], [-88, 15.8], [-88.3, 17.5], [-87.8, 18.4], [-87.5, 19.8],
        [-86.8, 21.3], [-88, 21.6], [-90, 21.2], [-90.5, 19.8], [-91.8, 18.6], [-94.4, 18.2], [-96, 19], [-97.3, 21],
        [-97.8, 22.3], [-97.4, 25], [-97.2, 26], [-97.4, 27.8], [-94.8, 29.3], [-92, 29.6], [-89.4, 29], [-88, 30.4],
        [-85.4, 29.7], [-84, 30], [-82.8, 28], [-81.8, 26], [-81, 25.2], [-80.1, 25.8], [-80.6, 28.4], [-81.4, 30.4],
        [-79.9, 32.8], [-75.5, 35.2], [-76, 37], [-74, 40.5], [-70, 41.6], [-70.5, 43.5], [-67, 44.8], [-64, 45.3],
        [-66, 43.8], [-60, 46], [-64.5, 48.8], [-60, 50.2], [-56, 52], [-60, 55.5], [-64.5, 60.3], [-69.5, 58.8],
        [-78, 62.4], [-77.5, 58], [-79, 54], [-82, 52.5], [-87, 55.8], [-93, 58.8], [-94.5, 61], [-90, 64], [-86, 67],
        [-95, 68.5], [-110, 68], [-125, 69.5], [-141, 69.6], [-156.8, 71.3], [-166, 68.9], [-163, 66], [-168, 65.6],
        [-161, 64.5], [-165.5, 62], [-162, 59], [-158, 57.5], [-163, 55], [-156, 56], [-151, 59.2], [-146, 60.8],
        [-140, 59.8], [-136, 58], [-131.5, 54.5], [-127.5, 50.8], [-124.7, 48.4], [-124, 46.2], [-124.5, 42.8],
        [-124.3, 40.4], [-122.5, 37.7], [-120.6, 34.6], [-118.3, 33.8], [-117.1, 32.5], [-116, 30.5], [-114.5, 28],
        [-112, 25.5], [-110, 23], [-110.3, 24.2], [-113.5, 29], [-114.7, 31.7], [-113, 31.3], [-111, 27.9],
        [-109.2, 25.6], [-106.4, 23.2], [-105.3, 21.5], [-105.7, 20.4], [-104.3, 19.1], [-102, 17.9], [-99.9, 16.8],
        [-97, 15.8], [-95.2, 16.2], [-93.5, 15.6], [-92.2, 14.5], [-90.8, 13.9], [-89, 13.4], [-87.6, 13], [-86.8, 12.1],
        [-85.7, 11.1], [-85.8, 10.2], [-84.8, 9.6], [-83.6, 8.5], [-82.9, 8.2], [-81.1, 7.6], [-80, 7.4], [-80.4, 8.3],
        [-79.5, 8.95], [-78.4, 8.4], [-78, 7.3], [-77.4, 6.5], [-77.4, 4], [-78, 2.6], [-78.8, 1.8], [-80, 0.9],
        [-80.5, -0.9], [-80.9, -2.2], [-79.9, -2.6], [-80.3, -3.4], [-81.3, -4.7], [-80.9, -6], [-79.9, -6.9],
        [-78.5, -9], [-77.1, -12], [-76.2, -13.8], [-75, -15.5], [-72, -17], [-70.3, -18.5], [-70.2, -23.6],
        [-70.9, -27], [-71.5, -30], [-71.6, -33], [-72.6, -35.5], [-73.2, -37.2], [-73.7, -40], [-74, -42], [-75, -46],
        [-75.5, -50], [-74, -52.5], [-71, -54], [-67.3, -55.9], [-65.2, -54.8], [-68.3, -52.3], [-69.2, -51.6],
        [-67.7, -49.3], [-65.8, -47.7], [-67.5, -46], [-65.5, -44.9], [-63.6, -42.6], [-64.9, -40.7], [-62.2, -38.8],
        [-57.6, -38.1], [-56.7, -36.4], [-57.4, -35.3], [-58.4, -34.6], [-57.8, -34.4], [-56.2, -34.9], [-54.9, -34.95],
        [-53.4, -33.7], [-50.8, -30.5], [-48.6, -26.5], [-48.5, -25.5], [-46.3, -24], [-44.5, -23.3], [-43.2, -22.95],
        [-42, -22.9], [-41, -21.6], [-40.3, -20.3], [-39.2, -17.7], [-39, -14.8], [-38.5, -13], [-37, -11], [-35.7, -9.6],
        [-34.9, -8], [-35.2, -5.5], [-37.3, -4.7], [-38.5, -3.7], [-41.5, -2.8], [-44.3, -2.5], [-47, -0.6], [-48.5, -1],
        [-50, 0.5], [-50.5, 2], [-51.6, 4.2], [-52.3, 4.9], [-55.2, 5.9], [-57.1, 6], [-58.2, 6.8], [-59.8, 8.3],
        [-60.8, 8.6], [-61.8, 9.5], [-62.3, 10.6], [-63.8, 10.6], [-64.2, 10.45], [-65, 10.1], [-66.2, 10.6], [-67, 10.6],
        [-68, 10.5], [-68.4, 11.2], [-69.6, 11.4], [-69.9, 12.1], [-70.3, 11.7], [-70.5, 11.25], [-71.5, 10.9],
        [-71.6, 11.35], [-71.3, 11.85]],
    // Caribbean: Cuba, Hispaniola, Jamaica, Puerto Rico, Trinidad and Andros.
    [[-84.95, 21.85], [-83, 22.9], [-81.6, 23.1], [-80, 22.95], [-78, 22.3], [-76.5, 21.2], [-75, 20.6], [-74.15, 20.25],
        [-75.5, 19.9], [-77.7, 19.85], [-77.2, 20.6], [-78, 20.7], [-80.4, 22], [-81.8, 22.2], [-83, 22], [-84.4, 21.8]],
    [[-74.45, 18.45], [-73.4, 19.8], [-72.2, 19.75], [-70.7, 19.8], [-69.3, 19.3], [-68.35, 18.6], [-69.9, 18.45],
        [-71.4, 17.6], [-72.6, 18.2]],
    [[-78.35, 18.35], [-77, 18.5], [-76.2, 18], [-77.2, 17.75], [-78, 18]],
    [[-67.25, 18.5], [-65.6, 18.4], [-65.65, 18], [-67.2, 17.95]],
    [[-61.9, 10.8], [-60.9, 10.8], [-61, 10.05], [-61.9, 10.05]],
    [[-78.3, 25.2], [-77.7, 25], [-77.8, 24], [-78.4, 24.3]],
    // Arctic islands of Canada.
    [[-61.3, 66.6], [-64, 63.5], [-65, 62], [-70.5, 62.8], [-77, 65], [-76, 67.5], [-80, 69.5], [-84, 70], [-88, 72.5],
        [-81, 73.8], [-76, 72.7], [-71, 70.6], [-67, 69]],
    [[-118, 71], [-110, 72.8], [-102, 71.5], [-101.5, 69.5], [-106, 69], [-113, 68.5], [-118, 69.5]],
    [[-125, 72], [-120, 74.3], [-116, 73], [-120, 71.5]],
    [[-92, 74.5], [-80.5, 74.5], [-81, 76.6], [-91, 76.3]],
    [[-90, 76.5], [-80, 76.2], [-75, 78.5], [-68, 80.5], [-63, 82.5], [-80, 83], [-92, 81], [-96, 78]],
    // Iceland, Great Britain and Ireland.
    [[-22, 64], [-24, 65.5], [-22, 66.4], [-16, 66.5], [-13.5, 65.2], [-15, 64.3], [-18, 63.4], [-21, 63.8]],
    [[-5.7, 50.05], [-3, 50.6], [1.4, 51.15], [1.75, 52.5], [0.1, 53.5], [-1.5, 55], [-2, 56], [-1.8, 57.5], [-3, 58.6],
        [-5, 58.6], [-5.7, 57], [-5, 55.8], [-3, 54.9], [-3, 53.5], [-4.6, 53.3], [-4.2, 52.2], [-5.3, 51.75], [-3, 51.5],
        [-4.5, 51.2], [-5, 50.4]],
    [[-6.3, 52.2], [-6, 53.3], [-5.6, 54.6], [-7.2, 55.3], [-8.5, 54.3], [-10.2, 53.4], [-10.3, 52], [-8, 51.6]],
    // Africa (Sinai included) and Madagascar.
    [[-5.9, 35.8], [-6.8, 34], [-9.6, 30.4], [-13, 27.9], [-16, 23.7], [-17, 21], [-16, 18.1], [-17.5, 14.7], [-16.7, 12.4],
        [-15, 11], [-13.7, 9.5], [-11.5, 6.9], [-7.5, 4.4], [-4, 5.2], [1.2, 6.1], [3.4, 6.4], [6, 4.3], [8.5, 4.5],
        [9.7, 4], [9.3, 0.5], [9, -1], [11.8, -4.8], [12.3, -6], [13.2, -8.8], [13.4, -12.5], [11.8, -17.2], [14.5, -22.9],
        [15.2, -26.6], [16.5, -28.6], [18.4, -33.9], [20, -34.8], [25.6, -33.9], [31, -29.9], [32.9, -26], [35.5, -24],
        [34.8, -20], [36.9, -17.9], [40.7, -14.5], [40.5, -10.5], [39.3, -6.8], [39.7, -4], [42.5, -0.5], [45.3, 2],
        [48, 5], [51.2, 10.4], [45, 10.4], [43.2, 11.6], [39.5, 15.6], [37.2, 19.6], [35.6, 23.9], [33.8, 27.2],
        [32.6, 29.9], [34.3, 27.9], [34.9, 29.5], [34.3, 31.3], [32.3, 31.3], [29.9, 31.2], [25, 31.6], [24, 32.1],
        [20.1, 32.1], [19.8, 30.9], [18.2, 30.6], [15.2, 32.4], [13.2, 32.9], [11.5, 33.1], [10.1, 33.9], [10.8, 34.7],
        [10.8, 35.8], [11, 37.1], [9.8, 37.3], [3, 36.8], [-0.6, 35.7], [-2.9, 35.3], [-5.3, 35.9]],
    [[49.3, -12], [50.5, -15.5], [49.4, -17.5], [47.2, -24.9], [45.2, -25.5], [43.7, -22], [44.4, -16.5], [47, -15]],
    // Eurasia: Atlantic Europe, Scandinavia, Arctic and Pacific Asia (coarse: it faces away), South Asia,
    // Arabia and the Mediterranean back to Gibraltar.
    [[-5.6, 36], [-6.3, 36.5], [-9, 37], [-9.5, 38.7], [-8.9, 41.5], [-9.3, 42.9], [-8, 43.7], [-5.7, 43.6], [-1.6, 43.5],
        [-1.2, 46.2], [-2.5, 47.3], [-4.8, 48.4], [-1.6, 49.7], [0.2, 49.5], [1.9, 51], [4.1, 52], [4.8, 53], [7, 53.5],
        [8.7, 53.9], [8.1, 55.5], [8.2, 56.8], [10.6, 57.7], [10.3, 56.5], [9.8, 55], [10.1, 54.3], [12.1, 54.2],
        [14.2, 53.9], [18.7, 54.4], [21.1, 55.7], [21, 56.5], [21.6, 57.4], [24.1, 57], [23.5, 58.9], [24.75, 59.4],
        [28, 59.5], [30.3, 59.9], [28.7, 60.7], [24.9, 60.2], [22.2, 60.4], [21.6, 63.1], [25.5, 65], [22.1, 65.6],
        [20.3, 63.8], [17.3, 62.4], [17.1, 60.7], [18.9, 59.4], [16.8, 58.6], [16.4, 56.7], [15.6, 56.2], [13, 55.6],
        [12.7, 56], [11.9, 57.7], [11.2, 58.9], [10.7, 59.9], [10, 59], [8, 58.1], [5.7, 59], [5.3, 60.4], [5, 61.6],
        [6.2, 62.5], [7.7, 63.1], [11.2, 64.9], [14.4, 67.3], [13, 68], [19, 69.6], [23.7, 70.7], [25.8, 71.1], [30, 69.7],
        [33.1, 69], [36, 69.2], [41, 67.8], [44, 68.5], [54, 68.3], [60.5, 69.6], [70, 73], [80, 73.5], [104, 77.5],
        [113, 73.6], [127, 73.4], [140, 72.4], [160, 69.6], [170, 70], [180, 69], [180, 65], [177, 64.5], [170, 60],
        [163, 56], [156.7, 51], [156, 57], [160, 61], [150.8, 59.5], [143.2, 59.4], [138.2, 56.5], [141, 53], [140.5, 50],
        [138, 46.5], [131.9, 43.1], [129.8, 41], [129.4, 36], [129, 35.1], [126.4, 34.4], [126.6, 37.5], [125, 38],
        [124.3, 39.9], [121.6, 39], [121, 40.8], [118, 39.2], [117.7, 39], [119, 37.2], [121.4, 37.5], [122.6, 37.4],
        [120.4, 36.1], [119.4, 34.7], [121.9, 31], [122, 29.9], [120.7, 28], [119.6, 26], [118.1, 24.5], [116.7, 23.4],
        [114.2, 22.3], [110, 20.3], [109.1, 21.5], [106.7, 20.8], [105.7, 18.7], [108.2, 16.1], [109.2, 13.8],
        [109.2, 11.9], [107.1, 10.3], [104.8, 8.6], [105, 10], [104.2, 10.6], [102.9, 11.7], [100.5, 13.5], [99.9, 12.6],
        [99.2, 10.5], [100.6, 7.2], [102.2, 6.1], [103.3, 3.8], [103.8, 1.3], [102.2, 2.2], [101.4, 3], [100.3, 5.4],
        [98.4, 8], [98.6, 9.9], [98.2, 14.1], [97.6, 16.5], [96.2, 16.8], [94.3, 16], [92.9, 20.1], [91.8, 22.3],
        [88.8, 21.6], [87, 20.7], [85.8, 19.8], [83.3, 17.7], [81.2, 16.2], [80.3, 13.1], [79.8, 11.9], [79.9, 10.3],
        [78.1, 8.8], [77.5, 8.1], [76.2, 10], [75.8, 11.3], [74.8, 12.9], [73.8, 15.4], [72.8, 19], [72.6, 21.2], [72.2, 22.2],
        [71, 20.7], [69.6, 21.6], [69, 22.3], [70, 22.8], [68.4, 23.6], [67, 24.8], [62.3, 25.1], [57.8, 25.6], [56.3, 27.2],
        [54, 26.6], [50.8, 28.9], [48.5, 29.9], [48, 29.4], [50.1, 26.4], [51.6, 25.9], [54.4, 24.5], [55.3, 25.3],
        [56.3, 26.3], [56.35, 25.1], [58.6, 23.6], [59.8, 22.5], [57.7, 19.6], [54.1, 17], [49.1, 14.5], [45, 12.8],
        [43.4, 12.6], [42.9, 14.8], [42.5, 16.9], [39.2, 21.5], [38, 24.1], [36.5, 26], [35, 28], [35, 29.5], [34.4, 31.5],
        [35, 32.8], [35.5, 33.9], [35.8, 35.5], [36.2, 36.6], [34.6, 36.8], [30.7, 36.9], [29.1, 36.6], [27.4, 37],
        [26.8, 38.4], [26.2, 40], [26, 40.8], [22.9, 40.6], [23.7, 37.9], [22.5, 36.4], [21.7, 36.8], [21.7, 38.2],
        [20.7, 39], [20.2, 39.5], [19.5, 40.5], [19.4, 41.3], [18.1, 42.6], [16.4, 43.5], [15.2, 44.1], [14.4, 45.3],
        [13.8, 45.6], [12.3, 45.4], [12.3, 44.4], [13.5, 43.6], [14.2, 42.5], [16.2, 41.9], [16.9, 41.1], [18, 40.6],
        [18.5, 40.1], [17.2, 40.5], [16.6, 39], [15.6, 38], [15.9, 38.7], [14.8, 40.6], [14.2, 40.8], [13.6, 41.2],
        [12.3, 41.7], [11.8, 42.1], [10.5, 42.9], [10.3, 43.5], [8.9, 44.4], [7.3, 43.7], [5.9, 43.1], [5.4, 43.3],
        [3.9, 43.5], [3, 42.6], [2.2, 41.4], [1.2, 41.1], [0.9, 40.7], [-0.3, 39.5], [0.2, 38.7], [-0.5, 38.3], [-1, 37.6],
        [-2.4, 36.8], [-4.4, 36.7], [-5.4, 36.1]],
    // Mediterranean islands and Sri Lanka.
    [[12.4, 38.1], [15.6, 38.3], [15.1, 36.7], [12.6, 37.6]],
    [[8.2, 41], [9.7, 40.9], [9.6, 39.2], [8.4, 39]],
    [[9.4, 43], [9.5, 42], [9.2, 41.4], [8.6, 41.9]],
    [[23.5, 35.6], [26.3, 35.3], [26, 35], [23.6, 35.2]],
    [[32.3, 35.1], [34.6, 35.7], [33.9, 34.9], [32.9, 34.6]],
    [[79.9, 9.8], [81.9, 7.5], [81.2, 6.1], [80.1, 6.2], [79.8, 8]],
    // Australia.
    [[113.6, -22], [114.9, -29], [115, -34], [118, -35], [123, -33.8], [129, -31.6], [135.5, -34.8], [138, -35.5],
        [140, -37.8], [146, -39], [150, -37.5], [153.5, -28], [153, -25], [149, -21], [145.5, -15], [142.5, -10.7],
        [141.5, -13], [139.5, -17.5], [136, -15], [136.8, -12], [131, -11.3], [129.5, -15], [125, -14.5], [122, -17.5],
        [116.5, -20.5]],
];

/** Inland seas, drawn over the land. */
const WATER = [
    [[27.6, 42.5], [28, 41.2], [31.2, 41.1], [35.2, 42], [38.3, 40.9], [41.6, 41.6], [40, 43.5], [37.8, 44.7], [34.5, 44.5],
        [33.5, 44.6], [32.5, 45.4], [30.7, 46.5], [29.7, 45.2], [28.6, 44]],
    [[47, 44.5], [49, 46.6], [53, 46.7], [51, 44], [53, 42], [53.9, 40], [53.9, 37.3], [51, 36.7], [49, 37.6], [49.5, 40.5],
        [47.5, 42.8]],
];

const ICE = [
    // Greenland.
    [[-73, 78.2], [-60, 82], [-40, 83.5], [-20, 82], [-18, 77], [-22, 72.5], [-21.5, 70.5], [-26, 68.3], [-32, 68],
        [-38, 65.5], [-43, 60], [-48, 61], [-52, 64], [-53.5, 67], [-51, 69.5], [-54.5, 70.8], [-56, 74], [-62, 76], [-68, 77]],
];

/** Equirectangular map: ocean, land shaded by latitude (deserts in the subtropics), ice and a grid. */
const earthTexture = () => {
    const width = 1024;
    const height = 512;
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d');
    const y = (latitude) => ((90 - latitude) / 180) * height;
    const x = (longitude) => ((longitude + 180) / 360) * width;
    const band = (stops) => {
        const gradient = context.createLinearGradient(0, 0, 0, height);
        stops.forEach(([latitude, color]) => gradient.addColorStop((90 - latitude) / 180, color));

        return gradient;
    };
    const fill = (shapes, style) => {
        context.fillStyle = style;
        shapes.forEach((shape) => {
            context.beginPath();
            shape.forEach(([longitude, latitude], index) => context[index === 0 ? 'moveTo' : 'lineTo'](x(longitude), y(latitude)));
            context.closePath();
            context.fill();
        });
    };

    const ocean = band([[90, '#173a5e'], [40, '#1c4f7d'], [0, '#215f96'], [-40, '#1c4f7d'], [-90, '#173a5e']]);
    context.fillStyle = ocean;
    context.fillRect(0, 0, width, height);

    fill(LAND, band([
        [75, '#cfd6d2'], [62, '#6f8a5a'], [45, '#7f9a5c'], [33, '#b8a774'], [24, '#d0b67f'], [16, '#b6a66c'], [8, '#5d9150'],
        [-8, '#5f9450'], [-20, '#8f9f5e'], [-28, '#b9a676'], [-40, '#8aa060'], [-56, '#a3ab92'],
    ]));
    fill(WATER, ocean);
    fill(ICE, '#e6edf0');

    // Antarctica: an ice shelf along the bottom.
    context.fillStyle = '#e6edf0';
    context.beginPath();
    context.moveTo(0, height);
    for (let longitude = -180; longitude <= 180; longitude += 15) {
        context.lineTo(x(longitude), y(-69 + 3 * Math.sin(longitude * 0.07) + 2 * Math.cos(longitude * 0.13)));
    }
    context.lineTo(width, height);
    context.fill();

    context.strokeStyle = 'rgba(255, 255, 255, 0.13)';
    context.lineWidth = 1;
    for (let longitude = -180; longitude < 180; longitude += 30) {
        context.beginPath();
        context.moveTo(x(longitude) + 0.5, 0);
        context.lineTo(x(longitude) + 0.5, height);
        context.stroke();
    }
    for (let latitude = -60; latitude <= 60; latitude += 30) {
        context.beginPath();
        context.moveTo(0, y(latitude) + 0.5);
        context.lineTo(width, y(latitude) + 0.5);
        context.stroke();
    }

    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.anisotropy = 4;

    return texture;
};

/** Unit vector of a place on THREE.SphereGeometry's default texture mapping. */
const surfaceDirection = (latitude, longitude) => {
    const lat = latitude * DEG;
    const lon = longitude * DEG;

    return new THREE.Vector3(Math.cos(lat) * Math.cos(lon), Math.sin(lat), -Math.cos(lat) * Math.sin(lon));
};

/** A satellite seen from its side: gold body, two solar wings, the instrument looking down (+z). */
const buildSatellite = (m) => {
    const satellite = new THREE.Group();
    const foil = new THREE.MeshStandardMaterial({ color: '#d4a537', roughness: 0.38, metalness: 0.65, flatShading: true });
    const wing = [m.white, m.white, m.white, m.white, m.cells, m.cells];

    satellite.add(new THREE.Mesh(new THREE.BoxGeometry(0.15, 0.15, 0.17), foil));
    [1, -1].forEach((side) => {
        const boom = new THREE.Mesh(new THREE.CylinderGeometry(0.006, 0.006, 0.08, 6), m.metal);
        boom.position.y = side * 0.11;
        // Turned toward the sun, so they are seen from the side as well as from above.
        const panel = new THREE.Mesh(new THREE.BoxGeometry(0.13, 0.34, 0.008), wing);
        panel.position.y = side * 0.32;
        panel.rotation.y = 50 * DEG;
        satellite.add(boom, panel);
    });

    const instrument = new THREE.Mesh(new THREE.CylinderGeometry(0.034, 0.042, 0.06, 14), m.white);
    instrument.rotation.x = Math.PI / 2;
    instrument.position.z = 0.115;
    const lens = new THREE.Mesh(new THREE.CircleGeometry(0.026, 14), m.dark);
    lens.position.z = 0.146;
    const dish = new THREE.Mesh(new THREE.SphereGeometry(0.07, 16, 6, 0, Math.PI * 2, 0, 0.6), m.whiteBothSides);
    dish.rotation.z = Math.PI / 2;
    dish.position.set(0.14, 0, -0.03);
    satellite.add(instrument, lens, dish);

    // What it measures: a cone of light from the instrument down to the surface.
    const reach = ORBIT_RADIUS - EARTH_RADIUS - 0.13;
    const beamMaterial = new THREE.MeshBasicMaterial({
        color: ACCENT, transparent: true, opacity: 0.15, blending: THREE.AdditiveBlending, depthWrite: false, side: THREE.DoubleSide,
    });
    const beam = new THREE.Mesh(new THREE.ConeGeometry(0.2, reach, 28, 1, true), beamMaterial);
    beam.rotation.x = -Math.PI / 2;
    beam.position.z = 0.15 + reach / 2;
    satellite.add(beam);

    return { satellite, beamMaterial };
};

/**
 * @param {{latitude: number, longitude: number}} point Where the app asks NASA POWER for data.
 */
export const buildNasaStation = (m, point) => {
    const group = new THREE.Group();
    group.position.y = 1.2;

    // Everything on the Earth turns with it, so the point can face the camera.
    const earth = new THREE.Group();
    group.add(earth);
    earth.add(new THREE.Mesh(
        new THREE.SphereGeometry(EARTH_RADIUS, 72, 48),
        new THREE.MeshStandardMaterial({ map: earthTexture(), roughness: 0.9, metalness: 0 }),
    ));
    group.add(new THREE.Mesh(
        new THREE.SphereGeometry(EARTH_RADIUS * 1.07, 48, 32),
        new THREE.MeshBasicMaterial({ color: '#8cc8ff', transparent: true, opacity: 0.16, side: THREE.BackSide, depthWrite: false }),
    ));

    // The point: a gold pin with a ring that opens after each pass.
    const up = surfaceDirection(point.latitude, point.longitude);
    const north = new THREE.Vector3(0, 1, 0).sub(up.clone().multiplyScalar(up.y)).normalize();
    const east = north.clone().cross(up).normalize();
    const pinMaterial = new THREE.MeshStandardMaterial({ color: ACCENT, emissive: new THREE.Color(ACCENT), emissiveIntensity: 0.4, roughness: 0.4 });
    const ringMaterial = new THREE.MeshBasicMaterial({ color: ACCENT, transparent: true, opacity: 0, side: THREE.DoubleSide, depthWrite: false });
    const marker = new THREE.Group();
    marker.position.copy(up).multiplyScalar(EARTH_RADIUS);
    marker.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), up);
    const pin = new THREE.Mesh(new THREE.CylinderGeometry(0.01, 0.01, 0.09, 8), pinMaterial);
    pin.position.y = 0.045;
    const head = new THREE.Mesh(new THREE.SphereGeometry(0.026, 14, 10), pinMaterial);
    head.position.y = 0.1;
    const ring = new THREE.Mesh(new THREE.RingGeometry(0.05, 0.064, 40), ringMaterial);
    ring.rotation.x = -Math.PI / 2;
    ring.position.y = 0.004;
    marker.add(pin, head, ring);
    earth.add(marker);

    // Orbit through the point: it passes over it heading south, tilted like a sun-synchronous orbit.
    const along = north.clone().multiplyScalar(-Math.cos(TRACK_TILT)).addScaledVector(east, -Math.sin(TRACK_TILT));
    const normal = up.clone().cross(along).normalize();
    const orbitAt = (phase, target = new THREE.Vector3()) => target.copy(up).multiplyScalar(Math.cos(phase)).addScaledVector(along, Math.sin(phase)).multiplyScalar(ORBIT_RADIUS);
    const path = new THREE.LineLoop(
        new THREE.BufferGeometry().setFromPoints(Array.from({ length: 160 }, (_, index) => orbitAt((index / 160) * Math.PI * 2))),
        new THREE.LineDashedMaterial({ color: '#cfe0ff', dashSize: 0.05, gapSize: 0.045, transparent: true, opacity: 0.5 }),
    );
    path.computeLineDistances();
    earth.add(path);

    const { satellite, beamMaterial } = buildSatellite(m);
    // Much larger than life, or it would be a speck next to the Earth.
    satellite.scale.setScalar(1.3);
    earth.add(satellite);

    // Stars far behind, beyond the camera: only those behind the Earth are ever in view.
    const stars = new Float32Array(260 * 3);
    for (let index = 0; index < 260; index++) {
        const direction = new THREE.Vector3().randomDirection().multiplyScalar(13 + Math.random() * 4);
        stars.set([direction.x, direction.y, direction.z], index * 3);
    }
    const starGeometry = new THREE.BufferGeometry();
    starGeometry.setAttribute('position', new THREE.BufferAttribute(stars, 3));
    group.add(new THREE.Points(starGeometry, new THREE.PointsMaterial({ color: '#e8eefc', size: 1.6, sizeAttenuation: false, transparent: true, opacity: 0.8 })));

    group.traverse((object) => {
        object.castShadow = false;
        object.receiveShadow = false;
    });

    const toward = new THREE.Vector3();
    const basis = new THREE.Matrix4();
    const side = new THREE.Vector3();
    const place = (phase) => {
        orbitAt(phase, satellite.position);
        toward.copy(satellite.position).negate().normalize();
        side.copy(normal).cross(toward);
        satellite.quaternion.setFromRotationMatrix(basis.makeBasis(side, normal, toward));
    };

    let time = 0;
    place(START_PHASE);

    return {
        group,
        onGround: false,
        autoRotate: false,
        frame: { target: new THREE.Vector3(0, 1.2, 0), radius: 1.95, polar: 76 * DEG },
        /** Turns the Earth so the point faces `direction` (world, from the center toward the camera), north up. */
        face(direction) {
            const front = direction.clone().normalize();
            const top = new THREE.Vector3(0, 1, 0).addScaledVector(front, -front.y).normalize();
            const right = top.clone().cross(front);
            const from = new THREE.Matrix4().makeBasis(east, north, up);
            const to = new THREE.Matrix4().makeBasis(right, top, front);
            earth.quaternion.setFromRotationMatrix(to.multiply(from.transpose()));
        },
        update(dt, still = false) {
            time += dt;
            // Reduced motion: parked just before the pass, beam on the point.
            const phase = still ? -0.12 : START_PHASE + (time / ORBIT_SECONDS) * Math.PI * 2;
            place(phase);

            // Over the point: the beam and the pin light up, and the ring opens right after.
            const over = THREE.MathUtils.smoothstep(Math.cos(phase), Math.cos(16 * DEG), Math.cos(3 * DEG));
            beamMaterial.opacity = 0.1 + 0.28 * over;
            pinMaterial.emissiveIntensity = 0.35 + 1.1 * over;
            const sincePass = ((((phase % (Math.PI * 2)) + Math.PI * 2) % (Math.PI * 2)) / (Math.PI * 2)) * ORBIT_SECONDS;
            const ping = still ? 1 : sincePass / PING_SECONDS;
            ring.visible = ping < 1;
            if (ring.visible) {
                ring.scale.setScalar(1 + ping * 3.2);
                ringMaterial.opacity = 0.9 * (1 - ping);
            }
        },
    };
};
