# Natal IA · solar_app

App web que dimensiona sistemas solares fotovoltaicos en La Guajira con datos climáticos reales
(NASA POWER, estación local, Ambient Weather) y da recomendaciones con IA.
**Pitch de la primera etapa: 15 de octubre de 2026** — prioriza lo que se muestra en la demo.

Contexto de negocio, producto y decisiones: [natal-ia-vault/Inicio.md](natal-ia-vault/Inicio.md).

## Comandos

```bash
composer dev                          # servidor + cola + Vite
php artisan test                      # suite completa
php artisan test --filter=NombreTest  # un solo test
composer test                         # lo que corre CI: Pint --test y luego tests (hoy se detiene en Pint)
vendor/bin/pint <archivos>            # formatear lo que tocaste
```

El scheduler (clima cada 5 min, NASA cada hora) está en `routes/console.php`. En Windows lo
dispara `scripts/windows/schedule-run.vbs` desde el Programador de tareas.

## Arquitectura: dónde va cada cosa

Dependencias en una sola dirección: `Http` → `Actions` → `Domain` ← `Infrastructure`.

| Capa | Carpeta | Regla |
|---|---|---|
| Dominio | `app/Domain` | PHP puro. **Nunca** importa `Illuminate`, `App\Models`, `App\Services` ni Carbon. |
| Casos de uso | `app/Actions/SolarProjects` | Orquestan dominio + persistencia. Una clase invocable por caso. |
| Infraestructura | `app/Infrastructure` | Adaptadores de los puertos del dominio (p. ej. `ClimateSource`). |
| HTTP | `app/Http` | Valida, autoriza, llama a una Action y traduce a respuesta. Sin lógica de negocio. |
| Legado | `app/Services` | Se puede corregir, pero **no agregues lógica nueva aquí**; extráela al dominio. |

- **Nueva fuente climática:** implementa `App\Domain\Climate\ClimateSource` en
  `app/Infrastructure/Climate` y regístrala en `ClimateSourceChain` (`AppServiceProvider`). El
  orden del registro es la prioridad.
- **Equipos y consumos de referencia:** `App\Domain\Consumption\ApplianceCatalog` (cada combinación
  de opciones debe tener potencia; lo verifica `ConsumptionEstimatorTest`). Los dibujos están en
  `resources/views/solar-projects/partials/appliance-icons.blade.php` (`#appliance-<icon>`, lista en
  `ApplianceCatalog::ICONS`). Los administradores agregan equipos en *Catálogo de equipos*
  (ADR-0017, tabla `catalog_appliances`): usa siempre el catálogo del contenedor
  (`app(ApplianceCatalog::class)`), nunca `new ApplianceCatalog`, o no verás los agregados. No lo
  inyectes en el constructor de un controlador: el router reutiliza esa instancia entre peticiones.
- **Creación y consumo (ADR-0013, ADR-0020):** el formulario (`_form.blade.php`) pregunta tipo de inmueble,
  ubicación, techo, **cómo se da el consumo**, tarifa y nombre. El consumo tiene dos orígenes
  (`solar_projects.consumption_mode`, `App\Domain\Consumption\ConsumptionMode`): `appliances` (por defecto), con
  los equipos que se agregan después, por espacio, en la pestaña Consumo (`SolarProjectConsumptionController`), o
  `bill`, con los kWh al mes del recibo que escribe el cliente (`monthly_consumption_kwh`; la pestaña Consumo
  muestra `consumption-bill`, y agregar equipos responde 409). Usa `usesBillConsumption()` para ramificar. Tipos y espacios:
  `App\Domain\Property\PropertyType`. **Con equipos, son la base del cálculo:** cada cambio pasa por
  `SaveProjectAppliance`/`RemoveProjectAppliance` → `SyncProjectConsumption` (consumo, potencia
  sugerida y cotización). Sin consumo, calcular lanza `MissingConsumption` y la vigencia queda
  *NOT_READY*. La descripción vive en la pestaña Notas.
- **Periodo de análisis:** son los días de clima con los que se calcula. Por defecto, los últimos tres
  meses hasta hoy; mínimo un mes y nunca después de hoy (`App\Domain\Solar\AnalysisPeriod`, validado en
  `SolarProjectRequest`, con "hoy" en la hora de Bogotá). Un solo día, sobre todo hoy a medio medir,
  dimensiona miles de paneles.
- **Dimensionamiento (ADR-0014):** se instalan los paneles que pide el consumo, con el techo como
  límite (`SystemSizing`); nunca se llena el techo. El mes de un panel es 1/12 del año, como la
  generación mensual de la estimación. El ahorro cuenta solo `min(generación, consumo)`: los
  excedentes no bajan el recibo. `SizeProjectSystem` dimensiona en vivo (franja del diario) y
  `DescribeProjectSystem` arma la pestaña **Mi sistema** (`system.blade.php`), que convive con
  **Técnico** (`show.blade.php`, el panel original) mientras se comparan.
- **Valores de referencia (ADR-0015):** números generales del sistema con vigencia e historial
  (`App\Domain\Reference\ReferenceValueCatalog`; tabla `reference_values`; pantalla de admin
  *Valores de referencia*). Léelos con el puerto `ReferenceValues`, nunca con constantes nuevas.
  `solar_projects.energy_rate_cop_kwh` puede ser nulo: el atributo del modelo devuelve la tarifa de
  referencia por tipo de lugar (`EnergyTariff`); para el valor escrito por el cliente usa
  `ownEnergyRate()`.
- **Ilustración 3D (ADR-0012):** `resources/js/solar-scene/` (Three.js con `import()` dinámico: solo
  carga donde hay `[data-solar-scene]`). Dibuja lo que entregan los `data-*` de la figura en
  `system.blade.php` (los arma `DescribeProjectSystem`) y no calcula nada del negocio; sin WebGL queda
  el boceto plano del servidor. No tiene pruebas automáticas: revísala en el navegador. También está en
  la landing (modo `showcase`, un ejemplo con selector de casa, negocio e institución) y en el último paso
  del formulario (modo `preview`, el techo con los paneles que caben): ambos usan `<x-solar-scene>`, que
  escribe los `data-*`; para cambiarlos con la escena montada, actualiza `figure.dataset` y llama
  `figure.solarScene.refresh()`.
- **Carga de las escenas 3D:** `resources/js/scene-loader.js` las monta. Con WebGL, la figura espera
  su escena con un indicador, sin mostrar el boceto (clase `solar-can-3d` en `<html>`, puesta en
  `partials/head` y repuesta en cada `wire:navigate`). `.is-flat` devuelve el boceto si la escena
  falla o tarda. Cada página con escena la pide en `@push('head')` con `@vite(...)`; las demás la
  precargan en segundo plano (`Vite::prefetch` en `AppServiceProvider`).
- **Equipos en 3D (ADR-0019):** al agregar o editar un equipo del diario, la hoja muestra a la derecha
  el equipo como lo dejan sus opciones (`resources/js/appliance-scene/`). Un archivo por equipo
  (`frame`, `build(materials, variant)`, `note(variant)`; las piezas comunes en `parts.js`) registrado
  en `MODELS` de `index.js` con la clave del catálogo; sin esa línea no hay recuadro, y
  `ApplianceConsumptionTest` falla si un equipo del catálogo queda sin modelo. El script del diario
  escribe `data-appliance`, `data-variant` y `data-variant-label` en la figura.
- **Estaciones en 3D (ADR-0018):** el encabezado de *Datos climáticos* muestra la estación de la
  pestaña elegida (`resources/js/station-scene/`, un solo lienzo para las tres; el globo y su mapa
  dibujado están en `earth.js`). La figura (`api-data/partials/station-figure.blade.php`) sigue la
  pestaña por `data-station` (`showApiDataTab` en `app.js`). La veleta usa el viento de la última
  lectura (`DescribeDataStations`); la sincronización de Ambient lo reenvía en `wind`. Los conteos
  viven en las pestañas (`data-ambient-count`…): no vuelvas a ponerlos en el encabezado.
- **Diseñador 3D (ADR-0021):** pantalla solo de desarrollo (gate `design-3d`: admin y `APP_ENV=local`; ítem
  *Desarrollo* del sidebar). Su primer modelo, `resources/js/system-scene/`, anima un día de un sistema híbrido en
  una casa en corte (cuarto técnico a la izquierda, sala a la derecha, techo plano con los paneles en su
  estructura). Los equipos y cables van a menos de 2,5 m: la losa tapa lo de más arriba.
  `simulation.js` (puro, sin DOM ni Three.js) decide el sol, lo que producen los paneles y a
  dónde va la energía (paneles → casa → baterías → red; sin energía, apagón); los demás archivos solo muestran
  su resultado (`readout`): `model.js` arma la casa y los cables, `equipment.js` los equipos con sus pantallas,
  `flow.js` la corriente y los haces de luz, `atmosphere.js` el cielo, las luces y el clima según la hora,
  `controls.js` los controles de la página (`data-system-*` en `designer/index.blade.php`) y `scene.js` el
  ciclo. Cada parte lleva `userData.info = {title, text, live(readout)}` para su etiqueta. Sin pruebas
  automáticas de la escena: revísala en `/disenador-3d`; en la consola, `figure.systemScene.advance(10)` deja pasar
  diez segundos de golpe y `figure.systemScene.simulation` permite cambiar la hora o las condiciones.
- **Salud de la sincronización (ADR-0016):** cada comando de clima (`ambient:sync`, `weather-station:fetch`,
  `nasa-power:fetch`) abre un `SyncRun` y lo cierra con `complete()` o `fail()`: **un comando de sincronización
  nuevo debe hacer lo mismo**. `DescribeSyncHealth` arma la franja de *Datos climáticos* y la insignia roja
  del menú; los umbrales y los estados están en `App\Domain\Sync`. El latido del programador
  (`scheduler-heartbeat`, cada minuto) prueba que el cron corre; `HEARTBEAT_PING_URL` lo avisa a un monitor
  externo. Las horas de la estación local están en `config/services.php`, no en `routes/console.php`.
- **Instaladores (ADR-0022):** `/instaladores` es el directorio del cliente: elige uno de sus proyectos
  y le pide cotización a quien cubre su municipio (`DescribeInstallerDirectory`,
  `RequestInstallerQuote`). La solicitud (`quote_requests`) es el lead del ADR-0005: **los datos de
  contacto solo se muestran a quien ya la pidió**, y sin consumo no se cotiza (`QuoteNotPossible`).
  El instalador es un registro, no un usuario: lo crea el admin desde la misma pantalla
  (`can:administer-platform`); el rol `installer` del ADR-0003 todavía no existe. Los de
  `InstallerSeeder` son de ejemplo y la página lo advierte: quita el aviso cuando entren reales.
- **Mapa de cobertura (ADR-0022):** el formulario de instalador elige municipios en el mismo mapa del
  formulario de proyecto (`resources/js/coverage-map.js`, Leaflet desde el CDN y solo en esa página).
  Usa `public/maps/la_guajira_municipios_simple.geojson` (26 KB): el original del IGAC (4 MB) congela la
  pestaña al repintar, y `scripts/maps/simplify-geojson.py` lo regenera. Los códigos DANE que unen cada
  municipio con su polígono están en `App\Domain\Property\MunicipalityBoundaries`, que comparten los dos
  formularios. Las casillas son el campo real; el mapa solo las marca. Tres cosas que no hay que
  deshacer: se dibuja en **SVG** (en canvas el hover va lento porque repinta los 15 polígonos), solo se
  repinta el municipio que cambió, y *Seleccionar todos* **dispara un `change`** o el mapa queda
  pintado con la respuesta vieja. Cada forma lleva su nombre (fijo solo las grandes): la etiqueta de
  ciudad que trae la tesela engaña, porque el municipio de Riohacha es enorme y la ciudad un punto.
  Las teselas y los colores son los del formulario de proyecto (oro = municipio, naranja quemado =
  cubierto), sin filtros encima. Los estilos de Leaflet se sobrescriben **fuera** de
  `@layer components`: viene del CDN sin capa y en Tailwind v4 lo no-capado gana.
- **Bandeja del instalador (ADR-0023):** el instalador entra con su propia cuenta (rol `installer` en
  `users.role` + `users.installer_id`; la crea el admin desde la ficha, no hay registro público) y cae en
  `/solicitudes` (`LoginResponse` en `FortifyServiceProvider`). La lista dice cuál abrir y cada solicitud
  tiene su página (`installer-inbox.show`), con la estimación y **el diario de equipos del cliente**
  (`BuildConsumptionDiary`; con recibo no hay diario, ADR-0020). Responde con `QuoteRequestStatus`:
  contactada, ganada o perdida, y **ganada exige el valor del contrato** (`QuoteNotPossible`); no se
  calcula comisión porque el ADR-0005 no tiene porcentaje. *Precios de referencia* le muestra el $/kW por
  municipio del que sale el presupuesto que ya vio el cliente. Gate `answer-quote-requests`; el sidebar le
  oculta *Centro solar* y los *Recursos* del cliente. Cuentas de prueba en `InstallerAccountSeeder`.
- **Precios por municipio (ADR-0024):** pantalla de admin *Precios por municipio*; el precio final es
  `base × factor logístico` y hay **uno solo por municipio y tipo de ubicación** (índice único; antes se
  podían duplicar y el costo dependía del orden de inserción). **Guardar no toca lo ya cotizado:** el
  proyecto conserva su `estimated_installation_cost` y la solicitud su `quoted_cost_cop`, congelado por
  `RequestInstallerQuote`. Si vuelves a cotizar al guardar, rompes la decisión. Un proyecto que el
  cliente sigue editando sí se recotiza (`SyncProjectConsumption`). Desactivar un precio hace que el
  municipio cotice con su precio urbano (`SolarInstallationCostService`).
- **Cambios en el cálculo:** van en `SolarCalculator` / `InstallationCostCalculator`, con test
  en `tests/Unit/Domain` (extienden `PHPUnit\Framework\TestCase`, sin base de datos).
- Decisiones de arquitectura: registra un ADR en `natal-ia-vault/03-diseno/decisiones-adr/`.

## Convenciones

- Commits: Conventional Commits (`feat:`, `fix:`, `refactor:`, `docs:`, `test:`, `chore:`).
- Textos visibles al usuario en español; código e identificadores en inglés.
- Las fechas de modelos son `CarbonImmutable` (`Date::use` en `AppServiceProvider`): tipa con
  `CarbonInterface`, no con `Carbon\Carbon`.
- Autorización de proyectos: `SolarProjectPolicy::manage` (dueño o `admin`); en controladores,
  `abort_unless($request->user()->can('manage', $solarProject), 403)`.
- Roles: `user` (cliente) y `admin`. Gates en `AppServiceProvider`: `administer-platform`
  (pantallas de administración, p. ej. Datos climáticos) y `sync-climate-data` (los datos son
  globales). Aplícalos con `can:` en rutas y `@can` en vistas.
- Sidebar (`layouts/app/sidebar.blade.php`): grupos *Centro solar* y *Recursos* para todos;
  *Administración* solo con `@can('administer-platform')`. Lo nuevo de admin va ahí.

## Trampas conocidas

- **La suite ya falla en `main`: 22 tests.** Antes de concluir que rompiste algo, compara contra
  esta línea base (por clase):
  `SolarDashboardTest` 10 · `ApiDataTest` 4 · `AmbientWeatherImportServiceTest` 4 ·
  `SolarCalculationTest` 1 (mensaje de estado desactualizado) · `SolarProjectTest` 1 ·
  `NasaRadiationFallbackServiceTest` 1 · `ProjectDashboardServiceTest` 1.
  Para comparar con precisión: `vendor/bin/phpunit --log-junit <archivo>`.
- Los tests viejos de `AmbientWeather*` hacen **HTTP real** (fallan sin red o por SSL); los nuevos
  (`AmbientWeatherSyncTest`) usan `Http::fake()` y `Sleep::fake()`.
- **Ambient Weather:** `ambient:sync` (cada 5 min, todo el día) y el botón traen desde la última
  lectura guardada hasta ahora (`importRecentForAllDevices`). La API admite 1 petición por segundo:
  `AmbientWeatherService::get()` espacia las peticiones y reintenta los 429. Las URLs llevan las
  llaves: los errores pasan por `AmbientWeatherService::withoutKeys()`. El año completo de historial
  es `php artisan ambient:sync-history`.
- **PHP en Windows:** el PHP de WinGet no trae certificados (cURL error 60 con las APIs HTTPS); el de
  Herd sí. `scripts/windows/schedule-run.vbs` usa `C:\tools\php84`, luego Herd y luego el del PATH.
- SQLite + `php artisan serve` en Windows: el servidor no recibe `TEMP`/`TMP` y las consultas grandes
  fallaban con "unable to open database file". `AppServiceProvider` fija `PRAGMA temp_store = MEMORY`.
- Resultados mensuales: la generación cubre solo los días con datos (`days_in_month`); compara
  meses **por día**, no por total, o un mes con pocos datos parecerá sin sol.
- Tests de vistas: los `data-*` y nombres de campos también aparecen en el JS inline de la página, así
  que `assertDontSee('data-x')` falla aunque el atributo no esté. Comprueba la etiqueta con una regex
  (`/<form[^>]*data-x/`).
- **Tailwind v4 oculta `[hidden]` con `!important`** (preflight, capa base): ningún CSS lo vuelve a
  mostrar. Lo que deba aparecer según un estado (p. ej. el escenario 3D mientras carga) se controla
  con clases, no con el atributo `hidden`.
- Pint ya reporta estilo en archivos heredados (`SolarProjectController`,
  `ClimateSourceFallbackService`, …). No reformatees archivos enteros: solo lo que tocas.
- Radiación: se guarda como **W/m² promedio de 24 h**. HSP (kWh/m²/día) = W/m² × 24 / 1000.
- **NASA POWER se pide por día, nunca por hora** (ADR-0009): la radiación horaria llega en `-999`
  durante meses. `nasa-power:fetch` reconsulta 45 días para confirmar estimaciones y nunca cambia
  un dato real por uno estimado; `--rebuild` limpia filas horarias y descarga todo de nuevo.
- `ProjectDashboardService`, `DashboardTimeScaleService`, `OpenAIRecommendationService` y
  `solar-projects/show.blade.php` son muy grandes: cambios pequeños y verificados.
