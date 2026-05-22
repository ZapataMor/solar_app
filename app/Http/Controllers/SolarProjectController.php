<?php

namespace App\Http\Controllers;

use App\Models\SolarProject;
use App\Services\NasaPowerService;
use App\Services\SolarCalculationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class SolarProjectController extends Controller
{
    public function index(Request $request): View
    {
        $solarProjects = $request->user()
            ->solarProjects()
            ->latest()
            ->paginate(10);

        return view('solar-projects.index', compact('solarProjects'));
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

    public function show(Request $request, SolarProject $solarProject): View
    {
        $this->authorizeOwner($request, $solarProject);

        $solarProject->load([
            'technicalParameter',
            'calculationResult',
            'monthlyResults' => fn ($query) => $query->orderBy('month_number'),
        ])
            ->loadCount('weatherData');

        return view('solar-projects.show', [
            'solarProject' => $solarProject,
            ...$this->dashboardData($solarProject),
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
    ): RedirectResponse {
        $this->authorizeOwner($request, $solarProject);

        try {
            $payload = $nasaPowerService->fetchHourlyData(
                $solarProject->start_date,
                $solarProject->end_date,
            );

            [$created, $updated] = $this->storeWeatherData($solarProject, $payload);
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

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: int, 1: int}
     */
    private function storeWeatherData(SolarProject $solarProject, array $payload): array
    {
        $parameters = data_get($payload, 'properties.parameter', []);

        $timestamps = collect($parameters)
            ->flatMap(fn (array $values) => array_keys($values))
            ->unique()
            ->sort()
            ->values();

        if ($timestamps->isEmpty()) {
            throw new \RuntimeException('NASA POWER response does not contain hourly values.');
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($solarProject, $parameters, $timestamps, &$created, &$updated) {
            foreach ($timestamps as $timestamp) {
                $weatherData = $solarProject->weatherData()->updateOrCreate(
                    ['date_time' => Carbon::createFromFormat('YmdH', (string) $timestamp)],
                    [
                        'allsky_sfc_sw_dwn' => $this->cleanWeatherValue($parameters['ALLSKY_SFC_SW_DWN'][$timestamp] ?? null),
                        't2m' => $this->cleanWeatherValue($parameters['T2M'][$timestamp] ?? null),
                        'rh2m' => $this->cleanWeatherValue($parameters['RH2M'][$timestamp] ?? null),
                        'prectotcorr' => $this->cleanWeatherValue($parameters['PRECTOTCORR'][$timestamp] ?? null),
                        'ws10m' => $this->cleanWeatherValue($parameters['WS10M'][$timestamp] ?? null),
                    ],
                );

                $weatherData->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        return [$created, $updated];
    }

    private function cleanWeatherValue(mixed $value): ?float
    {
        if ($value === null || (float) $value <= -900) {
            return null;
        }

        return (float) $value;
    }

    private function authorizeOwner(Request $request, SolarProject $solarProject): void
    {
        abort_unless($solarProject->user_id === $request->user()->id, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardData(SolarProject $solarProject): array
    {
        $monthlyResults = $solarProject->monthlyResults;
        $calculationResult = $solarProject->calculationResult;

        return [
            'chartData' => [
                'labels' => $monthlyResults->pluck('month_name')->values()->all(),
                'generation' => $monthlyResults->pluck('estimated_generation_kwh')->map(fn ($value) => (float) $value)->values()->all(),
                'consumption' => $monthlyResults->pluck('estimated_consumption_kwh')->map(fn ($value) => (float) $value)->values()->all(),
                'savings' => $monthlyResults->pluck('estimated_savings_cop')->map(fn ($value) => (float) $value)->values()->all(),
                'coverage' => $monthlyResults->pluck('coverage_percentage')->map(fn ($value) => (float) $value)->values()->all(),
            ],
            'coverageInterpretation' => $calculationResult
                ? $this->coverageInterpretation((float) $calculationResult->coverage_percentage)
                : null,
            'monthlyHighlights' => [
                'highestGeneration' => $monthlyResults->sortByDesc(fn ($result) => (float) $result->estimated_generation_kwh)->first(),
                'lowestGeneration' => $monthlyResults->sortBy(fn ($result) => (float) $result->estimated_generation_kwh)->first(),
                'highestSavings' => $monthlyResults->sortByDesc(fn ($result) => (float) $result->estimated_savings_cop)->first(),
                'lowestCoverage' => $monthlyResults->sortBy(fn ($result) => (float) $result->coverage_percentage)->first(),
            ],
            'monthlyTotals' => [
                'generation' => $monthlyResults->sum('estimated_generation_kwh'),
                'consumption' => $monthlyResults->sum('estimated_consumption_kwh'),
                'savings' => $monthlyResults->sum('estimated_savings_cop'),
            ],
        ];
    }

    private function coverageInterpretation(float $coveragePercentage): string
    {
        if ($coveragePercentage >= 100) {
            return 'La generacion estimada podria cubrir la totalidad del consumo anual registrado.';
        }

        if ($coveragePercentage >= 70) {
            return 'La generacion estimada tendria una cobertura alta del consumo anual.';
        }

        if ($coveragePercentage >= 40) {
            return 'La generacion estimada tendria una cobertura media del consumo anual.';
        }

        return 'La generacion estimada tendria una cobertura baja frente al consumo anual registrado.';
    }
}
