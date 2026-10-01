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
- **Cambios en el cálculo:** van en `SolarCalculator` / `InstallationCostCalculator`, con test
  en `tests/Unit/Domain` (extienden `PHPUnit\Framework\TestCase`, sin base de datos).
- Decisiones de arquitectura: registra un ADR en `natal-ia-vault/03-diseno/decisiones-adr/`.

## Convenciones

- Commits: Conventional Commits (`feat:`, `fix:`, `refactor:`, `docs:`, `test:`, `chore:`).
- Textos visibles al usuario en español; código e identificadores en inglés.
- Las fechas de modelos son `CarbonImmutable` (`Date::use` en `AppServiceProvider`): tipa con
  `CarbonInterface`, no con `Carbon\Carbon`.
- Autorización de proyectos: `authorizeOwner()` en `SolarProjectController` (dueño o `admin`).
- Roles: `user` (cliente) y `admin`. Gates en `AppServiceProvider`: `administer-platform`
  (pantallas de administración, p. ej. Datos climáticos) y `sync-climate-data` (los datos son
  globales). Aplícalos con `can:` en rutas y `@can` en vistas.
- Sidebar (`layouts/app/sidebar.blade.php`): grupos *Centro solar* y *Recursos* para todos;
  *Administración* solo con `@can('administer-platform')`. Lo nuevo de admin va ahí.

## Trampas conocidas

- **La suite ya falla en `main`: 23 tests** (16 fallos + 7 errores). Antes de concluir que rompiste
  algo, compara contra esta línea base (por clase):
  `SolarDashboardTest` 10 · `ApiDataTest` 4 · `AmbientWeatherImportServiceTest` 4 ·
  `AmbientWeatherServiceTest` 1 · `SolarCalculationTest` 1 (mensaje de estado desactualizado) ·
  `SolarProjectTest` 1 · `NasaRadiationFallbackServiceTest` 1 · `ProjectDashboardServiceTest` 1.
  Para comparar con precisión: `vendor/bin/phpunit --log-junit <archivo>`.
- Los tests de `AmbientWeather*` hacen **HTTP real** (fallan sin red o por SSL).
- Pint ya reporta estilo en archivos heredados (`SolarProjectController`,
  `ClimateSourceFallbackService`, …). No reformatees archivos enteros: solo lo que tocas.
- Radiación: se guarda como **W/m² promedio de 24 h**. HSP (kWh/m²/día) = W/m² × 24 / 1000.
- `ProjectDashboardService`, `DashboardTimeScaleService`, `OpenAIRecommendationService` y
  `solar-projects/show.blade.php` son muy grandes: cambios pequeños y verificados.
