---
tipo: adr
descripcion: ADR-0015 — Valores de referencia del sistema (tarifa del kWh, contribución comercial…) con vigencia e historial, administrados por el admin; el cliente ya no tiene que escribir su tarifa
actualizado: 2026-10-02
---

# ADR-0015 · Valores de referencia del sistema

- **Estado:** 🟢 Aceptada · implementada el 2026-10-02
- **Fecha:** 2026-10-02
- **Contexto del repo:**
  - `App\Domain\Reference` (`ReferenceValueCatalog`, `EnergyTariff`, puerto `ReferenceValues`);
  - `App\Infrastructure\Reference\DatabaseReferenceValues`;
  - `RecordReferenceValue`, `DescribeReferenceValues`, `ReferenceValueController`;
  - tabla `reference_values`; vista `reference-values/index`;
  - `SolarProject::energy_rate_cop_kwh` (ahora opcional).

## Contexto
- **El cliente tenía que escribir la tarifa del kWh** al crear el proyecto. Muchos no la conocen y el dato es el mismo para todos: lo fija Air-e para La Guajira (unos $890/kWh desde agosto de 2026).
- **Esa tarifa cambia con el tiempo,** así que no puede quedar fija en el código ni en cada proyecto.
- **Otros números del sistema están quemados en el código,** como el costo por kWp, el sol de referencia y el panel por defecto. Conviene un lugar para administrarlos.

## Decisión

### 1. Valores de referencia con vigencia
- Cada valor tiene una clave del catálogo (`ReferenceValueCatalog`): nombre, unidad, descripción, rango válido y valor por defecto.
- **Registrar un valor agrega una fila** con *rige desde*, fuente y nota. Las anteriores quedan como historial; registrar otra vez la misma fecha corrige esa fila.
- **El valor vigente** es el último cuya fecha ya llegó. Con una fecha futura queda *programado*.
- Mientras nadie registre uno, rige el valor por defecto del catálogo.

### 2. Primeros valores
| Valor | Inicial | Fuente |
|---|---|---|
| Tarifa del kWh (CU de Air-e, sin subsidio ni contribución) | $890/kWh, desde el 1 de agosto de 2026 | Prensa (Infobae, opscolombia); confirmar con las tarifas que publica Air-e |
| Contribución de los negocios | 20 % | Ley 142 de 1994 |

**Tarifa por tipo de lugar** (`EnergyTariff`):
- **Negocio:** la tarifa más la contribución ($1.068).
- **Casa:** la tarifa completa, porque los paneles reemplazan el consumo por encima del de subsistencia, que ya se paga sin subsidio.
- **Institución:** la tarifa sin subsidio ni contribución (usuario oficial).

### 3. La tarifa del proyecto es opcional
- **Campo vacío:** el proyecto sigue la tarifa de referencia (`energy_rate_cop_kwh` nulo). El formulario lo dice: "Si la dejas vacía usamos la tarifa de Air-e: $890 por kWh ($1.068 para negocios)".
- **Si el cliente escribe la de su recibo,** esa es la del proyecto. Borrarla lo devuelve a la de referencia.
- **El modelo resuelve la tarifa efectiva** en el atributo, así que todo lo que la lee (cálculo, diario, Mi sistema, preguntas, servicios heredados) usa la correcta sin cambios.
- **Cuando cambia la tarifa de referencia,** los proyectos que la siguen quedan marcados con "!" y el motivo "Se actualizó la tarifa de referencia del kWh" ([[adr-0010-aviso-de-recalculo]]). Un valor programado los marca el día en que empieza a regir.
- **Los proyectos de ejemplo** ([[pitch-primera-etapa]]) siguen la de referencia.

### 4. Pantalla de administración
**Administración → Valores de referencia**, solo para administradores (`administer-platform`). Por cada valor muestra:
- el valor vigente, desde cuándo rige y su fuente;
- para la tarifa, cuánto paga un negocio y cuántos proyectos la usan;
- lo programado;
- un formulario para registrar un valor nuevo y el historial.

## Consecuencias
- ➕ El cliente crea su proyecto sin saber su tarifa, y la cifra es la real de Air-e.
- ➕ Un cambio de tarifa se hace una vez, en un solo lugar, con fecha y fuente, y avisa qué proyectos recalcular.
- ➕ Queda la base para sacar del código los demás números de referencia.
- ➖ La tarifa de una casa no distingue estratos 1 y 2, que tienen subsidio en el consumo de subsistencia. Se acepta, porque los paneles reemplazan sobre todo el consumo que supera ese límite.
- ⚠️ El valor inicial viene de la prensa: hay que confirmarlo con las tarifas oficiales de Air-e antes del pitch.

## Alternativas consideradas
- **Mantener la tarifa obligatoria en el formulario:** el cliente no la sabe y escribe cualquier cosa.
- **Guardar la tarifa en `.env` o en la configuración:** no deja historial ni vigencias, y solo la cambia quien despliega.
- **Copiar la tarifa de referencia en cada proyecto al crearlo:** cuando Air-e la cambia, los proyectos quedan con la vieja.

## Por definir: otros valores candidatos
1. **Costo de instalación por kWp** (hoy $5.000.000 fijo en `SolarCalculator`). Define "Cuesta" y el retorno; mientras llega la cotización por ítems ([[adr-0004-cotizacion-por-items-y-transporte-interno]]), debería ser administrable.
2. **Sol de referencia de La Guajira** (5,8 HSP en `RequiredPower`). Se usa antes de tener datos climáticos.
3. **Panel y techo por defecto:** 550 W, 2,6 m², 14 % de pérdidas y % útil del techo (`SystemSpecification::DEFAULT_*`).
4. **Consumo de subsistencia y subsidios de estratos 1–3.** Permitirían una tarifa por estrato para las casas.
5. **Aumento anual de la tarifa, vida útil del sistema (unos 25 años) y degradación anual del panel.** Permitirían mostrar el ahorro en toda la vida del sistema, no solo el retorno.
6. **Factor de emisión de CO₂ de la red** (el que publica la UPME). Permitiría decir "evitas X toneladas de CO₂ al año".
7. **Precio de venta de excedentes,** si se habilita la autogeneración ([[adr-0014-dimensionar-por-necesidad-y-mi-sistema]]).

## Relacionado
[[adr-0013-creacion-guiada-y-diario-de-consumo]] · [[adr-0014-dimensionar-por-necesidad-y-mi-sistema]] · [[adr-0010-aviso-de-recalculo]] · [[pitch-primera-etapa]]
