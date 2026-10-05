"""Simplify the municipality boundaries so a browser can redraw them (ADR-0021).

The file the IGAC publishes (public/maps/la_guajira_municipios.geojson, ~4 MB) carries the coast of
Uribia point by point. Leaflet can draw it once, but restyling it -- which the installer coverage map
does on every click -- freezes the tab for seconds. At the zoom of a department-wide map that detail
is invisible, so the outlines are thinned with Douglas-Peucker and rounded to 4 decimals (~11 m).

    python scripts/maps/simplify-geojson.py

Writes public/maps/la_guajira_municipios_simple.geojson. Run it again if the source file changes.
"""

import io
import json
import os
import sys

# The coastline of Uribia is one ring of tens of thousands of points: Douglas-Peucker recurses deep.
sys.setrecursionlimit(100000)

SOURCE = "public/maps/la_guajira_municipios.geojson"
TARGET = "public/maps/la_guajira_municipios_simple.geojson"
# Degrees. ~330 m: a border this close to the real one cannot be told apart at department zoom.
TOLERANCE = 0.003
DECIMALS = 4
# Properties the maps actually read; the rest (areas, internal ids) only add weight.
KEEP = ("divipola", "mpio_cdpmp", "mpio_cnmbr", "dpto_cnmbr")


def perpendicular_distance(point, start, end):
    (x, y), (x1, y1), (x2, y2) = point, start, end
    dx, dy = x2 - x1, y2 - y1

    if dx == 0 and dy == 0:
        return ((x - x1) ** 2 + (y - y1) ** 2) ** 0.5

    return abs(dy * x - dx * y + x2 * y1 - y2 * x1) / ((dx * dx + dy * dy) ** 0.5)


def douglas_peucker(points, tolerance):
    if len(points) < 3:
        return points

    index, farthest = 0, 0.0

    for i in range(1, len(points) - 1):
        distance = perpendicular_distance(points[i], points[0], points[-1])

        if distance > farthest:
            index, farthest = i, distance

    if farthest <= tolerance:
        return [points[0], points[-1]]

    left = douglas_peucker(points[: index + 1], tolerance)
    right = douglas_peucker(points[index:], tolerance)

    return left[:-1] + right


def simplify_ring(ring):
    """A ring must stay closed and keep at least a triangle, however coarse the tolerance."""
    simplified = douglas_peucker([tuple(point[:2]) for point in ring], TOLERANCE)

    if len(simplified) < 4:
        simplified = [tuple(point[:2]) for point in ring][:: max(1, len(ring) // 8)]

    if simplified[0] != simplified[-1]:
        simplified.append(simplified[0])

    return [[round(x, DECIMALS), round(y, DECIMALS)] for x, y in simplified]


def simplify_geometry(geometry):
    kind = geometry["type"]

    if kind == "Polygon":
        return {"type": kind, "coordinates": [simplify_ring(ring) for ring in geometry["coordinates"]]}

    if kind == "MultiPolygon":
        return {
            "type": kind,
            "coordinates": [[simplify_ring(ring) for ring in polygon] for polygon in geometry["coordinates"]],
        }

    raise ValueError("Unexpected geometry: " + kind)


def main():
    with io.open(SOURCE, encoding="utf-8") as handle:
        data = json.load(handle)

    data["features"] = [
        {
            "type": "Feature",
            "properties": {key: feature["properties"][key] for key in KEEP if key in feature["properties"]},
            "geometry": simplify_geometry(feature["geometry"]),
        }
        for feature in data["features"]
    ]

    with io.open(TARGET, "w", encoding="utf-8", newline="\n") as handle:
        json.dump(data, handle, ensure_ascii=False, separators=(",", ":"))

    before = os.path.getsize(SOURCE) / 1024
    after = os.path.getsize(TARGET) / 1024
    print("%s: %.0f KB -> %.0f KB (%d municipios)" % (TARGET, before, after, len(data["features"])))


if __name__ == "__main__":
    main()
