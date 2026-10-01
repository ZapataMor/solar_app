---
tipo: adr
descripcion: ADR-0006 — Recomendar la configuración del sistema; la IA explica, el cálculo decide
actualizado: 2026-09-30
---

# ADR-0006 · Recomendación del sistema

- **Estado:** 🟡 Propuesta
- **Fecha:** 2026-09-30
- **Contexto del repo:** `SolarCalculator`, `OpenAIRecommendationService`, `DashboardAiWidgetService`

## Contexto
Hoy la app calcula el número de paneles a partir del área y de una potencia de panel que ingresa el usuario. La IA genera recomendaciones operativas: ahorro, mantenimiento, riesgo. El asesor pidió que la salida principal sea **cómo sería tu sistema**:
- qué paneles convienen (mono o policristalino, vatios, medidas),
- inversor, regulador o controlador y batería,
- si va conectado a la red (on-grid) o aislado (off-grid), o solo algunos circuitos.

También pidió no vender el producto como "basado en IA": la IA **recomienda**. Ver [[recomendacion-del-sistema]].

## Decisión
- **Catálogo de equipos:** paneles, inversores y baterías con especificaciones y precio. Se comparte con [[adr-0004-cotizacion-por-items-y-transporte-interno]].
- **Elección determinista:** un recomendador en el dominio elige la configuración con reglas (consumo, área disponible, necesidad de respaldo). Muestra un **comparativo** de 2 o 3 opciones de panel.
- **Papel de la IA:** explicar la recomendación en lenguaje simple y responder dudas; **no elige** los equipos.
- **Modo del sistema:** on-grid, off-grid o híbrido se deriva de si el cliente quiere respaldo ante cortes (las neveras que no se descongelen).

## Consecuencias
- ➕ Los resultados son reproducibles y verificables, sin depender de la variabilidad del LLM.
- ➕ Encaja con el mensaje del pitch ([[pitch-primera-etapa]]).
- ➖ Hay que construir y mantener el catálogo.
- ⚠️ Las reglas deben validarlas un instalador o el asesor.

## Alternativas consideradas
- **Que la IA elija los equipos:** es rápido de prototipar, pero no es reproducible y es difícil de justificar ante el cliente.

## Relacionado
[[asesoria-felix-bada]] · [[adr-0002-consumo-por-electrodomesticos]]
