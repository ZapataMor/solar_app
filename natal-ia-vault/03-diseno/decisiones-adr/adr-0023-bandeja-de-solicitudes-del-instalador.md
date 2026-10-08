---
tipo: adr
descripcion: ADR-0023 — El instalador entra a la plataforma y responde las solicitudes que recibe
estado: 🟢 Aceptada
actualizado: 2026-10-05
---

# ADR-0023 · Bandeja de solicitudes del instalador

- **Estado:** 🟢 Aceptada
- **Fecha:** 2026-10-05
- **Contexto del repo:** `app/Actions/Installers`, `InstallerInboxController`, `installers/inbox.blade.php`,
  rol `installer` en `users.role`, `users.installer_id`, columnas nuevas de `quote_requests`

## Contexto
[[adr-0022-directorio-de-instaladores-y-solicitudes]] dejó al instalador como un registro: el cliente
le pide cotización y alguien le pasa la solicitud por fuera de la app. Eso cierra el flujo del
cliente, pero no el del negocio: nadie sabe dentro de la plataforma si el instalador contestó, si
visitó o si cerró, y la comisión de [[adr-0005-marketplace-de-instaladores]] necesita exactamente ese
dato.

El rol `installer` estaba propuesto desde [[adr-0003-separar-datos-cliente-e-instalador]] y nunca se
construyó. Hoy solo existen `user` y `admin`.

## Decisión
- **El instalador entra con su propia cuenta.** Rol `installer` en `users.role` y `users.installer_id`
  apuntando a su empresa. Un administrador le crea la cuenta desde la ficha del instalador; **no hay
  registro público**, igual que en el ADR-0022.
- **Su pantalla es una bandeja**, en `/solicitudes`: las cotizaciones que le pidieron, la más nueva
  primero, con las abiertas antes que las cerradas.
- **Cada solicitud muestra la estimación**, no un formulario en blanco: municipio, tipo de inmueble,
  consumo al mes, área de techo, paneles que pide el consumo y presupuesto de referencia. Es lo que
  el ADR-0022 le prometió al cliente: *llegas con todo calculado*.
- **El diario de equipos va plegado, un espacio por tarjeta** y en dos columnas. Una casa con veinte
  equipos era una lista plana de dos pantallas. El encabezado de cada tarjeta dice sus kWh, cuántos
  equipos tiene y su porcentaje —con una barra, para compararlos sin abrir ninguno— y **solo se abre
  el espacio más grande** (`biggestSpace` de `BuildConsumptionDiary`), que es el que decide el
  sistema. Se pliega con `<details>`, como los *Precios por municipio* del
  [[adr-0024-precios-por-municipio-administrables]]: sin JavaScript también funciona.
- **Los datos de contacto del cliente** (nombre y correo de su cuenta) se muestran junto a la
  solicitud. El cliente ya decidió contactarlo al pedir la cotización.
- **El instalador mueve el estado:** contactada, ganada o perdida (`QuoteRequestStatus`, que ya
  existía sin que nada lo escribiera). Al marcarla ganada **anota el valor del contrato**; sin ese
  valor el dominio no deja cerrarla como ganada.
- **No se calcula ninguna comisión.** El porcentaje sigue sin definirse ([[modelo-de-ingresos]]);
  guardar el valor es lo que hará falta el día que se decida.
- **El instalador no ve nada más:** ni otros proyectos, ni el resto del directorio, ni las pantallas
  de administración. Solo las solicitudes de su empresa.

## Consecuencias
- ➕ El lead del ADR-0005 deja de ser una fila muerta: tiene estado, fecha de respuesta y valor.
- ➕ La demo del pitch puede iniciar sesión como instalador y mostrar el otro lado del flujo.
- ➖ Aparece un tercer rol: cada pantalla nueva tiene que decidir si lo deja entrar.
- ⚠️ El cliente solo tiene correo en su cuenta. Para que el instalador lo llame hay que pedirle un
  teléfono al crear el proyecto; hoy la app no lo pide.
- ⚠️ El valor del contrato lo escribe el instalador. Sigue dependiendo de su honestidad, como advertía
  el ADR-0005; la plataforma solo guarda lo que él declara.

## Alternativas consideradas
- **Una vista previa para el administrador**, sin rol ni login — se construye en una tarde, pero nadie
  de afuera puede responder y el estado del lead seguiría sin llenarse.
- **Avisarle por correo y que responda por fuera** — no necesita pantallas, pero la plataforma vuelve
  a quedar ciega al resultado, que es justo lo que esta decisión arregla.
- **Calcular ya la comisión** — exige fijar el porcentaje antes de tener un solo negocio cerrado.

## Relacionado
[[adr-0022-directorio-de-instaladores-y-solicitudes]] · [[adr-0005-marketplace-de-instaladores]] ·
[[adr-0003-separar-datos-cliente-e-instalador]] · [[modelo-de-ingresos]]
