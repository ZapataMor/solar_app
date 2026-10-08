---
tipo: adr
descripcion: ADR-0026 — El instalador responde con su cotización y el cliente la ve en la app
estado: 🟢 Aceptada
actualizado: 2026-10-05
---

# ADR-0026 · Cotización del instalador

- **Estado:** 🟢 Aceptada
- **Fecha:** 2026-10-05
- **Contexto del repo:** tabla `installer_quotes`, `SendInstallerQuote`, `DescribeQuoteForClient`,
  `InstallerInboxController`, `InstallerController::quote`, `installers/quote-request.blade.php`,
  `installers/quote.blade.php`, `installers/index.blade.php`

## Contexto
La pantalla se llama *solicitud de cotización*, pero hasta hoy el instalador no podía mandar
ninguna. Con [[adr-0023-bandeja-de-solicitudes-del-instalador]] ve el proyecto y marca si contactó,
ganó o perdió; el precio se negocia por fuera y la app nunca lo sabe.

Eso deja al cliente sin lo único que fue a buscar. El ADR-0022 le prometió *llegas sabiendo qué
necesitas y cuánto debería costar*, y el presupuesto que ve es el de referencia de su municipio, no
una oferta de nadie.

## Decisión
- **El instalador responde con una cotización** desde la página de la solicitud: cuánto cuesta, qué
  incluye, si lleva baterías, la potencia que propone y hasta cuándo vale el precio.
- **Vive en su propia tabla, `installer_quotes`**, una por solicitud. No va en columnas de
  `quote_requests` porque ahí ya está `quoted_cost_cop`, que es el presupuesto *de referencia* de la
  app congelado ([[adr-0024-precios-por-municipio-administrables]]): dos números con nombres
  parecidos y significados distintos en la misma fila es pedir una confusión.
- **Estado nuevo `quoted`.** Mandar la cotización lo pone solo; sigue contando como abierta, porque
  ahora la pelota la tiene el cliente.
- **Una cotización por solicitud.** Volver a mandarla corrige la anterior y mueve su fecha; no se
  guarda historial de versiones, porque lo que importa es la oferta vigente.
- **El precio vence.** Los equipos son importados y el precio se mueve con el dólar
  ([[modelo-de-ingresos]]): la fecha de validez es obligatoria y la app dice cuando ya pasó.
- **El cliente la ve en el directorio**, en la tarjeta de ese instalador, junto a los datos de
  contacto que ya se le revelaron al pedirla.
- **Y la abre en su propia página** (`/instaladores/cotizaciones/{solicitud}`), porque una cifra en
  una tarjeta no se revisa: ahí está el precio, qué incluye, hasta cuándo vale, y las cuentas.
- **La app rehace el retorno con *ese* precio.** Con el ahorro anual que ya calculó el proyecto,
  `Profitability::paybackYearsFor()` dice en cuánto se paga esa cotización y con qué veredicto
  ([[adr-0014-dimensionar-por-necesidad-y-mi-sistema]]): es la pregunta que un número solo no responde, y el
  mismo sistema de dos instaladores no se paga a la misma velocidad. Sin cálculo del proyecto no hay
  ahorro, así que la página lo dice en vez de inventarlo.
- **Compara, pero no recomienda.** Muestra la diferencia contra el presupuesto de referencia
  congelado ([[adr-0024-precios-por-municipio-administrables]]), el precio por kW y lo que
  ofrecieron los demás instaladores del mismo proyecto. No dice cuál aceptar: advierte que dos
  cotizaciones solo se comparan si llevan lo mismo (baterías, kW) y deja la decisión al cliente.
- **Quien puede ver la cotización es quien puede manejar el proyecto** (`SolarProjectPolicy::manage`).
  Una solicitud sin precio todavía no tiene página: responde 404.
- **Se lee por pasos, no en scroll.** Con el detalle del [[adr-0027-datos-de-una-cotizacion-real]] la
  página pasó a siete tarjetas y el precio se perdía de vista. El precio queda fijo arriba y lo demás
  es un paso a la vez: *¿te conviene?*, *cómo se compara*, *qué cubre*, *garantías*, *antes de firmar*.
  Los pasos son enlaces de verdad (`#paso-…`), así que el teclado, el botón atrás y un enlace
  compartido siguen funcionando, y **sin JavaScript la página simplemente se lee de arriba abajo**
  (la clase `solar-js` de `<html>`, puesta antes del primer pintado, es la que esconde los demás).

## Consecuencias
- ➕ El círculo cierra dentro de la app: pedir cotización sirve para recibir una cotización.
- ➕ Queda registrado lo que se ofreció y cuándo, que es la mitad de la atribución que el ADR-0005
  necesita; la otra mitad, el valor del cierre, ya la guarda el ADR-0023.
- ➕ El cliente compara ofertas con el mismo criterio con que la app dimensionó su sistema, en vez de
  elegir por el total más bajo.
- ➖ El instalador tiene que escribir su precio a mano cada vez; no hay catálogo de ítems suyo
  ([[adr-0004-cotizacion-por-items-y-transporte-interno]] sigue sin construirse).
- ⚠️ Al cliente no le llega aviso de que le cotizaron: se entera al entrar al directorio.
- ⚠️ Sin historial de versiones, corregir una cotización borra lo que decía antes.
- ⚠️ El retorno de la página se apoya en el ahorro del último cálculo del proyecto: si el cliente
  cambia su consumo y recalcula, el mismo precio cambia de veredicto.

## Alternativas consideradas
- **Cotización por ítems** (ADR-0004) — es lo que el asesor pidió a futuro, pero obliga a que cada
  instalador cargue y mantenga sus precios por equipo antes de poder responder nada.
- **Columnas en `quote_requests`** — una tabla menos, pero deja `quoted_cost_cop` y el precio del
  instalador lado a lado, que es justo la confusión que hay que evitar.
- **Solo un número, sin alcance ni validez** — más rápido de escribir, pero una cifra sin decir qué
  incluye no se puede comparar con otra, y comparar es el paso siguiente.

## Relacionado
[[adr-0023-bandeja-de-solicitudes-del-instalador]] · [[adr-0022-directorio-de-instaladores-y-solicitudes]] ·
[[adr-0024-precios-por-municipio-administrables]] · [[adr-0005-marketplace-de-instaladores]]
