<?php

namespace App\Http\Controllers;

use App\Models\SolarProject;
use App\Models\User;
use App\Models\WeatherStationReading;
use App\Services\NasaPowerService;
use App\Services\NasaWeatherDataService;
use App\Services\ProjectDashboardService;
use App\Services\SolarCalculationService;
use App\Services\WeatherStationAggregationService;
use App\Services\WeatherStationImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
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
            ->withCount(['weatherData', 'weatherStationReadings'])
            ->latest();

        if (! $isAdmin) {
            $solarProjectsQuery->where('user_id', $user->id);
        }

        $solarProjects = $solarProjectsQuery->paginate(12)->withQueryString();
        $globalWeatherStationCount = WeatherStationReading::query()->count();

        $solarProjects->getCollection()->transform(function (SolarProject $solarProject) use ($globalWeatherStationCount) {
            $solarProject->setAttribute('weather_station_readings_count', $globalWeatherStationCount);

            return $solarProject;
        });

        return view('solar-projects.index', [
            'solarProjects' => $solarProjects,
            'isAdmin' => $isAdmin,
        ]);
    }

    public function create(): View
    {
        return view('solar-projects.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProject($request);

        $solarProject = DB::transaction(function () use ($request, $validated) {
            $solarProject = $request->user()->solarProjects()->create([
                ...$this->projectAttributes($validated),
                'location_name' => SolarProject::LOCATION_NAME,
                'latitude' => SolarProject::LATITUDE,
                'longitude' => SolarProject::LONGITUDE,
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
    ): View
    {
        $this->authorizeOwner($request, $solarProject);

        $solarProject->load([
            'technicalParameter',
            'calculationResult',
            'monthlyResults' => fn ($query) => $query->orderBy('month_number'),
        ])
            ->loadCount(['weatherData', 'weatherStationReadings']);

        return view('solar-projects.show', [
            'solarProject' => $solarProject,
            ...$projectDashboardService->build($solarProject),
        ]);
    }

    public function edit(Request $request, SolarProject $solarProject): View
    {
        $this->authorizeOwner($request, $solarProject);

        $solarProject->load('technicalParameter');

        return view('solar-projects.edit', compact('solarProject'));
    }

    public function update(Request $request, SolarProject $solarProject): RedirectResponse
    {
        $this->authorizeOwner($request, $solarProject);

        $validated = $this->validateProject($request);

        DB::transaction(function () use ($solarProject, $validated) {
            $solarProject->update($this->projectAttributes($validated));

            $solarProject->technicalParameter()->updateOrCreate(
                ['solar_project_id' => $solarProject->id],
                $this->technicalParameterAttributes($validated),
            );
        });

        return redirect()
            ->route('solar-projects.show', $solarProject)
            ->with('status', 'Proyecto solar actualizado correctamente.');
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

            ['created' => $created, 'updated' => $updated] = $nasaWeatherDataService->storeDailyData($solarProject, $payload);
            $total = $solarProject->weatherData()->count();
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

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($solarProject, $dailyReadings, &$created, &$updated): void {
            foreach ($dailyReadings as $dailyReading) {
                $weatherData = $solarProject->weatherData()->updateOrCreate(
                    ['date_time' => $dailyReading['date_time']],
                    [
                        'allsky_sfc_sw_dwn' => $dailyReading['allsky_sfc_sw_dwn'],
                        't2m' => $dailyReading['t2m'],
                        'rh2m' => $dailyReading['rh2m'],
                        'prectotcorr' => null,
                        'ws10m' => null,
                    ],
                );

                $weatherData->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        return back()->with(
            'status',
            "Datos del centro meteorologico obtenidos desde el endpoint. Lecturas nuevas: {$imported['created']}. Lecturas existentes omitidas: {$imported['skipped']}. Dias nuevos: {$created}. Dias actualizados: {$updated}.",
        );
    }

    public function calculate(
        Request $request,
        SolarProject $solarProject,
        SolarCalculationService $solarCalculationService,
    ): RedirectResponse {
        $this->authorizeOwner($request, $solarProject);

        $solarProject->loadCount('weatherData');

        if ($solarProject->technicalParameter()->doesntExist()) {
            return back()->withErrors([
                'solar_calculation' => 'No es posible ejecutar calculos solares sin parametros tecnicos.',
            ]);
        }

        if ($solarProject->weather_data_count === 0) {
            return back()->withErrors([
                'solar_calculation' => 'No es posible ejecutar calculos solares sin datos climaticos de NASA POWER.',
            ]);
        }

        try {
            $solarCalculationService->calculate($solarProject);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'solar_calculation' => 'No fue posible ejecutar los calculos solares. Revise los datos del proyecto e intente nuevamente.',
            ]);
        }

        return back()->with('status', 'Calculos solares ejecutados correctamente.');
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
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'solar_calculation' => 'No fue posible ejecutar los calculos solares con datos de la estacion. Revise los datos del proyecto e intente nuevamente.',
            ]);
        }

        return back()->with('status', 'Calculos solares ejecutados correctamente con datos de la estacion meteorologica.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateProject(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'annual_consumption_kwh' => ['required', 'numeric', 'gt:0'],
            'energy_rate_cop_kwh' => ['required', 'numeric', 'gte:0'],
            'available_area_m2' => ['required', 'numeric', 'gt:0'],
            'usable_area_percentage' => ['required', 'numeric', 'between:1,100'],
            'panel_power_w' => ['required', 'numeric', 'gt:0'],
            'panel_area_m2' => ['required', 'numeric', 'gt:0'],
            'performance_ratio' => ['required', 'numeric', 'between:0,1'],
            'system_losses_percentage' => ['required', 'numeric', 'between:0,100'],
        ]);
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
            'annual_consumption_kwh' => $validated['annual_consumption_kwh'],
            'energy_rate_cop_kwh' => $validated['energy_rate_cop_kwh'],
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
}
