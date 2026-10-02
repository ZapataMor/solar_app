---
tipo: adr
descripcion: ADR-0004 — Cotización por ítems con el transporte incluido en la mano de obra
estado: 🟡 Propuesta
actualizado: 2026-09-30
---

# ADR-0004 · Cotización por ítems y transporte interno

- **Estado:** 🟡 Propuesta
- **Fecha:** 2026-09-30
- **Contexto del repo:** `InstallationCostCalculator`, `SolarInstallationCostService`, `municipality_solar_prices`, `_form.blade.php` (líneas del factor logístico), `show.blade.php`

## Contexto
- **Factor logístico visible:** hoy la app muestra al cliente el "Factor logístico" por municipio. El asesor advirtió que mostrar el sobrecosto de transporte espanta al cliente ("¿por qué me cobras eso?"). El costo sí debe calcularse, pero repartido.
- **Costo único:** la cotización es un solo número y el cliente no ve qué está pagando.
- **Dos cálculos de costo:** hay una tarifa fija de 5.000.000 COP/kWp en `SolarCalculator` y otra por municipio en `SolarInstallationCostService`.

Ver [[cotizacion-y-costos-ocultos]].

## Decisión
- **Cotización por ítems:** paneles, cable, inversor, batería, regulador y **mano de obra**.
- **Transporte oculto:** el factor logístico se aplica **dentro de la mano de obra** y no se muestra como línea ni como factor.
- **Dos variantes:** con batería y sin batería.
- **Una sola fuente de costo:** `InstallationCostCalculator` pasa a ser el único cálculo, y `SolarCalculator` recibe el costo en vez de usar la constante.
- **Precios mantenibles:** los precios por ítem van en una tabla editable por el admin, porque los equipos son importados y dependen del dólar.

## Consecuencias
- ➕ La cotización es comercialmente atractiva y transparente en lo que importa.
- ➕ Se elimina la inconsistencia entre los dos costos.
- ➖ Hay que capturar y mantener los precios por ítem.
- ⚠️ Registrar la fecha de actualización de los precios para no cotizar con valores viejos.

## Alternativas consideradas
- **Mantener el costo por kW con el factor visible:** es más simple, pero comercialmente contraproducente según el asesor.

## Relacionado
[[asesoria-felix-bada]] · [[adr-0001-acercar-a-clean-architecture]]
