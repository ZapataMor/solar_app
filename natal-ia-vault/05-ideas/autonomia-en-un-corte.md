---
tipo: idea
descripcion: Decir cuántas horas aguanta la casa en un corte, con el diario de equipos y los kWh de batería
estado: 🟢 Propuesta para el pitch
esfuerzo: ~3 horas la versión corta; ~1 día con equipos críticos
creado: 2026-10-10
tags: [idea, pitch, calculo]
---

# Autonomía en un corte

**Qué es:** a partir del diario de equipos y de los kWh útiles de batería, la app responde *"aguantas
6 horas con nevera, ventiladores y luces"*.

## Por qué
El dolor número uno en La Guajira **no es la tarifa, son los cortes**. El ahorro mensual es un
argumento de bolsillo; la autonomía es un argumento de miedo, y pesa más. Es también la pregunta que
el cliente hace primero cuando le mencionan baterías, y hoy la app no la contesta: las baterías solo
aparecen del lado del instalador, como algo que su cotización incluye o no
([[adr-0027-datos-de-una-cotizacion-real]]), nunca como una capacidad que el cliente entienda.

Ya está todo el insumo: el [[adr-0013-creacion-guiada-y-diario-de-consumo|diario]] tiene los equipos
por espacio, con su potencia y sus horas.

## Viabilidad
- **Dónde va:** `App\Domain\Solar\BackupAutonomy` (o junto a `SystemSizing`), dominio puro sobre el
  diario que ya existe, con su test unitario. Se pinta en *Mi sistema*.
- **Versión corta para el pitch (~3 horas):** sin marcar equipos críticos, con la carga nocturna del
  diario completo. Ya dice algo verdadero y alcanza para la demo.
- **Versión completa (~1 día):** marcar cada equipo del diario como crítico o no (migración en la
  tabla de equipos del proyecto + casilla en la hoja del diario) y calcular la autonomía solo con los
  críticos, que es como se dimensiona un respaldo de verdad.
- **Riesgo:** bajo. No toca el dimensionamiento ni la cotización; es un número nuevo sobre datos que
  ya están.

## ⚠️ Cuidado con el nombre
El asesor dijo **no mencionar todavía la alternancia entre la red y el sistema fotovoltaico**
([[ideas-aplazadas-asesoria]]). Esto **no es** esa alternancia —eso es una estrategia de operación y
de control— sino un resultado del dimensionamiento. Hay que llamarlo **respaldo** y nunca presentarlo
como algo que la app decide o conmuta sola.

## Por decidir
- [ ] De dónde salen los kWh útiles de batería: valor de referencia, dato que escribe el cliente, o
      lo que declare la cotización del instalador.
- [ ] Qué profundidad de descarga se asume, y si se muestra en pantalla.
- [ ] Si la versión del pitch lleva equipos críticos o la carga completa.

## Relacionado
[[adr-0013-creacion-guiada-y-diario-de-consumo]] · [[adr-0014-dimensionar-por-necesidad-y-mi-sistema]] ·
[[adr-0027-datos-de-una-cotizacion-real]] · [[ideas-aplazadas-asesoria]] ·
[[escenarios-on-grid-hibrido-off-grid]] · [[problema-y-propuesta-de-valor]] ·
[[nuevas-funcionalidades]]
