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
  `resources/views/solar-projects/partials/appliance-icons.blade.php` (`#appliance-<icon>`).
- **Creación y consumo (ADR-0013):** el formulario (`_form.blade.php`) pregunta tipo de inmueble,
  ubicación, techo, tarifa y nombre; **no** pide consumo. Los equipos se agregan después, por espacio,
  en la pestaña Consumo (`SolarProjectConsumptionController`). Tipos y espacios:
  `App\Domain\Property\PropertyType`. **Los equipos son la base del cálculo:** cada cambio pasa por
  `SaveProjectAppliance`/`RemoveProjectAppliance` → `SyncProjectConsumption` (consumo, potencia
  sugerida y cotización). Sin consumo, calcular lanza `MissingConsumption` y la vigencia queda
  *NOT_READY*. La descripción vive en la pestaña Notas.
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
  el boceto plano del servidor. No tiene pruebas automáticas: revísala en el navegador.
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
- Pint ya reporta estilo en archivos heredados (`SolarProjectController`,
  `ClimateSourceFallbackService`, …). No reformatees archivos enteros: solo lo que tocas.
- Radiación: se guarda como **W/m² promedio de 24 h**. HSP (kWh/m²/día) = W/m² × 24 / 1000.
- **NASA POWER se pide por día, nunca por hora** (ADR-0009): la radiación horaria llega en `-999`
  durante meses. `nasa-power:fetch` reconsulta 45 días para confirmar estimaciones y nunca cambia
  un dato real por uno estimado; `--rebuild` limpia filas horarias y descarga todo de nuevo.
- `ProjectDashboardService`, `DashboardTimeScaleService`, `OpenAIRecommendationService` y
  `solar-projects/show.blade.php` son muy grandes: cambios pequeños y verificados.
