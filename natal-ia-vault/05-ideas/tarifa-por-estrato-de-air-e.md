---
tipo: idea
descripcion: Usar la tarifa real de Air-e por estrato y subsidio, en vez de la tarifa de referencia por tipo de lugar
estado: ❄️ Aplazada — el mantenimiento no compensa la ganancia
esfuerzo: medio, pero con carga mensual permanente
creado: 2026-10-10
tags: [idea, aplazada, calculo]
---

# Tarifa por estrato de Air-e

**Qué es:** en lugar de una tarifa de referencia por tipo de lugar, la app usaría la tarifa real del
operador de La Guajira según el **estrato** del cliente, con su subsidio o su contribución.

## Por qué suena bien
La tarifa es el multiplicador de todo el ahorro, y por lo tanto del retorno: es el número del cálculo
más sensible. Y en Colombia no es uno solo —el estrato 1 subsidiado y el estrato 6 con contribución
pagan muy distinto por el mismo kWh—, así que una tarifa única reparte error en ambos sentidos. Decir
*"usamos la tarifa de tu estrato"* suena a precisión.

## Por qué está aplazada
**La ganancia real es pequeña y el costo es permanente.**

- El cliente **ya puede escribir la tarifa de su recibo** (`solar_projects.energy_rate_cop_kwh`, con
  `ownEnergyRate()`), y cuando lo hace es más exacto que cualquier tabla: es su recibo.
- Una tabla de tarifas por estrato **hay que actualizarla todos los meses**, igual que los precios de
  equipos que ya se advierten en el [[modelo-de-ingresos]]. Es una deuda de operación para siempre, no
  un desarrollo que se termina.
- Una tarifa oficial desactualizada es **peor** que una referencia declarada como referencia, porque
  se presenta con una precisión que no tiene.

Lo que sí vale casi gratis: **insistir en pantalla en que escriba la tarifa de su recibo**, que es el
dato bueno, y explicar que la referencia es solo un punto de partida.

## Qué la desbloquearía
- Una fuente de tarifas **consultable por API**, no un PDF mensual. Sin eso, es trabajo manual
  disfrazado de funcionalidad.
- Que se compruebe que los clientes **no** escriben su tarifa y por eso el cálculo sale desviado. Si
  la escriben, el problema no existe.

## Relacionado
[[adr-0015-valores-de-referencia]] · [[adr-0020-consumo-por-equipos-o-por-recibo]] ·
[[recibo-antes-y-despues]] · [[modelo-de-ingresos]] · [[valores-de-referencia-candidatos]] ·
[[nuevas-funcionalidades]]
