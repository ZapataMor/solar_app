---
tipo: idea
descripcion: Flujo de caja a 25 años con degradación del panel, inflación tarifaria y reemplazo del inversor
estado: 🟡 Candidata
esfuerzo: ~medio día la matemática; la tabla y la gráfica aparte
creado: 2026-10-10
tags: [idea, calculo, negocio]
---

# Proyección a 25 años

**Qué es:** en vez de un retorno en años, un flujo de caja año por año durante la vida del sistema,
con **degradación del panel** (~0,5 % al año), **inflación de la tarifa** y el **reemplazo del
inversor** hacia el año 12.

## Por qué
El retorno simple de hoy contesta la pregunta del cliente. Esto contesta la de **quien pone plata**:
un banco, un fondo o un jurado de convocatoria no pregunta "¿en cuántos años se paga?", pregunta por
el flujo y por los supuestos. Y hay un detalle que el payback simple esconde y que un evaluador
detecta de inmediato: **el inversor no dura 25 años**. Un retorno que ignora su reemplazo está
inflado, y que la app lo incluya sola es señal de seriedad.

La tarifa sube más rápido que la inflación general, así que la proyección **mejora** el caso en vez de
empeorarlo: el ahorro del año 15 vale más que el del año 1.

## Viabilidad
- **Dónde va:** dominio puro sobre `Profitability`, con test unitario. Los supuestos
  (degradación, inflación tarifaria, año y costo del reemplazo) son
  [[adr-0015-valores-de-referencia|valores de referencia]], no constantes.
- **Esfuerzo:** ~medio día la matemática. La tabla o la gráfica es trabajo aparte, y se puede dejar
  para después: el número que importa —retorno con reemplazo incluido— ya cabe en una línea.
- **Riesgo:** bajo de implementación. El riesgo es de presentación: 25 filas de tabla no las lee
  nadie. Sirve más el resumen (retorno corregido, ahorro total a 25 años) con el detalle plegado.

## Por decidir
- [ ] Qué degradación, qué inflación tarifaria y en qué año se reemplaza el inversor.
- [ ] Si las baterías entran con su propio reemplazo (~año 8) o se deja para cuando exista
      [[escenarios-on-grid-hibrido-off-grid|el escenario híbrido]].
- [ ] Si el retorno que se muestra por defecto pasa a ser el corregido, o convive con el simple.

## Relacionado
[[adr-0014-dimensionar-por-necesidad-y-mi-sistema]] · [[adr-0015-valores-de-referencia]] ·
[[financiacion-cuota-vs-ahorro]] · [[escenarios-on-grid-hibrido-off-grid]] ·
[[valores-de-referencia-candidatos]] · [[nuevas-funcionalidades]]
