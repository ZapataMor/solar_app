---
tipo: idea
descripcion: Mostrar el recibo de luz mes a mes como queda con el sistema, al lado de lo que paga hoy
estado: 🟡 Candidata
esfuerzo: ~2 horas
creado: 2026-10-10
tags: [idea, producto]
---

# Tu recibo, antes y después

**Qué es:** doce pares de barras. Lo que paga hoy cada mes y lo que pagaría con el sistema instalado.

## Por qué
**El ahorro anual no se siente; el recibo sí.** El cliente no tiene una intuición de "ahorro de
6.240.000 al año", pero sabe perfectamente qué significa que el recibo de agosto pase de 520.000 a
90.000. Es la misma información, contada en la unidad en la que la persona ya piensa.

Y hace visible algo que el promedio esconde: **el ahorro no es igual todos los meses**. En los meses
de más sol el recibo casi desaparece; en los nublados, no. Verlo evita el reclamo de "me dijeron que
no iba a pagar luz".

## Viabilidad
- **Dónde va:** solo presentación. El ahorro mensual ya está calculado —y ya cuenta únicamente
  `min(generación, consumo)`, que es lo correcto porque los excedentes no bajan el recibo
  ([[adr-0014-dimensionar-por-necesidad-y-mi-sistema]])— y el modo de consumo por recibo
  ([[adr-0020-consumo-por-equipos-o-por-recibo]]) ya trae los kWh del mes.
- **Esfuerzo:** ~2 horas.
- **Riesgo:** bajo, con una trampa conocida: **comparar meses por día y no por total**. Un mes con
  pocos datos de clima parece un mes sin sol, y el gráfico mentiría justo donde se ve más.

## Por decidir
- [ ] ¿Se muestra el cargo fijo del operador, que no desaparece aunque se genere todo?
- [ ] ¿Qué se muestra cuando el consumo viene del recibo y no hay doce meses de historia?

## Relacionado
[[adr-0014-dimensionar-por-necesidad-y-mi-sistema]] · [[adr-0020-consumo-por-equipos-o-por-recibo]] ·
[[financiacion-cuota-vs-ahorro]] · [[tarifa-por-estrato-de-air-e]] ·
[[problema-y-propuesta-de-valor]] · [[nuevas-funcionalidades]]
