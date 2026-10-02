---
tipo: adr
descripcion: ADR-0002 — Calcular el consumo a partir de electrodomésticos y horas de uso
estado: ✅ Implementada
actualizado: 2026-09-30
---

# ADR-0002 · Consumo por electrodomésticos

- **Estado:** 🟢 Aceptada · implementada el 2026-10-01
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

## Implementación
- **Dominio:**
  - `App\Domain\Consumption\ApplianceCatalog`: 15 equipos con variantes y potencia promedio en uso.
  - `ApplianceLoad` y `ConsumptionEstimator`: W × cantidad × horas × 30 / 1000. Los equipos "siempre encendidos" usan 24 h.
- **Persistencia:** tabla `solar_project_appliances` (equipo, variante, cantidad, horas al día).
- **Validación:** `SolarProjectRequest` recalcula `monthly_consumption_kwh` en el servidor a partir del catálogo; el valor que llega del navegador se ignora.
- **Interfaz** (etapa 3 del [[adr-0007-formulario-de-proyecto-por-etapas]]):
  - modos "Con mis equipos" (por defecto) y "Ya sé mi consumo";
  - filtro Hogar/Negocio;
  - dibujos SVG propios en `partials/appliance-icons`;
  - variantes con dibujo a escala, cantidad, horas al día o a la semana;
  - total en vivo.
- **Pendiente:** validar el catálogo de potencias con el asesor o un instalador.

## Alternativas consideradas
- **Mantener solo el kWh del recibo:** es más simple, pero es el problema que señaló el asesor.

## Relacionado
[[asesoria-felix-bada]] · [[adr-0001-acercar-a-clean-architecture]] · [[adr-0003-separar-datos-cliente-e-instalador]]
