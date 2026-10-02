---
tipo: adr
descripcion: ADR-0013 — Crear el proyecto con preguntas guiadas y registrar los equipos después, por espacio, como un diario (inspirado en Fitia)
actualizado: 2026-10-02
---

# ADR-0013 · Creación guiada y diario de consumo por espacios

- **Estado:** 🟢 Aceptada · implementada el 2026-10-01
- **Fecha:** 2026-10-01
- **Reemplaza en parte:**
  - [[adr-0007-formulario-de-proyecto-por-etapas]]: cambian las etapas y su orden.
  - [[adr-0002-consumo-por-electrodomesticos]]: los equipos ya no se piden al crear el proyecto y el modo "recibo" desaparece.
- **Contexto del repo:**
  - `App\Domain\Property\PropertyType`, `App\Domain\Solar\RequiredPower`, `App\Domain\Solar\MissingConsumption`;
  - `SaveSolarProject`, `SaveProjectAppliance`, `RemoveProjectAppliance`, `SyncProjectConsumption`, `QuoteInstallation`;
  - `SolarProjectConsumptionController`, `SolarProjectNotesController`;
  - vistas `solar-projects/consumption`, `solar-projects/notes` y `_form`.

## Contexto
- **El formulario sigue la lógica del sistema, no la del cliente.** Empieza con "Nombre del proyecto" y "Descripción", datos que el cliente no tiene en mente. La descripción, en realidad, le sirve a quien asesora, como lugar para anotar.
- **La etapa de consumo era la más larga.** Había que armar toda la lista de equipos de una vez, antes de ver el proyecto.
- **El recibo no es buena base.** El asesor indicó que el consumo del recibo es un promedio que puede no concordar con la realidad. La base del cálculo deben ser los equipos ([[asesoria-felix-bada]]).
- **Referencia: Fitia** (contador de calorías).
  - Primero pregunta tu objetivo y te guía con preguntas cortas.
  - Después registras comidas en un diario agrupado por momento del día (desayuno, almuerzo…), agregando de a una con búsqueda y porciones.
  - La app suma sola.

## Decisión

### 1. Creación guiada en 4 preguntas
Una pregunta por pantalla, con el mismo asistente del ADR-0007 (borrador al recargar, etapa en la URL).

| Paso | Pregunta | Datos |
|---|---|---|
| 1 · Tu lugar | ¿Para qué lugar quieres energía solar? | **Tipo de inmueble:** casa, negocio o institución (tarjetas) |
| 2 · Ubicación | ¿Dónde está? | Municipio (mapa), tipo de ubicación, coordenadas opcionales |
| 3 · Techo | ¿Cuánto espacio tienes en el techo? | Atajos pequeño, mediano o grande, o metros exactos. Parámetros técnicos y fechas en "avanzado" |
| 4 · Tu proyecto | Últimos datos | Tarifa del kWh (con la guía del recibo) y **nombre sugerido** ("Mi casa en Maicao"), más un resumen editable |

- **El nombre va al final** y se propone a partir del tipo y el municipio.
- **La descripción sale del formulario** y pasa a la pestaña **Notas** del proyecto: opcional para el cliente y útil para el asesor.
- **La ubicación muestra solo el precio por kW instalado.** Sin consumo todavía no hay potencia, así que el costo total no se puede mostrar. El factor logístico deja de mostrarse al cliente en este paso ([[adr-0004-cotizacion-por-items-y-transporte-interno]]).
- **Al crear,** el proyecto abre directamente su pestaña **Consumo**.
- **Periodo de análisis** (corregido el 2026-10-02):
  - por defecto son los últimos tres meses hasta hoy;
  - debe cubrir al menos un mes y no puede terminar después de hoy (`AnalysisPeriod`).
  - Antes se creaba con un solo día, el de creación. Un día con solo la mañana medida pedía cientos de miles de paneles.

### 2. El tipo de inmueble es un campo real
- `solar_projects.property_type`, con los valores `house`, `business` e `institution`.
- **Define los espacios del diario** y **filtra el catálogo** de equipos:
  - casa → equipos de hogar;
  - negocio → equipos de negocio;
  - institución → ambos.
- Servirá para la ilustración 3D ([[adr-0012-ilustracion-3d-de-la-instalacion]]).

### 3. Diario de consumo por espacios (pestaña Consumo)

| Inmueble | Espacios |
|---|---|
| Casa | Cocina · Sala y comedor · Habitaciones · Lavandería y patio · Otros |
| Negocio | Área de atención · Oficina · Bodega y cocina · Otros |
| Institución | Aulas · Oficinas · Cocina y comedor · Zonas comunes · Otros |

- **Cada espacio** lista sus equipos en filas compactas (dibujo, opción elegida, cantidad × horas, kWh) y tiene **"+ Agregar"**.
- **"+ Agregar" abre una hoja** con buscador y catálogo. Al elegir un equipo se configuran su tamaño o tecnología, cantidad y horas, con vista previa de los kWh. Las filas se editan y se quitan desde la misma hoja.
- **Resumen arriba:**
  - total en kWh/mes y por día;
  - anillo con la distribución por espacio;
  - el equipo que más consume;
  - botón Calcular cuando hace falta.
- **El servidor recalcula el consumo** con el catálogo; nunca confía en el navegador.
- **Ver en kWh o en pesos:** un selector cambia todas las cifras del diario (filas, espacios, total, anillo y vista previa) a **pesos al mes** con la tarifa del proyecto: es lo que esa energía cuesta hoy en el recibo. Cada navegador recuerda la elección. Sin tarifa, solo se muestra en kWh.
- **Guardar sin recargar:** agregar, editar o quitar un equipo actualiza el diario con el HTML que devuelve el servidor, y el mensaje de éxito sale como una notificación pasajera. Sin JavaScript, la petición clásica sigue funcionando.
- **Los equipos de un espacio que no existe** en el tipo actual, por ejemplo al cambiar de casa a negocio, aparecen en "Otros".

### 4. Los equipos son la base del cálculo
- **Consumo mensual = suma de los equipos.** Cada cambio en el diario lo actualiza, junto con la potencia sugerida y la cotización por municipio. El proyecto queda marcado con "!" para recalcular ([[adr-0010-aviso-de-recalculo]]).
- **Sin equipos no se calcula:** `MissingConsumption`. La vigencia queda en *NOT_READY* con el motivo "Agrega tus equipos", y la capa de preguntas lleva a la pestaña Consumo ([[adr-0011-preguntas-explicativas-del-proyecto]]).
- **Proyectos antiguos basados en el recibo** conservan su consumo hasta que se agrega el primer equipo. La pestaña Consumo lo avisa.

## Consecuencias
- ➕ La creación es corta y conversacional. El cliente responde lo que sabe y no ve campos técnicos.
- ➕ El consumo se construye poco a poco y por lugares, que es como la gente recuerda sus equipos. Además es más fiel que el promedio del recibo.
- ➕ La descripción queda donde le sirve al asesor.
- ➕ El tipo de inmueble alimenta el catálogo, los espacios y la futura ilustración 3D.
- ➖ El proyecto nace sin resultado hasta que se agregan equipos. Se mitiga abriendo directamente la pestaña Consumo con una invitación clara.
- ➖ La simulación previa del formulario (estimación en vivo) desaparece, porque dependía del consumo.
- ⚠️ El catálogo y sus consumos de referencia siguen pendientes de validar con un instalador.

## Alternativas consideradas
- **Mantener el recibo como base y los equipos como afinación:** el asesor lo desaconsejó, porque el recibo es un promedio poco fiel.
- **Seguir pidiendo los equipos en la creación:** es la pantalla más larga y frena el arranque. El diario permite volver y completar.
- **Agrupar los equipos por tipo en vez de por espacio:** recorrer la casa espacio por espacio ayuda a no olvidar equipos, como el diario por comidas de Fitia.
