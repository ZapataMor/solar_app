---
tipo: adr
descripcion: ADR-0030 — El contacto se revela por etapas y mirar una solicitud gasta un cupo del plan
estado: 🟡 Propuesta
actualizado: 2026-10-09
---

# ADR-0030 · Anonimato por etapas y cupos de la suscripción

- **Estado:** 🟡 Propuesta — **sin construir**, para revisar con el asesor después del pitch
- **Fecha:** 2026-10-09
- **Contexto del repo (previsto):** `DescribeQuoteRequest`, `DescribeInstallerDirectory`,
  `DescribeInstallerInbox`, `installers/index.blade.php`, `installers/quote-request.blade.php`,
  tablas `installers` (plan), `quote_requests` (desbloqueo) y una nueva de movimientos de cupos

## Contexto
El [[modelo-de-ingresos]] cobra de dos maneras: **suscripción** (unos 50.000 COP al mes por aparecer
recomendado) y **comisión** del 1 al 3 % del contrato cuando el negocio se cierra en la plataforma.
El [[adr-0005-marketplace-de-instaladores]] ya anotó la debilidad: *depende de que el instalador
reporte el cierre honestamente*.

El riesgo concreto es la **desintermediación**: el instalador toma los datos del cliente desde la app,
cierra por fuera y no paga comisión. Hoy la app lo hace fácil **en los dos sentidos**:

- El instalador ve **nombre y correo del cliente** en cuanto recibe la solicitud
  ([[adr-0023-bandeja-de-solicitudes-del-instalador]]).
- El cliente ve **WhatsApp, teléfono y correo del instalador** en cuanto pide la cotización
  ([[adr-0022-directorio-de-instaladores-y-solicitudes]]).

La primera idea fue **ocultarle los datos del cliente al instalador y que todo pase por soporte**. Se
evaluó y se descartó (ver *Alternativas consideradas*): no cierra la fuga —el cliente sigue viendo el
WhatsApp del instalador, y el nombre de la empresa basta para encontrarla— y convierte al soporte en
un relevo humano de decenas de mensajes por negocio, justo donde la promesa es *llegas con todo
calculado*.

Lo que sí mueve la aguja no es esconder el dato, es **cobrar antes del cierre**, que es el único
momento en que la plataforma todavía tiene palanca.

## Decisión

### 1. El contacto se revela por etapas, y revelar es registrar

| Etapa | El instalador ve | El cliente ve |
|---|---|---|
| Directorio, antes de pedir | nada | empresa, años, cobertura y precios de referencia — **sin contacto** |
| Solicitud recibida | el proyecto, con el cliente como **alias** (*"Cliente de Riohacha"*) | que la solicitud llegó |
| Cotización enviada | igual | la cotización y el comparador ([[adr-0028-comparador-de-cotizaciones]]) |
| **El cliente elige esa cotización** | **contacto del cliente** | **contacto del instalador** |

**El momento de revelar es el momento de anotar el negocio**, con su precio congelado. Nadie tiene los
datos del otro sin que quede registrado qué se acordó y por cuánto. El instalador acepta el cobro
antes de recibir el dato, no después.

### 2. La suscripción tiene planes, y cada plan trae cupos
- Varios planes; **entre más caro, más cupos al mes**.
- **Un cupo se gasta al abrir una solicitud** en detalle y poder cotizarla.
- El objetivo no es recaudar por cupo: es que el instalador **se lo piense antes de abrir todas las
  solicitudes**. Con cupos escasos elige las que de verdad puede atender, y el cliente recibe menos
  cotizaciones pero mejores.

### 3. Qué se ve sin gastar un cupo
Para que el instalador pueda elegir, la lista muestra **sin costo**: municipio, tipo de inmueble,
consumo al mes, área de techo, paneles que pide el consumo, presupuesto de referencia y la fecha.
Lo que queda detrás del cupo es el **detalle**: el diario de equipos del cliente
([[adr-0023-bandeja-de-solicitudes-del-instalador]]) y el formulario para cotizar.

Esto no es un adorno: **si la lista muestra de menos, el instalador no gasta cupos y nadie cotiza**, y
un directorio donde nadie responde es peor que uno que se fuga.

### 4. La comisión se queda como está
El 1–3 % del [[adr-0005-marketplace-de-instaladores]] sigue igual **por ahora**, declarado por el
instalador al marcar el negocio como ganado. El anonimato por etapas y los cupos no lo reemplazan: lo
acompañan. Si más adelante se quiere verificar el cierre, el camino es **preguntarle al cliente**, que
no tiene ningún incentivo para ocultarlo, y no vigilar al instalador.

### 5. Lo que no se oculta
- **El nombre de la empresa, al cliente.** Es lo que lo hace confiar y pedir la cotización; esconderlo
  mata el embudo para ganar un candado que se abre con una búsqueda en Google.
- **El proyecto, al instalador.** Consumo, techo y municipio son lo que le permite cotizar; sin eso la
  solicitud no sirve y el cupo no vale nada.

## Consecuencias
- ➕ La plataforma cobra **antes** del cierre, así que un negocio que se va por fuera ya dejó ingreso.
- ➕ El instalador elige en vez de barrer: menos cotizaciones de relleno y más atención por cliente.
- ➕ Queda un registro de qué se acordó y por cuánto, que es la atribución que el ADR-0005 necesita.
- ➕ Es mejor privacidad para el cliente, no solo defensa comercial: su correo deja de viajar a cinco
  empresas por el solo hecho de pedir un precio.
- ➖ Un cupo se gasta **antes** de saber si vale la pena cotizar. Hay que vigilar la tasa de respuesta:
  si cae, la lista está mostrando de menos y hay que abrir más datos gratis.
- ➖ Los cupos necesitan contabilidad: saldo, periodo, qué pasa con los que sobran y qué ve el
  instalador cuando se le acaban.
- ⚠️ **Esto no impide la fuga, la encarece.** Dos partes que ya se conocen cierran por fuera igual; lo
  que cambia es que la plataforma ya cobró y que irse deja rastro.
- ⚠️ La comisión sigue dependiendo de que el instalador declare el cierre: la debilidad del ADR-0005
  sobrevive intacta.
- ⚠️ Con pocos instaladores al principio, cobrar por abrir puede secar la red. Hace falta un plan
  gratuito o de cortesía mientras se llena el directorio.

## Alternativas consideradas
- **Ocultar los datos del cliente y que todo pase por soporte** (la idea original) — descartada. No
  cierra la fuga: el cliente sigue viendo el WhatsApp del instalador y el nombre de la empresa alcanza
  para encontrarla fuera de la app. Y una instalación necesita visita técnica, medidas, fotos y
  agenda: relevar eso a mano es un call center, mete horas de retraso donde la promesa es rapidez, y
  el que se frustra es el cliente, que no le debe nada a la plataforma.
- **Ocultarle al cliente el nombre del instalador** — descartada. Es el único dato que genera
  confianza antes de pedir; sin él no hay solicitudes que proteger.
- **Solo suscripción, sin comisión** — inmune a la fuga y lo más simple de vender y de operar, pero
  renuncia al ingreso que crece con el tamaño del negocio. Queda como plan B si la comisión resulta
  incobrable en la práctica.
- **Mensajería enmascarada dentro de la app** — es lo correcto a mediano plazo: deja hablar antes de
  elegir sin entregar datos, da el rastro y no cuesta personas. Se aplaza porque es tabla de mensajes,
  notificaciones y pantallas nuevas, y no cabe antes del pitch.
- **Penalizar al cliente ocultándole el contacto** — descartada: el cliente no es quien debe la
  comisión, y la fricción se la cobraría a quien la plataforma necesita retener.

## Por decidir antes de construir
- [ ] Cuántos planes, a qué precio y con cuántos cupos cada uno.
- [ ] Si los cupos sobrantes se acumulan al mes siguiente o se pierden.
- [ ] Qué ve y qué puede hacer un instalador sin cupos disponibles.
- [ ] Si el plan gratuito existe y por cuánto tiempo, mientras se llena el directorio.
- [ ] El porcentaje de la comisión, que sigue abierto desde el [[modelo-de-ingresos]].
- [ ] Qué pasa con un negocio que el cliente nunca marca como cerrado.

## Relacionado
[[adr-0005-marketplace-de-instaladores]] · [[adr-0022-directorio-de-instaladores-y-solicitudes]] ·
[[adr-0023-bandeja-de-solicitudes-del-instalador]] · [[adr-0026-cotizacion-del-instalador]] ·
[[adr-0028-comparador-de-cotizaciones]] · [[modelo-de-ingresos]] ·
[[adr-0003-separar-datos-cliente-e-instalador]]
