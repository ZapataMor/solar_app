---
tipo: adr
descripcion: ADR-0009 — Sincronizar NASA POWER por día para usar radiación real y reemplazar estimaciones al publicarse
estado: ✅ Implementada
actualizado: 2026-10-01
---

# ADR-0009 · NASA POWER diario con datos reales

- **Estado:** 🟢 Aceptada · implementada el 2026-10-01
- **Fecha:** 2026-10-01
- **Contexto del repo:** `FetchNasaPowerData` (`nasa-power:fetch`), `ApiDataController::fetchNasaData`, `NasaWeatherDataService::storeDailyData`, `routes/console.php`

## Contexto
La sincronización programada y el botón de "Datos climáticos" pedían a NASA POWER datos **por hora**. Al consultar la API el 2026-10-01 se encontró que:
- **Por hora:** la radiación (`ALLSKY_SFC_SW_DWN`) llega en `-999` para **todo agosto y septiembre**. NASA publica la radiación horaria con meses de retraso; la temperatura sí llega, con unos 2 días.
- **Por día:** la radiación real está disponible **hasta 4–5 días atrás**, en la misma unidad (W/m², promedio de 24 h, comunidad `SB`).

Resultado: las **1.080 filas** de NASA tenían la radiación **estimada**, ninguna real.
- El método "histórico mensual" ponía el promedio del mes **en cada hora**, también de noche.
- Los cálculos con NASA usaban radiación estimada habiendo datos reales.
- En la misma tabla se mezclaban filas por hora (de la sincronización) y por día (del botón dentro del proyecto).

## Decisión
1. **Datos diarios en todas las sincronizaciones** (programada y manual). El cálculo solar trabaja por días, así que no se pierde nada.
2. **Las estimaciones se reemplazan solas:** cada ejecución reconsulta una ventana de 45 días. Cuando NASA publica un día que estaba estimado, la fila **pasa a dato real**, y el log y el mensaje cuentan cuántas se "confirmaron".
3. **Un dato real nunca se reemplaza por una estimación:** si NASA devuelve `-999` para un día que ya era real, se conserva el valor real.
4. **La sincronización corre cada 6 horas**, no cada hora, porque NASA publica una vez al día.
5. **Limpieza:** `php artisan nasa-power:fetch --rebuild` borra las filas horarias y vuelve a descargar todo el periodo de los proyectos en datos diarios.

## Consecuencias
- ➕ **Más credibilidad:** la radiación de NASA es real salvo en los últimos ~5 días. Esos días se marcan como estimados y se confirman solos cuando NASA los publica. En el pitch se puede decir que se usan **datos satelitales oficiales y verificados**.
- ➕ **Cálculos más precisos:** se acaban las estimaciones planas y la "radiación nocturna" en la tabla.
- ➕ **Una sola granularidad** en la tabla de NASA.
- ➖ Se pierde el detalle horario de NASA, que de todos modos no tenía radiación real. El detalle intradía lo siguen dando Ambient y la estación local.
- ⚠️ Un proyecto cuyo periodo sea solo de los últimos días seguirá usando estimaciones hasta que NASA publique.

## Alternativas consideradas
- **Mantener la granularidad por hora y esperar a NASA:** son meses de retraso; no sirve para la demo ni para el cliente.
- **Mezclar radiación diaria con temperatura horaria:** dos granularidades en la misma tabla y más complejidad sin beneficio para el cálculo.

## Relacionado
[[adr-0001-acercar-a-clean-architecture]] · [[adr-0008-datos-climaticos-en-pestanas]]
