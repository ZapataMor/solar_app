---
tipo: adr
descripcion: ADR-0028 — El cliente compara sus cotizaciones lado a lado
estado: 🟢 Aceptada
actualizado: 2026-10-07
---

# ADR-0028 · Comparador de cotizaciones

- **Estado:** 🟢 Aceptada
- **Fecha:** 2026-10-07
- **Contexto del repo:** `App\Domain\Installers\QuoteComparison`,
  `App\Actions\Installers\CompareProjectQuotes`, `InstallerController::compare`,
  `installers/comparison.blade.php`, `components/installers/compare-cell.blade.php`,
  `resources/css/quote-comparison.css`, ruta `installers.quotes.compare`

## Contexto
Hoy el cliente pide cotización a varios instaladores, y cada una se lee **por separado**: la tarjeta
del directorio muestra el precio y [[adr-0026-cotizacion-del-instalador]] le da su página con el
retorno recalculado. Al final de esa página hay una lista de *lo que te ofrecieron los demás*, pero
es una lista de enlaces: para comparar hay que abrir una, recordar, volver y abrir otra.

Comparar es justamente el momento en que el cliente decide, y es donde la app puede hacer lo que un
PDF no hace. Con el [[adr-0027-datos-de-una-cotizacion-real]] ya hay con qué: las cotizaciones traen
los mismos campos, no textos libres distintos.

El consejo que se repite en el mercado colombiano es *pide al menos tres cotizaciones*. Nadie dice
cómo compararlas, y el total engaña: la más barata suele ser la que deja afuera el trámite ante el
operador de red y el medidor bidireccional, que juntos son varios millones.

## Decisión
- **Una pantalla de comparación por proyecto**, con todas las cotizaciones recibidas en columnas y
  los campos del ADR-0027 en filas. Se llega desde el directorio y desde cualquier cotización.
- **Las filas son las que deciden**, en este orden: precio total, precio por kW, retorno con ese
  precio (`Profitability::paybackYearsFor`), lo que cubre (RETIE, trámite, medidor, baterías,
  mantenimiento), garantías, anticipo y plazo, y validez.
- **La app marca lo mejor de cada fila, no la mejor cotización.** El retorno más rápido, la garantía
  más larga, el plazo más corto. Nunca una recomendación general: quién gana depende de lo que el
  cliente valore, y el [[adr-0005-marketplace-de-instaladores]] cobra por el cierre, así que la app
  no puede inclinar la balanza.
  > **Superado por [[adr-0029-recomendacion-y-cara-a-cara]] (8 de octubre de 2026):** la app sí
  > recomienda, con razones comprobables y una regla escrita. El marcado por fila sigue igual y es
  > el insumo de esa recomendación.
- **Lo que falta se ve tanto como lo que está.** Una celda vacía se muestra como *no lo dice*, no
  como un cero ni como un guion discreto: una cotización que no declara garantías es información.
- **El total se compara con una advertencia**, no a secas: si una cubre la legalización y otra no,
  la pantalla lo dice encima de los números.
  > **Ajustado el 8 de octubre de 2026:** la advertencia dejó de ser un bloque propio —cinco avisos
  > encima de la tabla eran más letra de la que nadie lee— y se dice donde se usa: la legalización,
  > dentro de la recomendación del [[adr-0029-recomendacion-y-cara-a-cara]]; el IVA, junto al nombre
  > de la columna; la vencida, en la suya. Advertir sigue siendo obligatorio; hacerlo en un párrafo
  > aparte, no.
- **Sin dos cotizaciones no hay pantalla.** Con una sola, el enlace no aparece y el cliente se queda
  en su página de detalle.
- **Nada de esto crea un estado nuevo.** Comparar no es aceptar: aceptar sigue siendo hablar con el
  instalador, y el cierre lo marca él desde su bandeja ([[adr-0023-bandeja-de-solicitudes-del-instalador]]).

## Lo que se decidió al construirlo
Preguntas que la decisión no respondía y que el código tuvo que responder. Todas salen de la misma
regla: la app marca lo mejor de cada fila y no inclina la balanza.

- **No se marca nada si no hay con qué comparar.** Con una sola candidata en la fila —porque las
  demás no lo dicen o están vencidas— la insignia significaría *la única que lo dice*, no *la mejor*.
  Y cuando todas dicen lo mismo tampoco se marca: una insignia en cada celda no informa.
- **La fila que nadie declara sale de la tabla**, pero su ausencia no se pierde: se nombra una vez
  en *Lo que ninguna dice*. Una fila con tres *no lo dice* es ruido; la pregunta que falta, no.
- **El retorno no es un silencio del instalador.** Sin cálculo del proyecto la fila desaparece con
  su propio aviso y el enlace para calcular, porque ahí quien no sabe es la app
  (`QuoteComparison::of($quotes, payback: false)`).
- **Las columnas van de la más barata a la más cara, y las vencidas al final**, porque un precio
  vencido ya no es un precio. Es el único orden que la app impone, y es el del total.
- **El sistema que propone cada uno cierra la tabla sin marcas** (potencia, paneles, inversor,
  baterías, producción): más kW no es mejor, es distinto, y es lo que explica las diferencias de
  precio de las filas de arriba.
- **En celular son columnas desplazables, no tarjetas apiladas.** Las etiquetas de fila quedan
  pegadas a la izquierda mientras las columnas se mueven: un valor sin su etiqueta no compara nada,
  y apilar tarjetas devuelve al cliente justo a lo que vino a evitar, comparar de memoria. El
  encabezado no se queda fijo —un contenedor que se desplaza de lado no puede además fijar su
  cabecera—, así que el nombre del instalador vuelve al pie, junto a la forma de escribirle.

## Consecuencias
- ➕ El cliente decide con el mismo criterio con que la app dimensionó su sistema, en lugar de elegir
  el total más bajo.
- ➕ Presiona hacia arriba la calidad de las cotizaciones: el instalador que no declara garantías se
  ve al lado del que sí.
- ➖ Es la primera pantalla de la app con tabla ancha; en celular hay que resolverla con columnas
  desplazables o tarjetas apiladas.
- ⚠️ Comparar cotizaciones con distinto alcance sigue siendo comparar peras con manzanas. La pantalla
  lo advierte, pero no lo puede impedir.
- ⚠️ Si una cotización está vencida, su precio ya no es un precio. Hay que mostrarla igual —el
  cliente la pidió— marcada como vencida y fuera de los "mejores de la fila".

## Alternativas consideradas
- **Dejarlo en la lista de enlaces que ya existe** — cuesta cero y es lo que hay hoy. Obliga al
  cliente a comparar de memoria, que es precisamente donde el total gana sobre el alcance.
- **Un puntaje por cotización** — una sola cifra para ordenarlas. Es cómodo y es exactamente lo que
  la app no debe hacer: inventa pesos que nadie acordó y convierte una decisión del cliente en una
  recomendación de la plataforma que además cobra comisión.
- **Exportar la comparación a PDF** — útil para mostrársela a alguien más, pero es un extra sobre la
  pantalla, no un reemplazo. Queda para después de que la pantalla exista.

## Relacionado
[[adr-0027-datos-de-una-cotizacion-real]] · [[adr-0026-cotizacion-del-instalador]] ·
[[adr-0022-directorio-de-instaladores-y-solicitudes]] · [[adr-0005-marketplace-de-instaladores]]
