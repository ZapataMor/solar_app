---
tipo: adr
descripcion: ADR-0001 — Acercar la app a Clean Architecture de forma incremental
estado: 🟢 Guía en curso
actualizado: 2026-09-30
---

# ADR-0001 · Acercar a Clean Architecture

- **Estado:** 🟢 Aceptada
- **Fecha:** 2026-09-30
- **Contexto del repo:** `app/Domain`, `app/Infrastructure/Climate`, `app/Actions/SolarProjects`, `SolarProjectController`

## Contexto
Monolito Laravel MVC con servicios. El cálculo solar dependía de Eloquent, no había interfaces ni casos de uso, y el controlador tenía 853 líneas con 4 métodos de cálculo casi duplicados. Puntaje estimado: 3/10.

## Decisión
1. **Puerto `ClimateSource`**, con un adaptador por fuente (Ambient, estación local, NASA) y una `ClimateSourceChain` con la prioridad, registrada en `AppServiceProvider`.
2. **Núcleo puro en `app/Domain`**: `SolarCalculator` e `InstallationCostCalculator`, sin Laravel ni base de datos.
3. **Casos de uso** `CalculateSolarProject` y `SaveSolarProject`. El controlador solo traduce HTTP.

## Consecuencias
- ➕ Una nueva estación se agrega con un solo adaptador.
- ➕ El cálculo tiene tests unitarios puros.
- ➕ El nuevo formulario por electrodomésticos ([[consumo-por-electrodomesticos]]) podrá entrar como otro `EnergyProfile`.
- ➖ Hay más archivos y conviven dos estilos: servicios legados y dominio.
- ⚠️ El dashboard (`ProjectDashboardService`) y `ApiDataController` siguen con el estilo anterior.

## Alternativas consideradas
- **Migración completa antes del pitch:** se descartó por costo y riesgo antes del 15 de octubre.

## Relacionado
[[mapa-de-modulos]]
