---
tipo: adr
descripcion: ADR-0008 — Mostrar cada fuente de datos climáticos en su propia pestaña
actualizado: 2026-10-01
---

# ADR-0008 · Datos climáticos en pestañas por fuente

- **Estado:** 🟢 Aceptada · implementada el 2026-10-01
- **Fecha:** 2026-10-01
- **Contexto del repo:** `resources/views/api-data/index.blade.php`, `ApiDataController`, `resources/js/app.js` (paginación y autosincronización)

## Contexto
La página "Datos climáticos" (solo para administración) apilaba en vertical tres bloques grandes: Ambient Weather, la estación local y NASA POWER. Cada uno tiene gráfico, tabla y paginación propios. Con muchos datos había que desplazarse mucho para llegar a NASA, y la estación quedaba enterrada entre las otras dos.

Además, **las fuentes no se leen juntas**:
- tienen columnas distintas: Ambient tiene 9, la estación 12 y NASA 8;
- tienen frecuencias distintas: cada pocos minutos frente a diaria;
- y cada una se sincroniza por separado.

La tarea real es "revisar una fuente", no "comparar filas entre fuentes".

## Decisión
Una **pestaña por fuente**: Ambient Weather · Estación local · NASA POWER.

- **Resumen arriba:** las cifras de los 4 indicadores siguen arriba, así que la visión global no se pierde.
- **Pestaña en la URL:** la activa se guarda en `?tab=ambient|weather-station|nasa`, así que se puede recargar, compartir y usar "atrás".
- **El servidor elige la pestaña:** usa `tab`, o infiere la fuente del parámetro de página (`nasa_page`, `station_page`, `ambient_page`). Así, la paginación por AJAX, que reemplaza la sección completa, mantiene la pestaña correcta.
- **Mejora progresiva:** las pestañas son enlaces reales. Sin JS funcionan recargando; con JS cambian al instante y actualizan la URL con `replaceState`.
- **Accesibilidad:** `role="tablist"/"tab"/"tabpanel"`, flechas ← → e Inicio/Fin para moverse entre pestañas.
- **Sincronización automática:** sigue actualizando las tres fuentes, también las ocultas.

## Consecuencias
- ➕ Sin desplazamiento largo: cada fuente se ve completa en una pantalla.
- ➕ Menos carga visual, y la URL describe exactamente lo que se ve.
- ➖ No se ven dos fuentes a la vez. Se acepta, porque no es una tarea frecuente y los totales siguen visibles arriba.
- ⚠️ Los gráficos de Chart.js de pestañas ocultas se dibujan cuando la pestaña aparece (redimensionado automático).

## Alternativas consideradas
- **Secciones plegables:** al abrir dos o más se vuelve al scroll largo.
- **Una página por fuente:** más rutas y se pierde el resumen común.
- **Selector desplegable de fuente:** oculta las opciones; las pestañas muestran las tres de un vistazo.

## Relacionado
[[adr-0007-formulario-de-proyecto-por-etapas]] (mismo criterio: menos scroll, una cosa a la vez)
