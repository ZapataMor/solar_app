---
tipo: idea
descripcion: El mismo proyecto dimensionado en tres variantes —conectado, híbrido y aislado— con su costo y su retorno
estado: 🟡 Candidata
esfuerzo: varios días
creado: 2026-10-10
tags: [idea, calculo]
---

# Escenarios: conectado, híbrido y aislado

**Qué es:** del mismo consumo y el mismo techo salen tres sistemas —**on-grid** (sin baterías),
**híbrido** (con respaldo) y **off-grid** (aislado)— cada uno con sus paneles, su costo y su retorno,
lado a lado.

## Por qué
Es la pregunta que todo cliente hace después de ver el primer número: *"¿y con baterías cuánto?"*. Hoy
la app dimensiona **un** sistema ([[adr-0014-dimensionar-por-necesidad-y-mi-sistema]]) y la
distinción con baterías solo aparece del lado del instalador, en lo que su cotización incluye
([[adr-0027-datos-de-una-cotizacion-real]]). Resultado: el cliente descubre que existen dos mundos
cuando ya le llegaron precios que no puede comparar, porque no son el mismo sistema.

También le arregla la vida al comparador: dos cotizaciones, una con baterías y otra sin, hoy se
enfrentan en una fila ([[adr-0028-comparador-de-cotizaciones]]) pero sus totales no son comparables.
Con escenarios, el cliente pide precio **para un escenario**, y entonces sí.

## Viabilidad
- **Esfuerzo: varios días, no horas.** Dimensionar baterías es cálculo nuevo —autonomía deseada,
  profundidad de descarga, carga crítica, pérdidas de ida y vuelta—, no un parámetro que se agrega al
  dimensionamiento actual. El costo también cambia: el inversor híbrido y el banco de baterías no se
  estiman con el $/kW de [[adr-0024-precios-por-municipio-administrables|los precios por municipio]].
- **Dónde va:** `SystemSizing` y `InstallationCostCalculator`, con tests en `tests/Unit/Domain`.
  Arrastra la pestaña *Mi sistema*, el presupuesto, la solicitud y el comparador.
- **Riesgo:** medio-alto, porque toca el corazón del cálculo que hoy funciona. Conviene después del
  pitch y con [[autonomia-en-un-corte]] ya hecho: esa idea es el primer pedazo de este cálculo y se
  puede entregar sola.

## Por decidir
- [ ] Si los tres escenarios se calculan siempre o el cliente elige uno al crear el proyecto.
- [ ] Qué autonomía por defecto define el escenario híbrido.
- [ ] Si la solicitud al instalador viaja con el escenario elegido (y si puede cotizar otro).

## Relacionado
[[adr-0014-dimensionar-por-necesidad-y-mi-sistema]] · [[adr-0024-precios-por-municipio-administrables]] ·
[[adr-0027-datos-de-una-cotizacion-real]] · [[adr-0028-comparador-de-cotizaciones]] ·
[[autonomia-en-un-corte]] · [[proyeccion-a-25-anos]] · [[nuevas-funcionalidades]]
