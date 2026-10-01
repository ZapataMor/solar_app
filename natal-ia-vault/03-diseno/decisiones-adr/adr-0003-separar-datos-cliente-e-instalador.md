---
tipo: adr
descripcion: ADR-0003 — Separar lo que ve y llena el cliente de lo que decide el instalador
actualizado: 2026-09-30
---

# ADR-0003 · Separar datos del cliente y del instalador

- **Estado:** 🟡 Propuesta
- **Fecha:** 2026-09-30
- **Contexto del repo:** `technical_parameters`, `_form.blade.php`, `show.blade.php`, roles en `users.role`

## Contexto
Hoy el mismo formulario le pide al cliente parámetros técnicos que no conoce: % de área utilizable, potencia y área del panel y pérdidas del sistema. El asesor indicó que eso es trabajo del instalador. El cliente solo debe dar el **área disponible en m²**, y las pérdidas varían con la limpieza del panel, así que no se pueden promediar. Ver [[datos-cliente-vs-instalador]].

## Decisión
- **El cliente solo llena:** tipo, equipos ([[adr-0002-consumo-por-electrodomesticos]]), ubicación, área disponible en m² y si ya tiene un sistema.
- **Parámetros técnicos:** toman **valores por defecto** configurables y solo los edita el instalador o el admin.
- **Resultados para el cliente:** capacidad instalada, generación mensual, cobertura, inversión inicial, ahorro mensual y **retorno expresado en años**.
- **Vista técnica:** el detalle técnico se muestra en otra vista, visible solo para instalador o admin.
- **Rol nuevo `installer`:** además de `admin` y `user`. Hoy solo existen esos dos y la única diferencia es ver proyectos ajenos.

## Consecuencias
- ➕ El formulario es más corto y entendible, y cae la fricción del cliente.
- ➕ Prepara el terreno para instaladores aliados ([[adr-0005-marketplace-de-instaladores]]).
- ➖ Se necesitan autorización por rol (policies) y vistas diferenciadas.
- ⚠️ Los valores por defecto deben ser realistas para La Guajira.

## Alternativas consideradas
- **Ocultar los campos con un "modo avanzado":** es más rápido, pero no resuelve quién es responsable del dato.

## Relacionado
[[asesoria-felix-bada]]
