---
tipo: adr
descripcion: ADR-0002 — Calcular el consumo a partir de electrodomésticos y horas de uso
actualizado: 2026-09-30
---

# ADR-0002 · Consumo por electrodomésticos

- **Estado:** 🟡 Propuesta
- **Fecha:** 2026-09-30
- **Contexto del repo:** `SolarProjectRequest`, `solar-projects/_form.blade.php`, `App\Domain\Solar\EnergyProfile`

## Contexto
El formulario pide `monthly_consumption_kwh` tomado del recibo. El asesor señaló que ese dato es un promedio: no dice **qué** quiere alimentar el cliente ni **cuándo**, que es lo que dimensiona el sistema. La mayoría de la gente no sabe su consumo en kWh, pero sí qué equipos tiene. Ver [[consumo-por-electrodomesticos]].

## Decisión
- **Primer paso del formulario:** tipo de cliente (**residencial** o **comercial**) y si **ya tiene un sistema**.
- **Carga por equipos:** el cliente elige sus equipos con datos que sí conoce, y la app los convierte a W y kWh:
  - aire acondicionado: convencional o inverter, BTU y horas;
  - abanicos, nevera (litros), bombillas, TV (pulgadas), lavadora (kg), licuadora;
  - en comercial: congeladores, enfriadores y neveras verticales.
- **En el dominio:** se agrega `ApplianceLoad` y un catálogo de potencias de referencia. `EnergyProfile` se construye sumando las cargas, y el `SolarCalculator` no cambia.
- **El kWh del recibo queda opcional** como dato de contraste, no como entrada principal.
- **Simulador:** quitar o agregar equipos recalcula en vivo el sistema y el costo.

## Consecuencias
- ➕ El cliente ingresa datos que conoce, y el dimensionamiento responde a su necesidad real (lo mínimo: refrigeración y climatización).
- ➕ Habilita sugerir kits estándar por rango de consumo.
- ➖ Hay que investigar y mantener un catálogo de potencias típicas.
- ⚠️ Migración: los proyectos existentes solo tienen kWh mensual y deben seguir funcionando.

## Alternativas consideradas
- **Mantener solo el kWh del recibo:** es más simple, pero es el problema que señaló el asesor.

## Relacionado
[[asesoria-felix-bada]] · [[adr-0001-acercar-a-clean-architecture]] · [[adr-0003-separar-datos-cliente-e-instalador]]
