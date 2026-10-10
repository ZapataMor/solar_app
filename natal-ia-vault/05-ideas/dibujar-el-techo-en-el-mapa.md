---
tipo: idea
descripcion: Dibujar el techo sobre una foto satelital para sacar los m², en vez de pedirle al cliente que los escriba
estado: ❄️ Aplazada — faltan teselas satelitales
esfuerzo: ~1 día el dibujo; la fuente de imagen es el bloqueo
creado: 2026-10-10
tags: [idea, aplazada, producto]
---

# Dibujar el techo en el mapa

**Qué es:** en el formulario, el cliente ve su casa desde arriba y marca el contorno del techo con
unos clics. De ahí salen los m² disponibles, sin que tenga que medir ni adivinar.

## Por qué
**Arregla el peor dato de entrada de toda la app.** Hoy `available_area_m2` se escribe a mano y es
obligatorio, pero **casi nadie sabe el área de su techo**: lo inventa. Y ese número no es un adorno,
es el **límite de paneles** del dimensionamiento ([[adr-0014-dimensionar-por-necesidad-y-mi-sistema]]):
si el cliente escribe 200 donde hay 60, la app recomienda un sistema que no cabe y el instalador
queda desmintiendo el estudio en la visita técnica.

Es, además, lo más vistoso de todo el listado: ver tu propia casa desde arriba y pintarle los paneles
encima es la clase de momento que se recuerda de una demo.

## Por qué está aplazada
**Los dos mapas de la app usan teselas de OpenStreetMap, que son un callejero: no traen foto
satelital.** Sin imagen aérea no hay techo que dibujar, y ahí está el bloqueo real:

- `resources/js/coverage-map.js` y `solar-projects/_form.blade.php` cargan
  `tile.openstreetmap.org`.
- Las fuentes satelitales son otra cosa: Esri World Imagery tiene condiciones de uso que hay que leer
  antes de montar un negocio encima, y Mapbox o Google piden clave y cobran por carga de mapa. **Es
  una decisión de costo y de licencia, no de código.**
- La resolución tampoco está garantizada: la foto de Riohacha puede servir, pero en un corregimiento
  pequeño la imagen disponible quizá no deje distinguir un techo.

La parte técnica es la fácil: el polígono y el área con la fórmula del área de un polígono, sobre el
Leaflet que ya está cargado. **~1 día** una vez haya imagen.

## Qué la desbloquearía
- Elegir una fuente de teselas satelitales con su licencia y su costo revisados.
- Comprobar la resolución disponible en los municipios que de verdad importan, no solo en Riohacha.

## Mientras tanto, barato
Ayudar a estimar sin mapa: *"una casa de un piso en La Guajira tiene típicamente X m² de techo útil"*,
o pedir el frente y el fondo en metros en vez del área, que la gente sí sabe medir con una cinta.

## Relacionado
[[adr-0014-dimensionar-por-necesidad-y-mi-sistema]] · [[adr-0012-ilustracion-3d-de-la-instalacion]] ·
[[adr-0022-directorio-de-instaladores-y-solicitudes]] · [[adr-0007-formulario-de-proyecto-por-etapas]] ·
[[nuevas-funcionalidades]]
