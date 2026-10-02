---
tipo: adr
descripcion: ADR-0014 — Instalar los paneles que el consumo necesita (con el techo como límite) y mostrarlo en la pestaña "Mi sistema", en lenguaje de cliente, junto al panel Técnico
actualizado: 2026-10-01
---

# ADR-0014 · Dimensionar por necesidad y pestaña "Mi sistema"

- **Estado:** 🟢 Aceptada · implementada el 2026-10-01
- **Fecha:** 2026-10-01
- **Reemplaza:** el dimensionamiento que llenaba el techo en `SolarCalculator`.
- **Contexto del repo:**
  - `App\Domain\Solar\SystemSizing`, `App\Domain\Solar\LiveSolarOutput`, `SolarCalculator`, `CalculationFreshnessPolicy`;
  - `SizeProjectSystem`, `DescribeProjectSystem`, `CalculateSolarProject`, `CheckCalculationFreshness`;
  - `SolarProjectSystemController`, vistas `solar-projects/system` y `partials/consumption-diary-content`;
  - migración `add_sizing_to_calculation_results` (`panels_needed`, `panels_that_fit`, `panel_monthly_generation_kwh`).

## Contexto
- **La app no decía cuántos paneles hacían falta.** `SolarCalculator` ponía todos los que cabían en el techo, sin mirar el consumo. Un techo grande con poco consumo salía con más paneles, y más costo, de lo necesario.
- **El ahorro estaba inflado.** Se multiplicaba toda la generación por la tarifa, incluida la energía que sobra. Un local comercial mostraba un ahorro de $1.111.800 al mes con un recibo de $1.088.000. La energía que sobra no baja el recibo.
- **El panel del proyecto habla en lenguaje técnico:** kWp, HSP, factor logístico, radiación. El cliente quiere saber:
  - cuántos paneles necesita y si caben;
  - cuánto cuesta, cuánto ahorra y en cuánto tiempo se paga.
  
  Tampoco veía la relación entre lo que da el sol y lo que gastan sus equipos.
- **Los equipos son la base del cálculo** ([[adr-0013-creacion-guiada-y-diario-de-consumo]]). Cada equipo cambia cuántos paneles hacen falta, pero el diario no lo mostraba.

## Decisión

### 1. Dimensionar según el consumo, con el techo como límite
`SystemSizing` (dominio puro):

| Cifra | Cálculo |
|---|---|
| Lo que da un panel al mes | potencia del panel (kW) × HSP promedio × PR × 365 ÷ 12 |
| Paneles necesarios | consumo mensual ÷ lo que da un panel, redondeado hacia arriba |
| Paneles que caben | área útil ÷ área del panel, redondeado hacia abajo |
| Paneles instalados | el menor de los dos |

- **Nunca se llena el techo.** Si sobra espacio se dice ("te queda espacio para 6 paneles más"), pero no se cobra.
- **Si el techo no alcanza,** se instalan todos los que caben y se dice cuántos faltan y cuánta energía seguiría llegando de la red.
- **El mes del panel es un doceavo del año,** igual que la generación mensual y la cobertura de la estimación. Con un mes de 30 días, el sistema pedía un panel más cuando la estimación ya cubría el 100 %.
- El cálculo guarda `panels_needed`, `panels_that_fit` y `panel_monthly_generation_kwh` en `calculation_results`.

### 2. El ahorro cuenta solo la energía que se deja de comprar
- **Ahorro = mín(generación, consumo) × tarifa**, por mes y por año. Los excedentes no bajan el recibo.
- El tiempo de retorno se calcula con ese ahorro.

### 3. Cobertura en vivo en el diario de consumo
- Debajo del resumen del diario, una franja con barra dice cuánto del consumo cubriría el techo:
  - "Bastan 3 paneles de los 12 que caben";
  - "Necesitarías 18 paneles y caben 12: faltan 6".
- Cambia con cada equipo, sin recargar.
- `SizeProjectSystem` toma lo que da un panel del último cálculo con datos climáticos. Sin cálculo, usa el sol de referencia de La Guajira (5,8 HSP) y lo avisa: "Estimado con el sol promedio de La Guajira".

### 4. Pestaña "Mi sistema" junto a "Técnico"
- **Pestañas del proyecto:** **Técnico** (el panel original) · **Mi sistema** · Consumo · Notas · Editar datos.
- **Los dos paneles conviven** para compararlos con clientes antes de quedarse con uno.

Contenido de "Mi sistema" (`DescribeProjectSystem`), de arriba abajo:
1. **Ilustración y recomendación en una frase:**
   - "Necesitas 15 paneles; en tu techo caben 11";
   - "el sol pagaría 8 de cada 10 pesos de tu luz";
   - barra de cobertura y lo que se seguiría pagando a la red.
2. **Tres cifras:** cuánto cuesta, cuánto ahorras al mes y en cuánto se paga ("2 años y 6 meses").
3. **El sol frente a tus equipos, mes a mes:** barras pareadas, con cada mes llevado a 30 días. Se omiten los meses con pocos días de datos.
4. **Ahora mismo:** con la última lectura de la estación Ambient Weather (de hace 3 horas como máximo), cuánto estarían dando los paneles y qué equipos alcanzarían a encender. `LiveSolarOutput` los toma de mayor a menor potencia.
5. **¿Cómo lo calculamos?:** los cuatro pasos del dimensionamiento con los números del proyecto.

Además:
- **Todas las cifras se pueden ver en kWh o en pesos,** con el mismo selector del diario.
- **Las preguntas explicativas** ([[adr-0011-preguntas-explicativas-del-proyecto]]) dicen si se instalan los paneles necesarios ("ni uno más") o todos los que caben.
- **Ilustración 3D** ([[adr-0012-ilustracion-3d-de-la-instalacion]]). `figure[data-solar-scene]` entrega:
  - tipo de inmueble;
  - paneles instalados, que caben y que faltan;
  - área del techo y área del panel;
  - kWh de un día promedio.
  
  Sin WebGL queda un boceto plano con los mismos números.
- **Calcular desde "Mi sistema"** vuelve a "Mi sistema" (`then=system`).

### 5. Cálculos anteriores
- Los resultados guardados antes de esta decisión no tienen `panels_needed`.
- La vigencia los marca para recalcular con el motivo "Mejoramos el cálculo: ahora se instalan solo los paneles que necesitas" ([[adr-0010-aviso-de-recalculo]]).
- El botón "Recalcular proyectos" del portafolio los pone al día.
- Mientras tanto, "Mi sistema" estima con los equipos actuales.

## Consecuencias
- ➕ La recomendación responde la pregunta del cliente: cuántos paneles necesita, no cuántos caben.
- ➕ Costo y retorno realistas: no se cobra un techo lleno ni se promete un ahorro mayor que el recibo.
- ➕ El diario muestra el efecto de cada equipo sobre el sistema mientras se llena.
- ➕ "Mi sistema" se entiende sin saber qué es un kWp, lo que ayuda en el pitch ([[pitch-primera-etapa]]).
- ➖ Hay dos paneles con información parecida que mantener hasta decidir.
- ➖ El costo sale de una tarifa fija por kWp (`INSTALLATION_COST_PER_KWP_COP`), no de la cotización por municipio ([[adr-0004-cotizacion-por-items-y-transporte-interno]]). Por eso puede no coincidir con el precio de la ubicación.
- ⚠️ **El año de consumo cuenta 360 días** (12 meses de 30) y el de sol, 365. La cobertura favorece al sol en un 1,4 %. Corregirlo cambia el consumo anual que se guarda y se muestra en el panel Técnico, así que queda para después.
- ⚠️ "Ahora mismo" usa una sola estación (Ambient Weather) para todos los municipios.
- ⚠️ Los excedentes no valen nada en este modelo. Si el cliente vende energía a la red (autogeneración a pequeña escala), habrá que valorarlos aparte.

## Alternativas consideradas
- **Seguir llenando el techo:** genera más, pero sobredimensiona y encarece; sin venta de excedentes, ese ahorro extra no existe.
- **Preguntar al cliente cuántos paneles quiere:** el cliente no lo sabe; la app debe recomendar.
- **Cambiar el panel original en vez de crear otro:** se pierde la vista técnica, útil para el asesor. Compararlos permite decidir con evidencia.
- **Pagar los excedentes al precio de venta a la red:** depende de un acuerdo con el operador de red que la app todavía no modela.

## Por decidir
- Qué panel queda como principal, o si "Técnico" pasa a ser una vista del asesor ([[adr-0003-separar-datos-cliente-e-instalador]]).
- Qué proponer cuando faltan paneles: un panel más potente, una pérgola o una instalación en el suelo.

## Relacionado
[[adr-0012-ilustracion-3d-de-la-instalacion]] · [[adr-0013-creacion-guiada-y-diario-de-consumo]] · [[adr-0011-preguntas-explicativas-del-proyecto]] · [[pitch-primera-etapa]]
