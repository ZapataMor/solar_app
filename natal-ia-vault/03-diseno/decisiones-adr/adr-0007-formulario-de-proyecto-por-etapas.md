---
tipo: adr
descripcion: ADR-0007 — Formulario de creación y edición de proyectos por etapas (wizard)
actualizado: 2026-10-01
---

# ADR-0007 · Formulario de proyecto por etapas

- **Estado:** 🟢 Aceptada
- **Fecha:** 2026-10-01
- **Contexto del repo:** `resources/views/solar-projects/_form.blade.php`, `resources/css/project-wizard.css`, `App\Domain\Solar\SystemSpecification`

## Contexto
El formulario era una sola página vertical con 4 bloques: información, ubicación con mapa, parámetros técnicos y pre-simulación. Tenía unos 15 campos, y le pedía al cliente datos técnicos que no conoce: % de área utilizable, potencia y área del panel, pérdidas y fechas del periodo. Los errores aparecían al final, después de recorrer todo. Además, el [[adr-0002-consumo-por-electrodomesticos]] va a agregar muchos campos más.

## Decisión
Se divide en **5 etapas** dentro de un único `<form>`:

| # | Etapa | Campos |
|---|---|---|
| 1 | Tu proyecto | nombre, descripción |
| 2 | Ubicación | municipio en el mapa, tipo de zona; coordenadas plegadas |
| 3 | Consumo | kWh mensual, tarifa, guía del recibo |
| 4 | Espacio disponible | área en m²; **Avanzado** plegado: % utilizable, panel (W y m²), pérdidas y fechas |
| 5 | Resumen y estimación | resumen con "Editar" por fila, pre-simulación y Guardar |

- **Solo navegador:** las etapas se manejan con JS en el cliente. Se mantienen el mismo POST, las mismas reglas de `SolarProjectRequest` y las mismas rutas. Sin JS se ve el formulario completo.
- **Validación:** se valida cada etapa antes de avanzar. Si el servidor rechaza un dato, el formulario abre la **primera etapa con error** y la marca en el indicador.
- **Valores por defecto al crear**, en `SystemSpecification`:
  - 80 % de área utilizable;
  - panel de 550 W y 2,6 m²;
  - 14 % de pérdidas (el valor por defecto de PVWatts);
  - fecha inicial: hoy, en hora de Colombia.
- **Al editar** se puede saltar a cualquier etapa. Al crear, solo a las ya visitadas.
- **El mapa** recalcula su tamaño al mostrarse su etapa (Leaflet no dibuja bien en contenedores ocultos).

## Consecuencias
- ➕ Menos carga visual: el cliente ve 2 o 3 campos por pantalla, con progreso visible ("Paso 2 de 5").
- ➕ Los parámetros técnicos dejan de estorbar al cliente; aplica en parte el [[adr-0003-separar-datos-cliente-e-instalador]].
- ➕ El ADR-0002 solo reemplaza la etapa 3.
- ➕ Funciona mejor en el celular y en la demo del pitch.
- ➖ Hay más clics y no se ve todo junto; lo compensa el resumen de la etapa 5.
- ⚠️ Los valores por defecto afectan el resultado: deben validarlos un instalador o el asesor.

## Alternativas consideradas
- **Mantener el formulario vertical con secciones plegables:** menos trabajo, pero no guía ni valida por partes.
- **Un formulario por etapa con POST intermedios:** obliga a guardar borradores y cambiar el backend; es excesivo para este alcance.

## Relacionado
[[asesoria-felix-bada]] · [[datos-cliente-vs-instalador]]
