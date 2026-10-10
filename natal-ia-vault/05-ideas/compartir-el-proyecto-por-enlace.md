---
tipo: idea
descripcion: Un enlace firmado para que el cliente muestre su proyecto sin que el otro tenga que crear cuenta
estado: 🟡 Candidata
esfuerzo: ~medio día
creado: 2026-10-10
tags: [idea, producto]
---

# Compartir el proyecto por enlace

**Qué es:** el cliente genera un enlace de solo lectura de su proyecto y lo manda por WhatsApp. Quien
lo abre ve el estudio sin cuenta y sin poder cambiar nada.

## Por qué
Es el mismo problema que [[estudio-imprimible-del-proyecto|el estudio imprimible]] —la decisión se
toma entre varios— pero por el canal que la gente usa de verdad en La Guajira: **WhatsApp, no el
correo ni la impresora**. Un enlace se reenvía en dos segundos; un PDF de dos megas, menos.

Y tiene un valor que el PDF no tiene: **crece**. Si el enlace muestra el proyecto vivo, quien lo
abrió vuelve a mirarlo cuando lleguen las cotizaciones.

## Viabilidad
- **Dónde va:** URL firmada de Laravel y una vista de solo lectura. La autorización no pasa por
  `SolarProjectPolicy::manage`, así que **hay que tener cuidado de no colar en esa vista nada que la
  policy protege**: notas internas, correo del cliente o los datos de contacto de los instaladores.
- **Esfuerzo:** ~medio día.
- **Riesgo:** privacidad. Un enlace que no caduca y se reenvía a un grupo deja el consumo y la
  dirección de alguien circulando. Choca de frente con el espíritu del
  [[adr-0030-anonimato-por-etapas-y-cupos]], que justamente busca que el dato del cliente no viaje
  solo: si se hace, **con vencimiento y con posibilidad de revocarlo**.

## Por decidir
- [ ] ¿Cuánto dura el enlace y se puede revocar?
- [ ] ¿Muestra las cotizaciones recibidas? (son precios de terceros, no del cliente)
- [ ] ¿Se registra cuántas veces se abrió? Es la métrica de si la idea sirve.

## Relacionado
[[estudio-imprimible-del-proyecto]] · [[adr-0030-anonimato-por-etapas-y-cupos]] ·
[[adr-0003-separar-datos-cliente-e-instalador]] · [[notificaciones-de-la-plataforma]] ·
[[nuevas-funcionalidades]]
