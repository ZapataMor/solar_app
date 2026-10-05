---
tipo: adr
descripcion: ADR-0025 — Cualquier visita a la app dispara la sincronización climática atrasada, en segundo plano
estado: 🟢 Aceptada
actualizado: 2026-10-05
---

# ADR-0025 · Sincronización disparada por el tráfico

- **Estado:** 🟢 Aceptada
- **Fecha:** 2026-10-05
- **Contexto del repo:** `app/Domain/Sync/SyncCadence`, `app/Actions/Climate/SyncDueSources`,
  `app/Jobs/SyncClimateSource`, `SyncClimateInBackground`, `bootstrap/app.php`

## Contexto
[[adr-0016-alertas-de-sincronizacion-de-datos]] dejó el programador corriendo cada minuto: Ambient y
la estación local cada 5 minutos, NASA cada 6 horas. Eso supone que alguien llame a `schedule:run`.

En producción nadie lo llama. El 5 de octubre de 2026 los datos se habían detenido el día 2 a las
10:45, y revivieron solos en cuanto alguien abrió *Datos climáticos*: esa pantalla lleva un
`setInterval` de 5 minutos (`initApiDataSync` en `app.js`) que sincroniza desde el navegador. O sea
que **lo único que mantenía viva la plataforma era una pestaña abierta en esa página**.

Es frágil por donde se mire: depende de que alguien esté mirando, de que sea justo esa pantalla, y
los datos que alimentan todos los cálculos se congelan los días que nadie entra ahí.

## Decisión
- **Cualquier petición a la app puede disparar la sincronización atrasada.** Un middleware
  *terminable* revisa, después de responder, si alguna fuente venció su cadencia; si venció, encola
  el comando de esa fuente. El visitante nunca espera: la respuesta ya salió.
- **La cadencia es la misma del programador** (`SyncCadence`, PHP puro): Ambient y estación local
  cada 5 minutos, NASA cada 6 horas. La estación local además solo dentro de su horario
  (`config/services.php`), como en el ADR-0016.
- **No reemplaza al programador, lo respalda.** Si hay cron, el tráfico casi nunca encuentra nada
  vencido y esto no hace nada. Si no lo hay —hosting compartido, cron mal puesto—, la app se
  mantiene al día sola mientras alguien la use.
- **Un candado por fuente** (`Cache::lock`) y `WithoutOverlapping` en el job: diez visitas
  simultáneas disparan una sola sincronización.
- **Cada corrida sigue abriendo su `SyncRun`**, porque usa los mismos comandos: la franja de salud y
  la insignia del menú siguen diciendo la verdad sin cambiar nada.
- **Solo en peticiones GET de páginas**, nunca en las de la propia sincronización ni en las que
  esperan JSON: no tiene sentido encolar trabajo detrás de una petición que ya está sincronizando.

## Consecuencias
- ➕ Los datos dejan de depender de que alguien tenga abierta *Datos climáticos*.
- ➕ No necesita cron, ni acceso al servidor, ni consola: sirve en hosting compartido.
- ➖ La primera visita después de un rato deja a un proceso PHP trabajando unos segundos tras
  responder. Con cola y worker ni eso.
- ⚠️ Si nadie entra a la app en todo el día, no se sincroniza nada. Para eso está el cron, que sigue
  siendo la solución correcta; esto es la red debajo.
- ⚠️ `HEARTBEAT_PING_URL` sigue siendo la única forma de enterarse desde fuera de que el programador
  murió, porque con este respaldo los datos ya no se ven congelados.

## Alternativas consideradas
- **Solo arreglar el cron del servidor** — es la solución correcta y hay que ponerla igual, pero ya
  falló una vez sin que nadie se enterara en tres días.
- **Dejar el `setInterval` de la pantalla como está** — es lo que hay hoy: obliga a tener una pestaña
  abierta en la pantalla correcta.
- **Sincronizar dentro de la petición, sin cola ni terminable** — la página se demoraría los segundos
  que tarde la API, y Ambient pide una petición por segundo.

## Relacionado
[[adr-0016-alertas-de-sincronizacion-de-datos]] · [[adr-0009-nasa-power-diario-con-datos-reales]]
