---
tipo: adr
descripcion: ADR-0029 — La app recomienda una cotización, y el cliente la enfrenta con otra
estado: 🟢 Aceptada
actualizado: 2026-10-08
---

# ADR-0029 · Recomendación y cara a cara

- **Estado:** 🟢 Aceptada — **cambia una decisión del [[adr-0028-comparador-de-cotizaciones]]**
- **Fecha:** 2026-10-08
- **Contexto del repo:** `QuoteComparison::faceOff()`, `::verdict()` y `::recommendation()`,
  `InstallerQuote::comparisonValues()`, `DescribeQuoteForClient::faceOff()`,
  `components/installers/face-off.blade.php`, `components/installers/compare-delta.blade.php`,
  `installers/comparison.blade.php`

## Contexto
El [[adr-0028-comparador-de-cotizaciones]] puso las cotizaciones en columnas y marcó lo mejor de
cada fila. Pero decidió, explícitamente, **no recomendar**: *"quién gana depende de lo que el
cliente valore, y el ADR-0005 cobra por el cierre, así que la app no puede inclinar la balanza"*.

En la práctica eso deja al cliente con una tabla de dieciséis filas y la misma pregunta con la que
llegó: *¿cuál cojo?*. Un cliente que pide tres cotizaciones de un sistema solar casi nunca sabe que
el trámite con el operador de red cuesta millones; la tabla se lo muestra, pero no se lo dice.
Negarse a responder no es neutralidad: es devolverle el problema al que menos sabe del tema.

Además, el ADR-0028 ya reconocía que la tabla ancha es incómoda, y hay un momento donde el cliente
no quiere dieciséis filas por cinco columnas: cuando está leyendo **una** cotización y quiere saber
cómo queda frente a otra. Ahí lo que sirve es un cara a cara, como el de un comparador de fichas
deportivas: dos columnas, el atributo en el medio y cuánto le saca cada uno al otro.

## Decisión
- **La app recomienda una cotización**, con nombre y precio, arriba de la tabla. Cambia el punto del
  ADR-0028 que lo prohibía; todo lo demás de ese ADR sigue en pie.
- **La recomendación se argumenta con razones comprobables**, nunca con un puntaje. Cada razón es
  una fila que esa cotización gana **sola** ("es la única que trae el RETIE", "se paga en menos
  tiempo"), así que el cliente puede bajar a la tabla y verificarla.
- **La regla está escrita, no escondida en pesos:** no se recomienda una cotización vencida; no se
  recomienda una que deja afuera la legalización mientras otra la cubra —ese trámite lo paga el
  cliente igual—; y entre las que quedan gana la que **más filas gana**, a igualdad la que **más
  datos declara**, y a igualdad la más barata.
- **Si la recomendada no es la más barata, la app lo dice primero**, con la diferencia en pesos y
  con qué deja afuera la más barata. El cliente no puede sentir que se le escondió el precio.
- **Cada cotización lleva su veredicto en dos cuentas:** cuántas filas gana de las que se comparan y
  cuántos datos declara de los que podía declarar. Son recuentos, no una nota: se pueden rehacer a
  mano.
- **Cada celda dice cuánto le saca a la mejor de las demás** (`+13 años`, `−7,6 M`). Con dos
  cotizaciones se lee como el cara a cara del diseño; con cinco, la ganadora muestra lo que le saca
  a la segunda.
- **El cliente elige cuáles entran.** Casillas en la pantalla y una ✕ por columna; la elección viaja
  en la URL, así que el enlace se puede compartir y el botón de atrás funciona. Con menos de dos
  marcadas se comparan todas, porque una columna no compara nada.
- **La página de una cotización trae el cara a cara** (`faceOff`): esta cotización contra otra del
  mismo proyecto, con el precio, el precio por kW, el retorno y los cinco checks de lo que cubre.
  Solo eso: la tabla entera está a un clic.
- **El cara a cara recomienda únicamente cuando esas dos son todas las cotizaciones del proyecto.**
  Con tres o más, recomendar sobre dos nombraría un ganador que la tabla no respalda.

## Consecuencias
- ➕ El cliente obtiene una respuesta, no solo datos, y puede discutirla: cada razón está en su fila.
- ➕ La regla premia declarar. Al instalador le conviene llenar la cotización completa y cubrir la
  legalización, que es exactamente la calidad que el ADR-0027 quería empujar.
- ➖ **La plataforma cobra comisión por el cierre ([[adr-0005-marketplace-de-instaladores]]) y ahora
  recomienda.** Es un conflicto de interés real, y no desaparece porque la regla sea pública. Se
  mitiga con tres cosas que no hay que quitar: las razones son verificables, la app dice cuándo su
  recomendación no es la más barata, y la regla no mira ninguna comisión. Si algún día la comisión
  varía por instalador, hay que volver a este ADR.
- ⚠️ La recomendación depende de lo que cada instalador **declare**, no de lo que haga. Quien llena
  bien el formulario sale favorecido frente a quien instala mejor pero escribe menos.
- ⚠️ El cara a cara solo existe para dos. Con tres o más el diseño vuelve a columnas: son dos
  pantallas con el mismo lenguaje visual que hay que mantener a la par.

## Alternativas consideradas
- **Un puntaje global por cotización, tipo 91 vs 94** — es lo más legible y lo que inspiró el
  diseño. Se descartó porque esconde los pesos: nadie acordó cuánto vale un año de garantía contra
  un millón de pesos, y una cifra inventada por la plataforma que cobra comisión no se puede
  discutir. Las dos cuentas del veredicto dan lo mismo sin inventar nada.
- **Dejar solo el marcado por fila del ADR-0028** — es lo que había, y es honesto, pero le devuelve
  la pregunta al cliente.
- **Pedirle al cliente sus prioridades y ordenar con ellas** — la recomendación sería suya, no de la
  app. Es mejor que un puntaje fijo, pero pide trabajo justo cuando el cliente quiere una respuesta,
  y los pesos que ponga sin saber del tema tampoco valen más. Queda para después, como un ajuste
  encima de esta recomendación.

## Relacionado
[[adr-0028-comparador-de-cotizaciones]] · [[adr-0027-datos-de-una-cotizacion-real]] ·
[[adr-0026-cotizacion-del-instalador]] · [[adr-0005-marketplace-de-instaladores]]
