---
tipo: adr
descripcion: ADR-0010 — Avisar con "!" cuando un proyecto necesita recalcularse, sin recalcular solo
estado: ✅ Implementada
actualizado: 2026-10-01
---

# ADR-0010 · Aviso inteligente de recálculo

- **Estado:** 🟢 Aceptada · implementada el 2026-10-01
- **Fecha:** 2026-10-01
- **Contexto del repo:** `App\Domain\Solar\CalculationFreshnessPolicy`, `CheckCalculationFreshness`, `RecalculateProjects`, `ClimateSource::lastChangedAt()`

## Contexto
Los resultados de un proyecto se calculan al pulsar "Calcular". Desde [[adr-0009-nasa-power-diario-con-datos-reales]], los datos climáticos cambian solos: llegan lecturas nuevas y NASA confirma las estimaciones. El usuario no tenía forma de saber que sus resultados ya no reflejaban los datos.

## Decisión
**Avisar, no recalcular solo.**
- **Portafolio:** un botón "Recalcular proyectos" con un **"!"** y el número de proyectos afectados. Recalcula **solo** esos proyectos, con la mejor fuente disponible. Cada tarjeta afectada muestra "Por recalcular" o "Sin calcular".
- **Detalle:** el botón "Calcular" lleva el "!", y un aviso explica **los motivos**.

**Qué cuenta como cambio** (lo decide una política pura del dominio, con pruebas):
1. Cambiaron los datos del proyecto, sus parámetros técnicos o sus equipos. Guardar sin cambios no cuenta.
2. Hay datos nuevos de una fuente **de mejor calidad** que la usada (Ambient > estación > NASA).
3. Hay datos nuevos de **la misma** fuente, pero solo **un día o más** después del cálculo, para que no haya un "!" permanente con lecturas cada 5 minutos.
4. Los datos de fuentes de **menor** calidad se ignoran: no cambiarían el resultado.

## Consecuencias
- ➕ Transparencia: el usuario sabe cuándo y por qué sus números quedaron desactualizados, y decide.
- ➕ Los números no cambian "solos" mientras el cliente los mira, ni en plena demo.
- ➖ Requiere un clic del usuario.
- ⚠️ Un proyecto cuyo periodo incluye hoy y usa Ambient pedirá recalcular como máximo una vez al día. Es lo esperado.
- ⚠️ El portafolio evalúa la vigencia de todos los proyectos visibles en cada carga: unas pocas consultas por proyecto. Habría que cachearlo si llegan a ser cientos.

## Alternativas consideradas
- **Recalcular automáticamente tras cada sincronización:** cada 5 minutos, para todos los proyectos. Es costoso y cambia los números sin aviso.
- **Sin aviso, solo el botón:** el usuario no sabría cuándo pulsarlo.

## Relacionado
[[adr-0001-acercar-a-clean-architecture]] · [[adr-0009-nasa-power-diario-con-datos-reales]]
