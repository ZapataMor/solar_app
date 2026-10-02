---
tipo: adr
descripcion: ADR-0017 — Catálogo de equipos como referencia de consumo para el administrador, que puede agregar equipos (tostadora…) sin tocar el código
actualizado: 2026-10-02
---

# ADR-0017 · Catálogo de equipos administrable

- **Estado:** 🟢 Aceptada · implementada el 2026-10-02
- **Fecha:** 2026-10-02
- **Contexto del repo:**
  - `App\Domain\Consumption\ApplianceCatalog` (equipos del sistema y equipos agregados);
  - tabla `catalog_appliances`, `DatabaseApplianceEntries`;
  - `SaveCatalogAppliance`, `DescribeApplianceCatalog`, `ApplianceCatalogController`;
  - vistas `appliance-catalog/index` y `appliance-catalog/form`.

## Contexto
- **Los equipos del diario de consumo** ([[adr-0013-creacion-guiada-y-diario-de-consumo]]) vivían en una constante del código. Agregar uno, como una tostadora, exigía programar.
- **Nadie podía consultar cuánto consume cada equipo** sin leer el código, y eso sirve como referencia para el equipo y el asesor.
- **Muchos equipos tienen opciones** (tamaño, tecnología). El consumo de un aire acondicionado va de 600 a 2.400 W según cuál.

## Decisión

### 1. Catálogo = equipos del sistema + equipos agregados
- **Los del sistema siguen en el código:** probados, con sus opciones cruzadas (capacidad × tecnología) y sus dibujos. En la pantalla son de solo lectura.
- **Los agregados viven en `catalog_appliances`.** Cada uno tiene:
  - nombre, dibujo (de los existentes, más "Tostadora" y "Enchufe" para cualquier equipo);
  - para qué lugar (hogar o negocio) y uso: horas al día, horas a la semana o todo el día;
  - horas y cantidad habituales, una ayuda para el cliente;
  - **una lista de opciones con su potencia** (una sola fila si no tiene variantes).
- **El contenedor arma un único `ApplianceCatalog`** con ambos, una vez por petición. Diario, cálculo, explicaciones y validación lo usan sin cambios.
- **Un agregado nunca reemplaza a uno del sistema:** si se llama "fridge", su clave pasa a ser `fridge_2`.

### 2. Cambiar o retirar un equipo no rompe proyectos
- **Las opciones conservan su clave** aunque cambie su nombre.
- **Si una opción desaparece,** o el equipo pasa de una opción a varias (o al revés), las filas del diario que la usaban pasan a la opción por defecto.
- **Al guardar se recalcula el consumo** de los proyectos que usan el equipo, y quedan con "!" para recalcular ([[adr-0010-aviso-de-recalculo]]).
- **No se borran, se ocultan:** un equipo oculto ya no se ofrece en el diario, pero los proyectos que lo tienen lo conservan y pueden editarlo.

### 3. La pantalla de referencia (Administración → Catálogo de equipos)
Una fila por equipo con:
- para qué lugar y su uso habitual;
- **potencia:** rango de sus opciones (mín.–máx.) y **promedio**;
- **consumo típico:** una unidad de la opción que el diario propone por defecto, con sus horas habituales, en kWh/mes y en pesos (tarifa de referencia, [[adr-0015-valores-de-referencia]]);
- cuántos proyectos lo usan;
- el detalle de cada opción, desplegable.

**Orden:** por consumo típico (predeterminado), potencia máxima, potencia promedio, nombre o número de opciones. **Filtro:** todos, hogar o negocio.

**¿Por qué no ordenar solo por el promedio de las opciones?**
- Las opciones no son igual de probables: el promedio del aire mezcla 9.000 BTU inverter con 24.000 BTU convencional.
- Tampoco incluye las horas de uso: una plancha de 1.100 W consume menos al mes que una nevera de 60 W.
- El **consumo típico** responde la pregunta real ("¿cuánto suma esto al recibo?").
- El promedio queda visible y como criterio de orden, para comparar potencias.

## Consecuencias
- ➕ Se agregan equipos sin desplegar código y aparecen de inmediato en el diario de todos los proyectos.
- ➕ El catálogo sirve como tabla de referencia de consumo para el equipo y el asesor.
- ➖ **Los agregados tienen un solo grupo de opciones.** Para cruzar dos (tamaño × tecnología) hay que hacerlo en el código.
- ➖ **Los del sistema no se editan desde la pantalla.** Ajustar sus potencias sigue siendo un cambio de código.
- ⚠️ Las potencias de los agregados son responsabilidad de quien las escribe: conviene anotar la fuente en la ayuda o validarlas con un instalador.

## Por decidir
- ¿Permitir ajustar la potencia de los equipos del sistema desde la pantalla, guardando la nueva en la base de datos?
- ¿Mostrar el consumo típico por la cantidad habitual (por ejemplo, 6 bombillos) y no por unidad?

## Relacionado
[[adr-0002-consumo-por-electrodomesticos]] · [[adr-0013-creacion-guiada-y-diario-de-consumo]] · [[adr-0015-valores-de-referencia]]
