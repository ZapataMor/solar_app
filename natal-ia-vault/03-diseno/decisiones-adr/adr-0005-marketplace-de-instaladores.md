---
tipo: adr
descripcion: ADR-0005 — Recomendar instaladores aliados y registrar los negocios cerrados
estado: 🟡 Propuesta
actualizado: 2026-09-30
---

# ADR-0005 · Marketplace de instaladores

- **Estado:** 🟡 Propuesta
- **Fecha:** 2026-09-30
- **Contexto del repo:** no existe aún; depende del rol `installer` de [[adr-0003-separar-datos-cliente-e-instalador]]

## Contexto
El modelo de ingresos propuesto ([[modelo-de-ingresos]]) es:
- **Suscripción:** los instaladores pagan unos 50.000 COP al mes por aparecer como recomendados.
- **Comisión:** del 1 al 3 % del contrato cuando el negocio se cierra a través de la plataforma.

Para cobrar, el software debe poder atribuir el cliente al instalador.

> **Primera etapa implementada:** [[adr-0022-directorio-de-instaladores-y-solicitudes]] acota lo que
> ya existe (directorio por municipio y solicitudes de cotización). Lo demás sigue pendiente aquí.

## Decisión
- **Perfil de instalador:** zona de cobertura por municipio, estado de la suscripción y datos de contacto.
- **Recomendación:** al final del resultado se sugieren instaladores activos que cubren el municipio del proyecto.
- **Lead:** cuando el cliente contacta a un instalador se crea un registro proyecto ↔ instalador con fecha; eso es la atribución.
- **Negocio cerrado:** el instalador marca el lead como ganado con el valor del contrato, y se calcula la comisión.
- **Fuera de alcance por ahora:** cobros en línea (se facturan manualmente) y alternar entre la red y el sistema fotovoltaico ([[ideas-aplazadas-asesoria]]).

## Consecuencias
- ➕ Hace real el modelo de ingresos y le da valor al instalador.
- ➖ Depende de que el instalador reporte el cierre honestamente.
- ⚠️ Definir el % de comisión y los términos antes de construir ([[modelo-de-ingresos]] tiene la tarea abierta).

## Alternativas consideradas
- **Solo suscripción:** es más simple de operar, pero deja fuera el ingreso ligado al éxito.
- **Solo comisión:** baja la barrera para el instalador, pero es difícil de verificar.

## Relacionado
[[asesoria-felix-bada]] · [[clientes-y-usuarios]] · [[adr-0022-directorio-de-instaladores-y-solicitudes]]
