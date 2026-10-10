---
tipo: idea
descripcion: Preguntarle al cliente si cerró y por cuánto, en vez de confiar en que el instalador lo declare
estado: 🟡 Candidata
esfuerzo: ~medio día con las notificaciones ya hechas
creado: 2026-10-10
tags: [idea, negocio, instaladores]
---

# Verificar el cierre preguntándole al cliente

**Qué es:** a los días de que el cliente vio una cotización, la app le pregunta *"¿cerraste con X? ¿por
cuánto?"*. La respuesta es la que registra el negocio.

## Por qué
La comisión del 1-3 % del [[modelo-de-ingresos]] depende hoy de que **el instalador declare** el
cierre, y el [[adr-0005-marketplace-de-instaladores]] ya anotó la debilidad. El
[[adr-0030-anonimato-por-etapas-y-cupos]] la deja en pie a propósito y nombra este camino: **el
cliente no tiene ningún incentivo para ocultar que cerró**, y el instalador sí para ocultar que ganó.

La diferencia de fondo es cuál de los dos vigilas. Vigilar al instalador es una carrera que se pierde
—siempre podrá no reportar—; preguntarle al cliente es simplemente obtener el dato de quien no lo
está escondiendo.

Hay un segundo beneficio, más grande que la comisión: es la **única señal de si la plataforma
funciona**. Cuántas solicitudes terminan en instalación, a qué precio real contra el estimado, qué
instalador cumple. Sin eso, no se sabe si el producto sirve.

## Viabilidad
- **Dónde va:** una notificación encolada y una pantalla de una pregunta (o un enlace firmado que no
  exija entrar). Se apoya en [[notificaciones-de-la-plataforma]]: con eso hecho, ~medio día.
- **Riesgo:** bajo en código. El riesgo es de respuesta: mucha gente no contesta. Una sola pregunta,
  en un enlace que no pida contraseña, es lo que más sube la tasa.
- **Ojo:** no convertirlo en encuesta de satisfacción. Dos preguntas máximo, y la plata primero.

## Por decidir
- [ ] A los cuántos días se pregunta, y si se repite una vez.
- [ ] Qué pasa si el cliente dice que cerró y el instalador no lo declaró: ¿se le cobra igual?
- [ ] Qué pasa con un negocio que nadie marca como cerrado (ya abierto en el
      [[adr-0030-anonimato-por-etapas-y-cupos|ADR-0030]]).

## Relacionado
[[adr-0005-marketplace-de-instaladores]] · [[adr-0030-anonimato-por-etapas-y-cupos]] ·
[[adr-0023-bandeja-de-solicitudes-del-instalador]] · [[modelo-de-ingresos]] ·
[[notificaciones-de-la-plataforma]] · [[nuevas-funcionalidades]]
