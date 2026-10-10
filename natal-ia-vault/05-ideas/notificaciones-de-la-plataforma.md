---
tipo: idea
descripcion: Avisar cuando llega una solicitud, cuando llega una cotización y cuando una está por vencer
estado: 🟢 Propuesta para el pitch
esfuerzo: ~medio día
creado: 2026-10-10
tags: [idea, pitch, instaladores]
---

# Notificaciones de la plataforma

**Qué es:** tres avisos. Solicitud nueva → al instalador. Cotización nueva → al cliente. Cotización
por vencer → al cliente.

## Por qué
**El marketplace no funciona sin esto.** Hoy un instalador que no abre `/solicitudes` nunca se
enterará de que le llegó una solicitud, y un cliente que no vuelve al directorio nunca sabrá que ya
tiene precios para comparar. Están construidas la bandeja
([[adr-0023-bandeja-de-solicitudes-del-instalador]]), la cotización
([[adr-0026-cotizacion-del-instalador]]) y el comparador ([[adr-0028-comparador-de-cotizaciones]]), y
todo eso depende de que alguien entre por su cuenta.

Es además lo más fácil de que se caiga en vivo: si en el pitch nadie responde la solicitud de la
demo, la cadena se ve rota aunque el código esté bien.

El aviso de vencimiento no es un adorno: el precio **vence** a propósito, porque los equipos son
importados, y una cotización que se venció sin que el cliente la mirara es un negocio perdido por
silencio.

## Viabilidad
- **Dónde va:** notificaciones de Laravel encoladas; la cola ya corre en `composer dev` y el
  scheduler ya existe en `routes/console.php` para el aviso de vencimiento.
- **Esfuerzo:** ~medio día.
- **Riesgo:** bajo en código. El riesgo es de entrega —que el correo caiga en spam—, y se acota con
  un remitente del dominio propio.

## 💡 En La Guajira pesa más WhatsApp que el correo
Es el canal real, no el correo. Pero la WhatsApp Cloud API exige empresa verificada y plantillas
aprobadas: son semanas de trámite, no de código. **Arrancar con correo más un enlace `wa.me`** y
dejar la API para cuando haya empresa constituida.

Ojo con el cruce con [[adr-0030-anonimato-por-etapas-y-cupos]]: si el contacto se revela por etapas,
el aviso no puede llevar adentro el teléfono del otro. El correo avisa y lleva a la app; el dato se
muestra donde ya se controla quién puede verlo.

## Por decidir
- [ ] Cuántos días antes avisa el vencimiento.
- [ ] Si el instalador puede apagar los avisos, y qué hace la plataforma si los apaga.

## Relacionado
[[adr-0022-directorio-de-instaladores-y-solicitudes]] ·
[[adr-0023-bandeja-de-solicitudes-del-instalador]] · [[adr-0026-cotizacion-del-instalador]] ·
[[adr-0028-comparador-de-cotizaciones]] · [[adr-0030-anonimato-por-etapas-y-cupos]] ·
[[verificar-el-cierre-con-el-cliente]] · [[nuevas-funcionalidades]]
