# Integración Ambient Weather — Solar Dashboard

## Resumen

Este documento describe la integración de la API de **Ambient Weather** como tercera fuente de datos climáticos dentro del dashboard solar, junto a la estación local (MeteoEstación) y NASA POWER.

---

## Variables de entorno (.env)

```env
# ── Ambient Weather ─────────────────────────────────────────────
AMBIENT_API_KEY=                  # Clave de usuario (ambientweather.net → Account)
AMBIENT_APPLICATION_KEY=          # Clave de aplicación registrada
AMBIENT_ENABLED=true              # Interruptor maestro
AMBIENT_REQUEST_TIMEOUT=20        # Timeout HTTP en segundos
AMBIENT_CACHE_MINUTES=10          # TTL caché para dispositivos y últimas lecturas
AMBIENT_ONLINE_THRESHOLD_MINUTES=30  # Minutos sin datos → estación offline
```

> Deja `AMBIENT_ENABLED=false` o en blanco `AMBIENT_API_KEY` para deshabilitar completamente la integración sin afectar otros módulos.

---

## Flujo arquitectónico

```
┌─────────────────────────────────────────────────────────┐
│                    Artisan Scheduler                     │
│      routes/console.php — cada 5 min, 06:00–18:30       │
└──────────────────────┬──────────────────────────────────┘
                       │
                       ▼
           ┌──────────────────────┐
           │  SyncAmbientWeather  │  php artisan ambient:sync
           │   (Console Command)  │
           └──────────┬───────────┘
                      │ llama
                      ▼
      ┌───────────────────────────────┐
      │  AmbientWeatherImportService  │
      │  - importLatestForAllDevices()│
      │  - importDevice(mac)          │
      └──────────┬────────────────────┘
                 │ usa
                 ▼
      ┌──────────────────────────┐          ┌─────────────────────┐
      │  AmbientWeatherService   │◄─ cache ─│   Laravel Cache     │
      │  - getDevices()          │          └─────────────────────┘
      │  - getLatestData(mac)    │
      │  - getHistoricalData()   │   ► https://api.ambientweather.net/v1
      │  - normalizeReading()    │
      └──────────────────────────┘
                 │ persiste
                 ▼
      ┌────────────────────────────┐
      │  ambient_weather_readings  │  (tabla MySQL / PostgreSQL)
      │  - mac_address + recorded_at (UNIQUE)
      └──────────────────────────┘
                 │ consulta
                 ▼
  ┌────────────────────────────────────┐
  │  AmbientWeatherAggregationService  │
  │  - readingsForProject(project)     │
  │  - latestReadings(limit)           │
  │  - dailyRows(readings)             │
  │  - stats(readings)                 │
  │  - chartData(readings)             │
  └────────────────┬───────────────────┘
                   │
                   ▼
  ┌─────────────────────────────────────┐
  │   ProjectDashboardService::build()  │
  │   + ClimateSourceFallbackService    │
  └─────────────────────────────────────┘
```

---

## Prioridad de fuentes (ClimateSourceFallbackService)

| Prioridad | Fuente           | Condición de activación                          |
|-----------|------------------|--------------------------------------------------|
| 1         | Estación local   | Lectura en `weather_station_readings` < 30 min   |
| 2         | Ambient Weather  | Lectura en `ambient_weather_readings` < 30 min   |
| 3         | NASA POWER       | Fallback siempre disponible (datos satelitales)  |

El **umbral de 30 minutos** se configura con `AMBIENT_ONLINE_THRESHOLD_MINUTES`.

Si una fuente falla en tiempo de ejecución, se registra un `Log::warning()` y se pasa a la siguiente fuente automáticamente — los cálculos solares nunca se interrumpen.

---

## Normalización de unidades

| Campo Ambient   | Unidad origen | Unidad destino   | Campo interno       |
|-----------------|---------------|------------------|---------------------|
| `dateutc`       | ms epoch / ISO| Carbon UTC       | `recorded_at`       |
| `tempf`         | °F            | °C               | `temperature`       |
| `humidity`      | %             | %                | `humidity`          |
| `windspeedmph`  | mph           | km/h (÷ 1.60934) | `wind_speed`        |
| `winddir`       | grados 0-359  | grados 0-359     | `wind_direction`    |
| `hourlyrainin`  | pulgadas/h    | mm (× 25.4)      | `rainfall`          |
| `uv`            | índice UV     | índice UV        | `uv_index`          |
| `solarradiation`| W/m²          | W/m²             | `solar_radiation`   |

Los campos ausentes o con valor centinela (`-9999`) se almacenan como `null`.

---

## Ejemplo de payload JSON real (Ambient API)

```json
[
  {
    "dateutc": 1716854400000,
    "tempf": 89.6,
    "humidity": 72,
    "windspeedmph": 6.7,
    "winddir": 135,
    "hourlyrainin": 0.0,
    "uv": 8,
    "solarradiation": 620.4,
    "macAddress": "AA:BB:CC:DD:EE:FF"
  }
]
```

Resultado normalizado:

```json
{
  "recorded_at": "2024-05-28 00:00:00",
  "temperature": 32.0,
  "humidity": 72.0,
  "wind_speed": 10.783,
  "wind_direction": 135,
  "rainfall": 0.0,
  "uv_index": 8.0,
  "solar_radiation": 620.4
}
```

---

## Sincronización manual

```bash
# Ejecutar una sincronización puntual
php artisan ambient:sync

# Ver logs recientes
tail -f storage/logs/laravel.log | grep -i ambient
```

Salida esperada:
```
Ambient Weather sincronizado. Recibidos: 2. Guardados: 2. Omitidos (duplicados): 0.
```

---

## Scheduler automático

Definido en `routes/console.php`:

```php
Schedule::command('ambient:sync')
    ->everyFiveMinutes()
    ->between('06:00', '18:30')
    ->timezone(config('app.timezone', 'America/Bogota'))
    ->withoutOverlapping()
    ->onOneServer();
```

El scheduler comparte la misma ventana horaria que la estación local (06:00–18:30), respeta rate limits gracias al caché y usa `withoutOverlapping()` para evitar acumulación en caso de respuesta lenta.

---

## Nuevas claves en el dashboard

`ProjectDashboardService::build()` retorna estos campos adicionales **sin romper los existentes**:

| Clave                      | Descripción                                              |
|----------------------------|----------------------------------------------------------|
| `activeClimateSource`      | `{source, label, online, fallbackUsed, fallbackReason}`  |
| `ambientWeatherStats`      | `{total, averageRadiation, averageTemperature, latest…}` |
| `recentAmbientReadings`    | Últimas 60 lecturas Ambient ordenadas por fecha          |
| `ambientChartData`         | `{labels[], radiation[]}` para gráficas                  |
| `climateSourceComparison`  | Comparativa NASA vs Ambient `{nasa, ambient, delta}`     |

### Fuente climática activa (ejemplo de respuesta)

```json
{
  "activeClimateSource": {
    "source": "ambient",
    "label": "Ambient Weather",
    "online": true,
    "fallbackUsed": true,
    "fallbackReason": "Estación local sin datos recientes."
  }
}
```

---

## Tests

```bash
# Tests unitarios de normalización y API
php artisan test tests/Unit/Services/AmbientWeatherServiceTest.php

# Tests de persistencia (requiere base de datos de prueba)
php artisan test tests/Unit/Services/AmbientWeatherImportServiceTest.php

# Tests de fallback inteligente
php artisan test tests/Feature/ClimateSourceFallbackTest.php

# Suite completa
php artisan test
```

---

## Archivos creados / modificados

| Archivo                                                                  | Tipo          |
|--------------------------------------------------------------------------|---------------|
| `config/ambient.php`                                                     | Nuevo         |
| `database/migrations/2026_05_25_000009_create_ambient_weather_readings…` | Nuevo         |
| `app/Models/AmbientWeatherReading.php`                                   | Nuevo         |
| `app/Services/AmbientWeatherService.php`                                 | Nuevo         |
| `app/Services/AmbientWeatherImportService.php`                           | Nuevo         |
| `app/Services/AmbientWeatherAggregationService.php`                      | Nuevo         |
| `app/Services/ClimateSourceFallbackService.php`                          | Nuevo         |
| `app/Console/Commands/SyncAmbientWeather.php`                            | Nuevo         |
| `database/factories/AmbientWeatherReadingFactory.php`                    | Nuevo         |
| `database/factories/WeatherStationReadingFactory.php`                    | Nuevo         |
| `tests/Unit/Services/AmbientWeatherServiceTest.php`                      | Nuevo         |
| `tests/Unit/Services/AmbientWeatherImportServiceTest.php`                | Nuevo         |
| `tests/Feature/ClimateSourceFallbackTest.php`                            | Nuevo         |
| `routes/console.php`                                                     | Modificado    |
| `app/Services/ProjectDashboardService.php`                               | Modificado    |
