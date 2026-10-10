---
tipo: idea
descripcion: Que el instalador use el dimensionamiento con sus propios clientes, no solo para responder solicitudes
estado: 🟡 Candidata
esfuerzo: medio-alto
creado: 2026-10-10
tags: [idea, negocio, instaladores]
---

# La calculadora como herramienta del instalador

**Qué es:** el instalador puede crear proyectos **suyos** —clientes que consiguió por su cuenta— y
dimensionar, estimar y cotizar con la app, no solo responder las solicitudes que le llegan.

## Por qué
**Resuelve el problema más grave del modelo de cobro.** La suscripción de 50.000 al mes del
[[modelo-de-ingresos]] solo vale si llegan clientes, y el propio
[[adr-0030-anonimato-por-etapas-y-cupos]] lo advierte: *con pocos instaladores al principio, cobrar
por abrir puede secar la red*. Es el huevo y la gallina —sin clientes no hay instaladores, sin
instaladores no hay a quién pedirle cotización— y la herramienta lo rompe: **la suscripción se paga
sola desde el día uno**, aunque el directorio esté vacío, porque le ahorra al instalador el Excel con
el que cotiza hoy.

Y hay un efecto de arrastre: el instalador que dimensiona en la app mete ahí sus proyectos, sus
precios y sus clientes. Eso es retención real, no un listado donde aparece.

## Viabilidad
- **Esfuerzo: medio-alto.** No es una pantalla nueva, es permisos: hoy un proyecto tiene un dueño
  cliente y la autorización es `SolarProjectPolicy::manage` (dueño o admin). Un instalador con
  proyectos propios obliga a revisar esa policy, el rol `installer` y el sidebar, que hoy le oculta
  *Centro solar* ([[adr-0023-bandeja-de-solicitudes-del-instalador]]).
- **Lo que ya sirve tal cual:** todo el cálculo, el diario de equipos, el 3D y el presupuesto. No hay
  dominio nuevo; es acceso.
- **Riesgo:** confundir los dos mundos. Un proyecto del instalador **no** debe entrar al directorio ni
  generar solicitudes a la competencia, y sus precios no deberían contaminar los
  [[adr-0024-precios-por-municipio-administrables|precios de referencia]]. Si se mezclan, se rompe la
  separación del [[adr-0003-separar-datos-cliente-e-instalador]].

## Por decidir
- [ ] ¿Entra en el plan básico o es lo que justifica un plan superior?
- [ ] ¿Puede el instalador marcar un proyecto propio como "ya instalado" y alimentar datos reales?
- [ ] ¿Lo ve el cliente final de ese instalador, o es una herramienta interna suya?

## Relacionado
[[adr-0003-separar-datos-cliente-e-instalador]] · [[adr-0005-marketplace-de-instaladores]] ·
[[adr-0023-bandeja-de-solicitudes-del-instalador]] · [[adr-0030-anonimato-por-etapas-y-cupos]] ·
[[modelo-de-ingresos]] · [[clientes-y-usuarios]] · [[nuevas-funcionalidades]]
