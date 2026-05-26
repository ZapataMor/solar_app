<?php

namespace App\Http\Controllers;

use App\Http\Requests\SolarProjectRequest;
use App\Models\ApiWeatherData;
use App\Models\Municipality;
use App\Models\SolarProject;
use App\Models\User;
use App\Models\WeatherStationReading;
use App\Services\NasaPowerService;
use App\Services\NasaWeatherDataService;
use App\Services\ProjectDashboardService;
use App\Services\SolarCalculationService;
use App\Services\SolarInstallationCostService;
use App\Services\AmbientWeatherAggregationService;
use App\Services\WeatherStationAggregationService;
use App\Services\WeatherStationImportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class SolarProjectController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $isAdmin = $user->role === 'admin';

        $solarProjectsQuery = SolarProject::query()
            ->with([
                'user:id,name',
                'calculationResult',
            ])
            ->latest();

        if (! $isAdmin) {
            $solarProjectsQuery->where('user_id', $user->id);
        }

        $solarProjects = $solarProjectsQuery->paginate(12)->withQueryString();
        $solarProjects->getCollection()->transform(function (SolarProject $solarProject) {
            $this->attachWeatherCounts($solarProject);

            return $solarProject;
        });

        return view('solar-projects.index', [
            'solarProjects' => $solarProjects,
            'isAdmin' => $isAdmin,
        ]);
    }

    public function create(): View
    {
        return view('solar-projects.create', [
            'municipalities' => $this->municipalityOptions(),
        ]);
    }

    public function store(SolarProjectRequest $request, SolarInstallationCostService $installationCostService): RedirectResponse
    {
        $validated = $request->validated();
        $municipality = Municipality::query()->findOrFail($validated['municipality_id']);
        try {
            $cost = $installationCostService->calculate(
                $municipality,
                (string) $validated['location_type'],
                (float) $validated['required_power_kw'],
            );
        } catch (RuntimeException) {
            return back()->withInput()->withErrors([
                'municipality_id' => 'No hay precio disponible para esa ubicacion.',
            ]);
        }

        $solarProject = DB::transaction(function () use ($request, $validated, $municipality, $cost) {
            $solarProject = $request->user()->solarProjects()->create([
                ...$this->projectAttributes($validated),
                ...$this->locationAttributes($validated, $municipality, $cost),
            ]);

            $solarProject->technicalParameter()->create($this->technicalParameterAttributes($validated));

            return $solarProject;
        });

        return redirect()
            ->route('solar-projects.show', $solarProject)
            ->with('status', 'Proyecto solar creado correctamente.');
    }

    public function show(
        Request $request,
        SolarProject $solarProject,
        ProjectDashboardService $projectDashboardService,
        NasaWeatherDataService $nasaWeatherDataService,
    ): View
    {
        $this->authorizeOwner($request, $solarProject);

        $solarProject->load([
            'municipality',
            'technicalParameter',
            'calculationResult',
            'monthlyResults' => fn ($query) => $query->orderBy('month_number'),
        ]);

        $solarProject->setRelation('weatherData', $nasaWeatherDataService->dataForProject($solarProject));
        $this->attachWeatherCounts($solarProject);
        $generateAiRecommendations = $request->boolean('generate_ai');
        $aiFocus = $request->string('ai_focus')->toString();

        return view('solar-projects.show', [
            'solarProject' => $solarProject,
            'generateAiRecommendations' => $generateAiRecommendations,
            'aiFocus' => $aiFocus,
            ...$projectDashboardService->build($solarProject, $generateAiRecommendations, $aiFocus),
        ]);
    }

    public function edit(Request $request, SolarProject $solarProject): View
    {
        $this->authorizeOwner($request, $solarProject);

        $solarProject->load('technicalParameter');

        return view('solar-projects.edit', [
            'solarProject' => $solarProject,
            'municipalities' => $this->municipalityOptions(),
        ]);
    }

    public function ambientSimulatorContext(
        Request $request,
        AmbientWeatherAggregationService $ambientWeatherAggregationService,
    ): JsonResponse {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $start = Carbon::parse((string) $validated['start_date'])->startOfDay();
        $end = Carbon::parse((string) $validated['end_date'])->endOfDay();

        $readings = $ambientWeatherAggregationService->latestReadings(5000)
            ->filter(fn ($r) => $r->recorded_at !== null && $r->recorded_at->betweenIncluded($start, $end))
            ->values();

        $dailyRows = $ambientWeatherAggregationService->dailyRows($readings);

        if ($dailyRows->isNotEmpty()) {
            $avgDailyHsp = (float) $dailyRows->avg(fn (array $row) => (((float) $row['allsky_sfc_sw_dwn']) * 24) / 1000);

            return response()->json([
                'source' => 'ambient_range',
                'avg_daily_hsp' => round($avgDailyHsp, 4),
                'days' => $dailyRows->count(),
            ]);
        }

        $fallbackReadings = $ambientWeatherAggregationService->latestReadings(3000);
        $fallbackRows = $ambientWeatherAggregationService->dailyRows($fallbackReadings);
        $fallbackDailyHsp = $fallbackRows->isNotEmpty()
            ? (float) $fallbackRows->avg(fn (array $row) => (((float) $row['allsky_sfc_sw_dwn']) * 24) / 1000)
            : null;

        return response()->json([
            'source' => $fallbackDailyHsp !== null ? 'ambient_recent_fallback' : 'default_fallback',
            'avg_daily_hsp' => $fallbackDailyHsp !== null ? round($fallbackDailyHsp, 4) : null,
            'days' => $fallbackRows->count(),
        ]);
    }

    public function update(
        SolarProjectRequest $request,
        SolarProject $solarProject,
        SolarInstallationCostService $installationCostService,
    ): RedirectResponse
    {
        $this->authorizeOwner($request, $solarProject);

        $validated = $request->validated();
        $municipality = Municipality::query()->findOrFail($validated['municipality_id']);
        try {
            $cost = $installationCostService->calculate(
                $municipality,
                (string) $validated['location_type'],
                (float) $validated['required_power_kw'],
            );
        } catch (RuntimeException) {
            return back()->withInput()->withErrors([
                'municipality_id' => 'No hay precio disponible para esa ubicacion.',
            ]);
        }

        DB::transaction(function () use ($solarProject, $validated, $municipality, $cost) {
            $solarProject->update([
                ...$this->projectAttributes($validated),
                ...$this->locationAttributes($validated, $municipality, $cost),
            ]);

            $solarProject->technicalParameter()->updateOrCreate(
                ['solar_project_id' => $solarProject->id],
                $this->technicalParameterAttributes($validated),
            );
        });

        return redirect()
            ->route('solar-projects.show', $solarProject)
            ->with('status', 'Proyecto solar actualizado correctamente.');
    }

    public function solarPrice(
        Request $request,
        Municipality $municipality,
        SolarInstallationCostService $installationCostService,
    ): JsonResponse {
        abort_unless($municipality->active, 404);

        $validated = $request->validate([
            'location_type' => ['nullable', 'string', 'in:urbana,rural,rural_dispersa,alta_guajira'],
            'required_power_kw' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $locationType = (string) ($validated['location_type'] ?? 'urbana');
        $requiredPowerKw = (float) ($validated['required_power_kw'] ?? 1);
        $cost = $installationCostService->calculate($municipality, $locationType, $requiredPowerKw);

        return response()->json([
            'municipality_id' => $municipality->id,
            'municipality_name' => $municipality->name,
            'zone_name' => $cost['zone_name'],
            'location_type' => $locationType,
            'base_price_per_kw' => $cost['base_price_per_kw'],
            'logistic_factor' => $cost['logistic_factor_used'],
            'final_price_per_kw' => $cost['final_price_per_kw_used'],
            'estimated_installation_cost' => $cost['estimated_installation_cost'],
            'min_price_per_kw' => $cost['min_price_per_kw'],
            'max_price_per_kw' => $cost['max_price_per_kw'],
            'notes' => $cost['notes'],
        ]);
    }

    public function destroy(Request $request, SolarProject $solarProject): RedirectResponse
    {
        $this->authorizeOwner($request, $solarProject);

        $solarProject->delete();

        return redirect()
            ->route('solar-projects.index')
            ->with('status', 'Proyecto solar eliminado correctamente.');
    }

    public function fetchWeatherData(
        Request $request,
        SolarProject $solarProject,
        NasaPowerService $nasaPowerService,
        NasaWeatherDataService $nasaWeatherDataService,
    ): RedirectResponse {
        $this->authorizeOwner($request, $solarProject);

        try {
            $payload = $nasaPowerService->fetchDailyData(
                $solarProject->start_date,
                $solarProject->end_date,
            );

            ['created' => $created, 'updated' => $updated] = $nasaWeatherDataService->storeDailyData($payload);
            $total = $nasaWeatherDataService->countForProject($solarProject);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'weather_data' => 'No fue posible consultar NASA POWER. Intente nuevamente.',
            ]);
        }

        return back()->with(
            'status',
            "Datos climáticos sincronizados. Nuevos: {$created}. Existentes actualizados: {$updated}. Total del proyecto: {$total}.",
        );
    }

    public function fetchWeatherStationData(
        Request $request,
        SolarProject $solarProject,
        WeatherStationImportService $weatherStationImportService,
        WeatherStationAggregationService $weatherStationAggregationService,
    ): RedirectResponse {
        $this->authorizeOwner($request, $solarProject);

        try {
            $imported = $weatherStationImportService->importAll();
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'weather_station' => 'No fue posible consultar el endpoint del centro meteorologico. Intente nuevamente.',
            ]);
        }

        $readings = $weatherStationAggregationService->readingsForProject($solarProject);

        if ($readings->isEmpty()) {
            return back()->withErrors([
                'weather_station' => 'No hay lecturas del centro meteorologico para el rango de fechas del proyecto.',
            ]);
        }

        $dailyReadings = $weatherStationAggregationService->dailyRows($readings);

        if ($dailyReadings->isEmpty()) {
            return back()->withErrors([
                'weather_station' => 'Las lecturas del centro meteorologico no tienen datos de radiacion solar o UV.',
            ]);
        }

        return back()->with(
            'status',
            "Datos del centro meteorologico obtenidos desde el endpoint. Lecturas nuevas: {$imported['created']}. Lecturas existentes omitidas: {$imported['skipped']}. Dias disponibles para este proyecto: {$dailyReadings->count()}.",
        );
    }

    /**
     * Auto-calculate solar metrics choosing the highest-quality data source available.
     *
     * Priority order:
     *   1. Ambient Weather  — direct high-frequency sensor, temperature-derated irradiance
     *   2. Weather Station  — local sensor, temperature-derated irradiance
     *   3. NASA POWER       — satellite daily averages (fallback when no local data exists)
     *
     * Each source applies source-appropriate radiation normalization inside its
     * dailyRows() method so the shared SolarCalculationService always receives
     * a correctly normalized 24h-average irradiance (W/m²).
     */
    public function calculate(
        Request $request,
        SolarProject $solarProject,
        SolarCalculationService $solarCalculationService,
        NasaWeatherDataService $nasaWeatherDataService,
        AmbientWeatherAggregationService $ambientAgg,
        WeatherStationAggregationService $stationAgg,
    ): RedirectResponse {
        $this->authorizeOwner($request, $solarProject);

        if ($solarProject->technicalParameter()->doesntExist()) {
            return back()->withErrors([
                'solar_calculation' => 'No es posible ejecutar calculos solares sin parametros tecnicos.',
            ]);
        }

        // ── Prioridad 1: Ambient Weather ─────────────────────────────────────
        $ambientReadings = $ambientAgg->readingsForProject($solarProject);

        if ($ambientReadings->isNotEmpty()) {
            $ambientDailyRows = $ambientAgg->dailyRows($ambientReadings);

            if ($ambientDailyRows->isNotEmpty()) {
                try {
                    $solarCalculationService->calculate(
                        $solarProject,
                        $solarCalculationService->weatherDataFromRows($ambientDailyRows),
                        'ambient',
                    );
                } catch (Throwable $exception) {
                    report($exception);

                    return back()->withErrors([
                        'solar_calculation' => 'No fue posible ejecutar los calculos con datos de Ambient Weather: ' . $exception->getMessage(),
                    ]);
                }

                $avgCorrection = round(
                    $ambientDailyRows->avg(fn ($r) => $r['temp_correction'] ?? 1.0) * 100,
                    1
                );

                return back()->with(
                    'status',
                    "✓ Calculos ejecutados con datos de Ambient Weather (prioridad 1). "
                    . "Dias procesados: {$ambientDailyRows->count()}. "
                    . "Correccion termica promedio: {$avgCorrection}%.",
                );
            }
        }

        // ── Prioridad 2: Centro meteorologico ────────────────────────────────
        $stationReadings = $stationAgg->readingsForProject($solarProject);

        if ($stationReadings->isNotEmpty()) {
            $stationDailyRows = $stationAgg->dailyRows($stationReadings);

            if ($stationDailyRows->isNotEmpty()) {
                try {
                    $solarCalculationService->calculate(
                        $solarProject,
                        $solarCalculationService->weatherDataFromRows($stationDailyRows),
                        'local',
                    );
                } catch (Throwable $exception) {
                    report($exception);

                    return back()->withErrors([
                        'solar_calculation' => 'No fue posible ejecutar los calculos con datos de la estacion: ' . $exception->getMessage(),
                    ]);
                }

                return back()->with(
                    'status',
                    "✓ Calculos ejecutados con datos del centro meteorologico (prioridad 2 — sin datos Ambient en el rango). "
                    . "Dias procesados: {$stationDailyRows->count()}.",
                );
            }
        }

        // ── Prioridad 3: NASA POWER ───────────────────────────────────────────
        if ($nasaWeatherDataService->countForProject($solarProject) === 0) {
            return back()->withErrors([
                'solar_calculation' => 'No hay datos climaticos disponibles para este proyecto. '
                    . 'Sincroniza al menos una fuente: Ambient Weather, centro meteorologico o NASA POWER.',
            ]);
        }

        try {
            $solarCalculationService->calculate($solarProject, null, 'nasa_power');
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'solar_calculation' => 'No fue posible ejecutar los calculos con NASA POWER: ' . $exception->getMessage(),
            ]);
        }

        return back()->with(
            'status',
            '✓ Calculos ejecutados con datos NASA POWER (prioridad 3 — fallback satelital, sin datos locales en el rango del proyecto).',
        );
    }

    public function calculateWithWeatherStation(
        Request $request,
        SolarProject $solarProject,
        SolarCalculationService $solarCalculationService,
        WeatherStationAggregationService $weatherStationAggregationService,
    ): RedirectResponse {
        $this->authorizeOwner($request, $solarProject);

        if ($solarProject->technicalParameter()->doesntExist()) {
            return back()->withErrors([
                'solar_calculation' => 'No es posible ejecutar calculos solares sin parametros tecnicos.',
            ]);
        }

        $dailyReadings = $weatherStationAggregationService->dailyRows(
            $weatherStationAggregationService->readingsForProject($solarProject)
        );

        if ($dailyReadings->isEmpty()) {
            return back()->withErrors([
                'solar_calculation' => 'No hay datos de estacion meteorologica almacenados para procesar en el rango del proyecto.',
            ]);
        }

        try {
            $solarCalculationService->calculate(
                $solarProject,
                $solarCalculationService->weatherDataFromRows($dailyReadings),
                'local',
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'solar_calculation' => 'No fue posible ejecutar los calculos solares con datos de la estacion. Revise los datos del proyecto e intente nuevamente.',
            ]);
        }

        return back()->with('status', 'Calculos solares ejecutados correctamente con datos de la estacion meteorologica.');
    }

    public function calculateWithAmbientWeather(
        Request $request,
        SolarProject $solarProject,
        SolarCalculationService $solarCalculationService,
        AmbientWeatherAggregationService $ambientWeatherAggregationService,
    ): RedirectResponse {
        $this->authorizeOwner($request, $solarProject);

        if ($solarProject->technicalParameter()->doesntExist()) {
            return back()->withErrors([
                'solar_calculation' => 'No es posible ejecutar calculos solares sin parametros tecnicos.',
            ]);
        }

        $dailyReadings = $ambientWeatherAggregationService->dailyRows(
            $ambientWeatherAggregationService->readingsForProject($solarProject)
        );

        if ($dailyReadings->isEmpty()) {
            return back()->withErrors([
                'solar_calculation' => 'No hay datos de Ambient Weather almacenados para el rango del proyecto. Sincroniza primero desde "Datos APIs".',
            ]);
        }

        try {
            $solarCalculationService->calculate(
                $solarProject,
                $solarCalculationService->weatherDataFromRows($dailyReadings),
                'ambient',
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'solar_calculation' => 'No fue posible ejecutar los calculos solares con datos de Ambient Weather. Revise los datos del proyecto e intente nuevamente.',
            ]);
        }

        return back()->with('status', 'Calculos solares ejecutados correctamente con datos de la estacion Ambient Weather.');
    }

    public function calculateWithNasaPower(
        Request $request,
        SolarProject $solarProject,
        SolarCalculationService $solarCalculationService,
        NasaWeatherDataService $nasaWeatherDataService,
    ): RedirectResponse {
        $this->authorizeOwner($request, $solarProject);

        if ($solarProject->technicalParameter()->doesntExist()) {
            return back()->withErrors([
                'solar_calculation' => 'No es posible ejecutar calculos solares sin parametros tecnicos.',
            ]);
        }

        if ($nasaWeatherDataService->countForProject($solarProject) === 0) {
            return back()->withErrors([
                'solar_calculation' => 'No hay datos NASA POWER en el rango del proyecto. Sincroniza primero con el boton NASA.',
            ]);
        }

        try {
            $solarCalculationService->calculate($solarProject, null, 'nasa_power');
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'solar_calculation' => 'No fue posible ejecutar los calculos solares con NASA POWER. Revise los datos del proyecto e intente nuevamente.',
            ]);
        }

        return back()->with('status', 'Calculos solares ejecutados correctamente con datos NASA POWER.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function projectAttributes(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'monthly_consumption_kwh' => $validated['monthly_consumption_kwh'],
            'energy_rate_cop_kwh' => $validated['energy_rate_cop_kwh'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $cost
     * @return array<string, mixed>
     */
    private function locationAttributes(array $validated, Municipality $municipality, array $cost): array
    {
        return [
            'location_name' => "{$municipality->name}, La Guajira, Colombia",
            'municipality_id' => $municipality->id,
            'latitude' => $validated['latitude'] ?? $municipality->latitude ?? SolarProject::LATITUDE,
            'longitude' => $validated['longitude'] ?? $municipality->longitude ?? SolarProject::LONGITUDE,
            'location_type' => $validated['location_type'],
            'required_power_kw' => $validated['required_power_kw'],
            'base_price_per_kw' => $cost['base_price_per_kw'],
            'logistic_factor_used' => $cost['logistic_factor_used'],
            'final_price_per_kw_used' => $cost['final_price_per_kw_used'],
            'estimated_installation_cost' => $cost['estimated_installation_cost'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function technicalParameterAttributes(array $validated): array
    {
        return [
            'available_area_m2' => $validated['available_area_m2'],
            'usable_area_percentage' => $validated['usable_area_percentage'],
            'panel_power_w' => $validated['panel_power_w'],
            'panel_area_m2' => $validated['panel_area_m2'],
            'performance_ratio' => $validated['performance_ratio'],
            'system_losses_percentage' => $validated['system_losses_percentage'],
        ];
    }

    private function authorizeOwner(Request $request, SolarProject $solarProject): void
    {
        abort_unless(
            $request->user()->role === 'admin' || $solarProject->user_id === $request->user()->id,
            403
        );
    }

    private function attachWeatherCounts(SolarProject $solarProject): void
    {
        $solarProject->setAttribute('weather_data_count', $this->apiWeatherDataCount($solarProject));
        $solarProject->setAttribute('weather_station_readings_count', $this->weatherStationReadingCount($solarProject));
    }

    private function municipalityOptions()
    {
        return Municipality::query()
            ->active()
            ->with(['solarPrices' => fn ($query) => $query->active()])
            ->orderBy('name')
            ->get();
    }

    private function apiWeatherDataCount(SolarProject $solarProject): int
    {
        return ApiWeatherData::query()
            ->whereBetween('date_time', [
                $solarProject->start_date->copy()->startOfDay(),
                $solarProject->end_date->copy()->endOfDay(),
            ])
            ->count();
    }

    private function weatherStationReadingCount(SolarProject $solarProject): int
    {
        return WeatherStationReading::query()
            ->whereBetween('measured_at', [
                $solarProject->start_date->copy()->startOfDay(),
                $solarProject->end_date->copy()->endOfDay(),
            ])
            ->count();
    }
}
