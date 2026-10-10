---
tipo: idea
descripcion: Mostrar la cuota mensual de un crédito al lado del ahorro mensual, para que el precio deje de ser un muro
estado: 🟢 Propuesta para el pitch
esfuerzo: ~medio día
creado: 2026-10-10
tags: [idea, pitch, calculo]
---

# Financiación: cuota contra ahorro

**Qué es:** junto al presupuesto del sistema, la app calcula la **cuota mensual** de un crédito a N
años y la pone al lado del **ahorro mensual** que ya calcula. La frase que resulta es *"tu cuota
sería 480.000 y tu ahorro 520.000: el sistema se paga solo"*.

## Por qué
Hoy el recorrido termina en *"esto cuesta 25 millones"* y ahí se acaba la conversación: nadie en La
Guajira tiene eso en efectivo. El cálculo ya es correcto y el retorno ya está, pero **el retorno a
seis años no resuelve la pregunta de quien no puede pagar el año uno**. La cuota sí: convierte una
cifra imposible en una comparación con lo que ya paga de luz todos los meses.

No cambia el dimensionamiento ni el presupuesto; es una lectura distinta de los mismos números.

## Viabilidad
- **Dónde va:** `App\Domain\Finance\Financing`, PHP puro (cuota fija de interés compuesto), con test
  en `tests/Unit/Domain` como el resto del dominio del cálculo. Se pinta en la pestaña *Mi sistema*
  (`system.blade.php`) y en la página de la cotización del instalador.
- **La tasa y el plazo son [[adr-0015-valores-de-referencia|valores de referencia]]**, no constantes:
  cambian con el mercado y el administrador tiene que poder moverlos sin tocar código. Van con
  vigencia e historial como los demás.
- **Esfuerzo:** ~medio día.
- **Riesgo:** usar una tasa inventada. Una cuota calculada con una tasa que ningún banco da es peor
  que no mostrar cuota, porque el cliente la lleva al banco y la app queda mintiendo. Hay que
  conseguir al menos una tasa real de referencia antes de mostrarla, y decir en pantalla que es
  **estimada** y de dónde sale.

## Por decidir
- [ ] Con qué tasa y qué plazo se calcula la cuota de referencia, y de qué fuente sale.
- [ ] Si se nombran líneas de crédito concretas (bancos, Bancóldex, FNA) o se deja genérico.

## Relacionado
[[adr-0014-dimensionar-por-necesidad-y-mi-sistema]] · [[adr-0015-valores-de-referencia]] ·
[[adr-0026-cotizacion-del-instalador]] · [[pitch-primera-etapa]] ·
[[problema-y-propuesta-de-valor]] · [[nuevas-funcionalidades]]
