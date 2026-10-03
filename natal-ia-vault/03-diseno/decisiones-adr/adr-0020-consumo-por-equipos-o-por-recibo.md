---
tipo: adr
descripcion: ADR-0020 — El cliente elige cómo dar su consumo: sumando sus equipos o con los kWh al mes de su recibo
estado: ✅ Implementada
actualizado: 2026-10-03
---

# ADR-0020 · Consumo por equipos o por recibo

- **Estado:** 🟢 Aceptada · implementada el 2026-10-03
- **Fecha:** 2026-10-03
- **Reemplaza en parte:**
  - [[adr-0013-creacion-guiada-y-diario-de-consumo]]: los equipos dejan de ser la única base del cálculo, y el modo "recibo" vuelve, ahora como una elección del cliente.
- **Contexto del repo:**
  - `App\Domain\Consumption\ConsumptionMode`, `solar_projects.consumption_mode`;
  - `SaveSolarProject`, `SyncProjectConsumption`, `SolarProjectRequest`, `SolarProject::usesBillConsumption()`;
  - `SolarProjectConsumptionController`, vistas `solar-projects/_form` (etapa *Tu consumo*), `consumption` y `consumption-bill`.

## Contexto
- **El ADR-0013 hizo de los equipos la única base** del consumo, siguiendo al asesor: el recibo es un promedio y puede no reflejar el uso real ([[asesoria-felix-bada]]).
- **En la reunión con los profesores** se pidió que también exista la opción de **escribir los kWh al mes que aparecen en el recibo**. Quien tiene el recibo a mano calcula en segundos; quien no lo tiene, o quiere ver qué gasta más, usa los equipos.
- **Las dos formas valen.** El recibo es rápido y exacto para el mes que mira; los equipos explican el consumo y permiten simular cambios.
- **Por eso hay que preguntarle** al usuario cuál va a usar.

## Decisión

### 1. Se pregunta al crear el proyecto
- **Una etapa nueva, *Tu consumo*,** entre *Techo* y *Tu proyecto*. Pregunta "¿Cómo calculamos tu consumo de energía?" con dos tarjetas, como la del tipo de lugar:
  - **Con mi recibo de luz:** escribes los kWh al mes. Es lo más rápido.
  - **Con mis equipos:** los agregas después, espacio por espacio, como hasta ahora.
- **No hay una opción marcada de antemano:** el cliente tiene que elegir.
- **Solo el recibo pide un número.** El campo de kWh aparece al elegirlo, con la ayuda "¿Dónde lo encuentro en mi recibo?" (la misma guía de la tarifa). Se acepta de 1 a 1.000.000 kWh al mes.
- **El resumen** del último paso suma una fila *Consumo*: "380 kWh al mes" o "Con mis equipos".
- **Al crear:**
  - con el recibo, el proyecto ya tiene consumo: abre **Mi sistema** para calcular;
  - con los equipos, abre la pestaña **Consumo**, como antes.

### 2. Son excluyentes, y se puede cambiar
- **Un proyecto usa una forma a la vez:** `solar_projects.consumption_mode` es `appliances` (por defecto) o `bill`.
- **Se cambia en *Editar datos***, en la misma etapa *Tu consumo*. Los enlaces de la pestaña Consumo llevan allí.
- **Al pasar de equipos a recibo,** el campo empieza con lo que sumaban los equipos. **Los equipos se guardan:** si vuelve a los equipos, la lista sigue ahí.
- **Al pasar de recibo a equipos,** el consumo es lo que suma el diario (cero si está vacío).
- **Cada cambio deja el cálculo desactualizado** y rehace la potencia sugerida y la cotización.

### 3. Qué cambia en la aplicación
| Dónde | Con equipos | Con recibo |
|---|---|---|
| Pestaña *Consumo* | El diario por espacios | Una tarjeta con los kWh al mes, lo que cuestan con la tarifa y la cobertura del techo |
| Agregar o quitar equipos | Sí | No: el servidor responde 409 |
| `SyncProjectConsumption` | Suma los equipos | No toca el consumo |
| *Mi sistema* y las preguntas | "Tus equipos usan…" | "Tu consumo es de…" |

- **El resto del cálculo no cambia:** dimensionamiento ([[adr-0014-dimensionar-por-necesidad-y-mi-sistema]]), cotización y retorno parten de `monthly_consumption_kwh`, venga de donde venga.
- **Una petición sin modo** (una prueba, otra pantalla) conserva el del proyecto, y un proyecto nuevo queda con los equipos.
- **Los proyectos que ya existían** con consumo y sin equipos (los del recibo, de antes del ADR-0013) pasan a `bill` con una migración; el resto queda en `appliances`.

## Consecuencias
- ➕ Cumple lo que pidieron los profesores y deja entrar al cliente que solo tiene el recibo.
- ➕ El cálculo no se duplicó: una sola cifra de consumo mensual, con dos orígenes.
- ➖ Con el recibo no hay desglose por equipo: se pierde "lo que más consume" y el anillo por espacios.
- ⚠️ El recibo es un promedio de un mes. El texto de la etapa sugiere escribir el promedio de varios meses si se tienen.
- ⚠️ La etapa *Tu consumo* lleva el formulario a cinco pasos al crear (cuatro al editar).

## Por decidir
- ¿Permitir los dos a la vez, el recibo como total y los equipos como desglose?
- ¿Pedir también el recibo en pesos, para contrastarlo con la tarifa?
- ¿Mostrar un aviso cuando el consumo del recibo y el de los equipos difieran mucho?

## Relacionado
[[adr-0002-consumo-por-electrodomesticos]] · [[adr-0013-creacion-guiada-y-diario-de-consumo]] · [[adr-0014-dimensionar-por-necesidad-y-mi-sistema]] · [[adr-0007-formulario-de-proyecto-por-etapas]] · [[asesoria-felix-bada]]
