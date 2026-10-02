---
tipo: adr
descripcion: ADR-0011 — Explicar los resultados del proyecto con preguntas en lenguaje simple (puntos laterales + panel de 1/3)
estado: ✅ Implementada
actualizado: 2026-10-01
---

# ADR-0011 · Preguntas explicativas del proyecto

- **Estado:** 🟢 Aceptada · implementada el 2026-10-01
- **Fecha:** 2026-10-01
- **Contexto del repo:** `App\Domain\Explanation\*`, `ExplainSolarProject`, `partials/project-explainer.blade.php`, `resources/css/project-explainer.css`

## Contexto
El panel de un proyecto muestra kWh, kWp, cobertura, retorno y una gráfica de generación frente a consumo. Un cliente sin formación técnica, que es el usuario objetivo ([[clientes-y-usuarios]]), **no sabe interpretarlos**: ¿me conviene?, ¿cuánto pago?, ¿cuánto ahorro? El asesor insistió en que la información debe ser comprensible ([[asesoria-felix-bada]]).

## Decisión
Una capa explicativa **basada en preguntas**, sin quitar el panel técnico:

- **Puntos en el borde derecho,** uno por pregunta. Al pasar el mouse muestran la pregunta; al hacer clic abren un **panel lateral de un tercio de la pantalla**.
- **El panel tiene:**
  - la pregunta;
  - una **cifra grande** con su lectura ("3,6 años");
  - 2 o 3 frases cortas con comparaciones cotidianas;
  - "Anterior / Siguiente" para recorrer las preguntas;
  - cierre con X o Esc.
- **Las 10 preguntas:**
  1. ¿Me conviene instalar paneles?
  2. ¿Cuánto me cuesta?
  3. ¿Cuánto voy a ahorrar?
  4. ¿En cuánto tiempo recupero mi dinero?
  5. ¿Qué parte de mi luz pagaría el sol?
  6. ¿Qué significa "generación vs. consumo"?
  7. ¿Cuántos paneles necesito y cuánto espacio ocupan?
  8. ¿Me quedo sin luz de noche o si está nublado?
  9. ¿Qué tan confiables son estos datos?
  10. ¿Qué es un kWh?
- **Respuestas calculadas, no generadas por IA:** plantillas en el dominio (`ProjectExplainer`, PHP puro) alimentadas con **las mismas cifras del panel**. Son instantáneas, consistentes, verificables con tests y no pueden inventar números frente al cliente.
- **Proyecto sin calcular:** una sola pregunta, "¿Por qué no veo respuestas?", con el botón Calcular.
- **En el celular:** los puntos se reemplazan por un botón "¿Qué significan estos números?" y el panel ocupa toda la pantalla.

## Consecuencias
- ➕ El cliente entiende su proyecto sin saber de energía: pesos, años y comparaciones.
- ➕ Es buen material para el pitch: muestra que la app "traduce" lo técnico.
- ➕ El panel técnico se mantiene para el instalador.
- ➖ Las plantillas hay que mantenerlas cuando cambien los cálculos (tests de dominio).
- ⚠️ Las comparaciones (vida útil de 25 años, excedentes a la red) son aproximaciones y se presentan como tales.

## Alternativas consideradas
- **Respuestas generadas por IA:** más naturales, pero pueden inventar cifras y dependen de la red y la API. Queda como mejora futura solo para reformular textos.
- **Un glosario o una sección de texto largo:** el usuario pidió preguntas, no bloques de texto.
- **Tooltips sobre cada número:** dispersos y poco descubribles en el celular.

## Relacionado
[[adr-0007-formulario-de-proyecto-por-etapas]] · [[adr-0010-aviso-de-recalculo]]
