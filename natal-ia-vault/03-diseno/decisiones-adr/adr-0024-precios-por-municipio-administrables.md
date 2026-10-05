---
tipo: adr
descripcion: ADR-0024 — El administrador edita el precio por kW de cada municipio, sin tocar lo ya cotizado
estado: 🟢 Aceptada
actualizado: 2026-10-05
---

# ADR-0024 · Precios por municipio administrables

- **Estado:** 🟢 Aceptada
- **Fecha:** 2026-10-05
- **Contexto del repo:** `app/Actions/Pricing`, `MunicipalityPriceController`,
  `municipality-prices/index.blade.php`, tablas `municipality_solar_prices` y `quote_requests`

## Contexto
El precio por kW instalado de cada municipio es el número más caro de la app: de él salen la
inversión inicial, el ahorro y el retorno de todo proyecto, y el *presupuesto de referencia* que el
instalador ve en cada solicitud ([[adr-0023-bandeja-de-solicitudes-del-instalador]]).

Hasta hoy vivía solo en `MunicipalitySolarPriceSeeder`: cambiarlo exigía editar PHP y volver a
sembrar. [[modelo-de-ingresos]] advierte que los equipos son importados y el precio se mueve con el
dólar, así que ese número cambia más seguido que el código.

[[adr-0015-valores-de-referencia]] ya resolvió lo mismo para los números generales del sistema, pero
dejó fuera los precios por municipio, que no son un valor único sino uno por municipio y tipo de
ubicación.

## Decisión
- **Pantalla de administración** *Precios por municipio* (gate `administer-platform`), junto a
  *Valores de referencia* y *Catálogo de equipos*.
- **Se edita el precio base y el factor logístico** de cada par municipio + tipo de ubicación. El
  precio final es `base × factor`, como ya lo calcula `InstallationCostCalculator`.
- **Cambiar un precio no toca lo ya cotizado.** Un proyecto guarda la cotización con la que se hizo
  (`base_price_per_kw`, `logistic_factor_used`, `final_price_per_kw_used`,
  `estimated_installation_cost`) y ahí se queda. Si mañana sube el dólar y el precio con él, lo
  acordado sigue diciendo lo que se acordó; el precio nuevo rige para lo que se cotice desde
  entonces.
- **La solicitud de cotización congela su número.** `quote_requests` guarda el presupuesto y el
  precio por kW del momento en que el cliente la pidió (`quoted_cost_cop`, `quoted_price_per_kw_cop`).
  Antes la bandeja del instalador leía el valor vivo del proyecto, así que un cambio de precio
  reescribía el número sobre el que las dos partes ya estaban hablando.
- **Un precio se oculta, no se borra:** `active = false` lo saca del cálculo, pero lo cotizado con él
  se conserva.
- **Sin historial de precios en la tabla.** No hace falta: el historial que importa queda copiado en
  cada proyecto y en cada solicitud.

## Consecuencias
- ➕ Actualizar precios cuando se mueve el dólar deja de ser un cambio de código.
- ➕ Lo acordado con un cliente no cambia a sus espaldas, ni en su proyecto ni en la solicitud que
  está leyendo el instalador.
- ➖ Dos proyectos del mismo municipio pueden mostrar precios distintos si se cotizaron en meses
  distintos. Es correcto, pero hay que saberlo al compararlos.
- ⚠️ Un proyecto que el cliente **sigue editando** vuelve a cotizarse con el precio de hoy
  (`SyncProjectConsumption`): lo que queda congelado es lo cotizado, no lo que está en construcción.
- ⚠️ `min_price_per_kw` y `max_price_per_kw` siguen siendo informativos: nadie valida contra ellos.

## Alternativas consideradas
- **Llevarlos a `reference_values`** (ADR-0015) — ese catálogo es de valores únicos; un precio por
  municipio y tipo de ubicación no cabe sin inventarle claves compuestas.
- **Volver a cotizar los proyectos del municipio al guardar** — los deja a todos al día con el precio
  de hoy, pero reescribe el número que un cliente y un instalador ya tenían sobre la mesa. Fue la
  primera idea y se descartó por eso.
- **Guardar vigencias e historial de precios, como el ADR-0015** — más fiel a la historia de la
  tabla, pero lo que alguien necesita auditar es lo que se le cobró a un cliente, y eso ya queda en
  su proyecto y en su solicitud.

## Relacionado
[[adr-0015-valores-de-referencia]] · [[adr-0023-bandeja-de-solicitudes-del-instalador]] ·
[[adr-0022-directorio-de-instaladores-y-solicitudes]] · [[modelo-de-ingresos]]
