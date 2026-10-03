---
tipo: adr
descripcion: ADR-0016 — Saber si el cron y las sincronizaciones de clima están corriendo, y avisar cuando no
estado: ✅ Implementada
actualizado: 2026-10-03
---

# ADR-0016 · Alertas de sincronización de datos

- **Estado:** 🟢 Aceptada · implementada el 2026-10-03 (puntos 1 a 4 y el aviso externo opcional del 5; ver *Cómo quedó*)
- **Fecha:** 2026-10-02
- **Contexto del repo:** `routes/console.php` (programador), `ambient:sync`, `weather-station:fetch`, `nasa-power:fetch`, cron de Hostinger, pantalla *Datos climáticos*

## Contexto
- **Los datos de clima llegan solos,** gracias a un cron de Hostinger que corre `schedule:run` cada minuto:

  | Fuente | Frecuencia |
  |---|---|
  | Ambient Weather | cada 5 minutos, todo el día |
  | Estación local | cada 5 minutos, de 6:00 a. m. a 6:30 p. m. |
  | NASA POWER | cada 6 horas |
- **Nada avisa cuando eso deja de pasar.** El 2026-10-02 Ambient llevaba horas sin datos nuevos por varias causas, y nadie se enteró hasta mirar la tabla:
  - el programador no corría;
  - la API respondía 429;
  - faltaban certificados en el PHP de la consola.
- **En producción es más grave:** si el cron se cae o una llave vence, "Ahora mismo", los cálculos y la vigencia ([[adr-0010-aviso-de-recalculo]]) se quedan con datos viejos sin que se note.

## Decisión propuesta
1. **Latido del programador.** Una tarea de `Schedule::call` que cada minuto guarde "último tic". Si pasan más de 10 minutos sin tic, el cron no está corriendo.
2. **Registro de cada sincronización.** Cada comando guarda en una tabla `sync_runs`:
   - fuente, inicio, fin, resultado (ok / error / sin datos nuevos);
   - lecturas nuevas, fecha del último dato y mensaje de error, sin llaves (`AmbientWeatherService::withoutKeys()`).
3. **Salud de los datos en *Datos climáticos*.** Una tarjeta por fuente con su estado (al día · atrasada · fallando), la última ejecución y el último dato. Umbrales iniciales:

   | Fuente | Atrasada si no hay datos nuevos en |
   |---|---|
   | Ambient Weather | 20 minutos |
   | Estación local | 30 minutos, dentro de su horario |
   | NASA POWER | 2 días (publica con rezago) |
4. **Aviso visible:** un punto rojo en *Datos climáticos* en el menú del administrador y un aviso al entrar ("Ambient Weather lleva 2 h sin datos nuevos").
5. **Alerta fuera de la app.** Así llega aunque nadie entre. Dos opciones, para elegir:
   - **Monitor externo de latidos** (por ejemplo Healthchecks.io o Better Stack): el programador hace ping con `->pingOnSuccess()` / `->pingOnFailure()` de Laravel, y el servicio avisa por correo o Telegram si deja de recibir pings. No hace falta escribir lógica de alertas propia.
   - **Correo de Laravel** al administrador, cuando una fuente pase a "fallando" o el latido se detenga (requiere configurar `MAIL_*`).

## Cómo quedó (2026-10-03)
**Lo que se decidió de "Por decidir":**
- **Aviso fuera de la app:** monitor externo, no correo propio. El programador hace ping a `HEARTBEAT_PING_URL` cada minuto (vacía = sin ping). El correo de Laravel queda para después, porque `MAIL_MAILER` sigue en `log`.
- **Quién recibe:** el monitor externo avisa a quien se registre allí. Dentro de la app, solo el administrador ve el aviso.

**Latido.** La tarea `scheduler-heartbeat` guarda la hora en la caché cada minuto (`RecordSchedulerHeartbeat`). Pasados 10 minutos sin latido se marca "detenido"; sin ningún latido es "sin latido todavía" y no cuenta como problema (instalación nueva).

**Registro.** La tabla `sync_runs` (`SyncRun`) guarda la fuente, inicio, fin, resultado (`ok`, `empty` o `error`), lecturas nuevas y el mensaje de error, sin llaves. Los tres comandos la llenan; el comando de Ambient también anota como error que la integración esté desactivada. `model:prune` borra lo de más de 30 días, cada día.

**Estados por fuente** (`SourceHealth`, dominio puro):

| Estado | Cuándo |
|---|---|
| Al día | El dato más nuevo es más reciente que el umbral |
| Atrasada | Pasó el umbral y no se conoce un error |
| Fallando | Pasó el umbral y la última ejecución terminó en error |
| Fuera de horario | Estación local, de 6:30 p. m. a 6:00 a. m. |
| Sin datos todavía | La fuente nunca ha tenido datos |

- **Un error con datos frescos no alerta.** Un 429 pasajero no importa si los datos siguen llegando.
- **La estación local cuenta desde que abre:** a las 6:20 a. m., con datos de ayer, aún no está atrasada.
- **NASA** mide su día completo: el dato de un día vale desde que el día termina.
- **Las horas de la estación** salen de `config/services.php`, y el programador usa los mismos valores.

**Dónde se ve.**
- **Salud de la sincronización,** en *Datos climáticos*, arriba de las pestañas: el programador, una tarjeta por fuente (estado, último dato, última ejecución, error) y un aviso rojo cuando algo está atrasado ("Ambient Weather lleva 2 h sin datos nuevos").
- **Insignia roja** con el número de problemas en el menú *Datos climáticos* del administrador.

**Pendiente en producción:** correr `php artisan migrate`; confirmar que el cron de Hostinger ejecuta `schedule:run` cada minuto; y, si se quiere el aviso externo, crear el chequeo en Healthchecks.io o Better Stack y poner su URL en `HEARTBEAT_PING_URL`.

## Consecuencias
- ➕ Se sabe en minutos, y no en días, si los datos dejaron de llegar.
- ➕ La pantalla de salud ayuda a diagnosticar (cron, llaves, límite de la API, SSL) sin entrar por SSH.
- ➖ Una tabla más que limpiar. Conviene guardar 30 días de `sync_runs`.
- ⚠️ Los umbrales deben respetar el horario de cada fuente, para no alertar de noche por la estación local.

## Por decidir
- ¿Agregar un correo de Laravel al administrador cuando una fuente pase a "fallando"? Requiere configurar `MAIL_*`.
- ¿Refrescar la tarjeta de salud sin recargar, tras el botón de sincronizar?

## Relacionado
[[adr-0009-nasa-power-diario-con-datos-reales]] · [[adr-0008-datos-climaticos-en-pestanas]] · [[adr-0010-aviso-de-recalculo]] · [[pitch-primera-etapa]]
