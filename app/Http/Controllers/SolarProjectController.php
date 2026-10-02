<?php

namespace App\Http\Controllers;

use App\Actions\SolarProjects\CalculateSolarProject;
use App\Actions\SolarProjects\CheckCalculationFreshness;
use App\Actions\SolarProjects\ExplainSolarProject;
use App\Actions\SolarProjects\RecalculateProjects;
use App\Actions\SolarProjects\SaveSolarProject;
use App\Domain\Climate\ClimateSeries;
use App\Domain\Climate\ClimateSource;
use App\Domain\Climate\NoClimateData;
use App\Domain\Pricing\PriceNotAvailable;
use App\Domain\Solar\MissingConsumption;
use App\Domain\Solar\MissingTechnicalParameters;
use App\Http\Requests\SolarProjectRequest;
use App\Models\AmbientWeatherReading;
use App\Models\ApiWeatherData;
use App\Models\Municipality;
use App\Models\SolarProject;
use App\Models\User;
use App\Models\WeatherStationReading;
use App\Services\NasaPowerService;
use App\Services\NasaWeatherDataService;
use App\Services\AiForecastPredictionService;
use App\Services\ProjectDashboardService;
use App\Services\SolarProjectAiHistoryService;
use App\Services\SolarInstallationCostService;
use App\Services\WeatherStationAggregationService;
use App\Services\WeatherStationImportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class SolarProjectController extends Controller
{
    public function index(Request $request): View
    {
        return view('solar-projects.index', $this->portfolioData($request));
    }

    /**
     * Projects the user can see: all for admins, their own otherwise.
     */
    private function visibleProjectsQuery(User $user): Builder
    {
        return SolarProject::query()
            ->when(! $user->isAdmin(), fn (Builder $query) => $query->where('user_id', $user->id));
    }

    /**
     * Data for the project portfolio (listing).
     *
     * @return array<string, mixed>
     */
    private function portfolioData(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $isAdmin = $user->isAdmin();
        $checkFreshness = app(CheckCalculationFreshness::class);

        $solarProjectsQuery = $this->visibleProjectsQuery($user)
            ->with([
                'user:id,name',
                'calculationResult',
                'technicalParameter',
            ])
            ->latest();

        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $solarProjectsQuery->where('name', 'like', '%'.$search.'%');
        }

        $solarProjects = $solarProjectsQuery->paginate(12)
            ->withPath(route('solar-projects.index'))
            ->withQueryString();
        $solarProjects->getCollection()->transform(function (SolarProject $solarProject) {
            $this->attachWeatherCounts($solarProject);

            return $solarProject;
        });

        $portfolioQuery = array_filter([
            'search' => $search,
            'page' => $request->query('page'),
        ], fn ($value) => filled($value));

        // "!" indicator: which cards need a recalculation, and how many projects in total.
        $projectFreshness = $solarProjects->getCollection()
            ->mapWithKeys(fn (SolarProject $solarProject) => [$solarProject->id => $checkFreshness($solarProject)])
            ->all();
        $projectsToRecalculate = $this->visibleProjectsQuery($user)
            ->with(['calculationResult', 'technicalParameter'])
            ->get()
            ->filter(fn (SolarProject $solarProject) => $checkFreshness($solarProject)->needsRecalculation())
            ->count();

        return [
            'solarProjects' => $solarProjects,
            'isAdmin' => $isAdmin,
            'search' => $search,
            'portfolioQuery' => $portfolioQuery,
            'projectFreshness' => $projectFreshness,
            'projectsToRecalculate' => $projectsToRecalculate,
        ];
    }

    /**
     * Recalculates, with the best available source, only the visible projects that need it.
     */
    public function recalculateOutdated(Request $request, RecalculateProjects $recalculateProjects): RedirectResponse
    {
        $summary = $recalculateProjects(
            $this->visibleProjectsQuery($request->user())->with(['calculationResult', 'technicalParameter'])->get()
        );

        if ($summary['recalculated'] === 0 && $summary['failed'] === 0) {
            return back()->with('status', 'Todos tus proyectos ya estaban al día.');
        }

        $message = $summary['recalculated'] === 1
            ? 'Se recalculó 1 proyecto.'
            : "Se recalcularon {$summary['recalculated']} proyectos.";

        if ($summary['failed'] > 0) {
            $message .= " {$summary['failed']} no se pudieron calcular (revisa sus datos climáticos o parámetros).";
        }

        return back()->with('status', $message);
    }

    public function create(): View
    {
        return view('solar-projects.create', [
            'municipalities' => $this->municipalityOptions(),
        ]);
    }

    public function store(SolarProjectRequest $request, SaveSolarProject $saveSolarProject): RedirectResponse
    {
        try {
            $solarProject = $saveSolarProject($request->user(), $request->validated());
        } catch (PriceNotAvailable $exception) {
            return back()->withInput()->withErrors(['municipality_id' => $exception->getMessage()]);
        }

        // ADR-0013: the appliances are added next, space by space, in the consumption diary.
        return redirect()
            ->route('solar-projects.consumption', $solarProject)
            ->with('status', 'Proyecto creado. Ahora agrega los equipos de cada espacio para calcular tu sistema.');
    }

    public function show(
        Request $request,
        SolarProject $solarProject,
        ProjectDashboardService $projectDashboardService,
        NasaWeatherDataService $nasaWeatherDataService,
        SolarProjectAiHistoryService $aiHistoryService,
        CheckCalculationFreshness $checkFreshness,
        ExplainSolarProject $explainSolarProject,
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

        $calculationFreshness = $checkFreshness($solarProject);

        return view('solar-projects.show', [
            'portfolioUrl' => $this->portfolioUrl($request),
            'calculationFreshness' => $calculationFreshness,
            'projectQuestions' => $explainSolarProject($solarProject, $calculationFreshness),
            'solarProject' => $solarProject,
            'generateAiRecommendations' => $generateAiRecommendations,
            'aiFocus' => $aiFocus,
            'aiRecommendationHistory' => $aiHistoryService->recommendationHistory($solarProject),
            'aiPredictionHistory' => $aiHistoryService->predictionHistory($solarProject),
            'liveReadingStamp' => $this->latestReadingStamp(),
            ...$projectDashboardService->build($solarProject, $generateAiRecommendations, $aiFocus),
        ]);
    }

    /**
     * Cheap endpoint polled by the dashboard: tells the page whether a new
     * station reading arrived so it can refresh its live sections.
     */
    public function liveStatus(Request $request, SolarProject $solarProject): JsonResponse
    {
        $this->authorizeOwner($request, $solarProject);

        return response()->json(['stamp' => $this->latestReadingStamp()]);
    }

    private function latestReadingStamp(): string
    {
        return implode('|', [
            (string) AmbientWeatherReading::query()->max('recorded_at'),
            (string) WeatherStationReading::query()->max('measured_at'),
        ]);
    }

    public function edit(Request $request, SolarProject $solarProject): View
    {
        $this->authorizeOwner($request, $solarProject);

        $solarProject->load(['technicalParameter', 'municipality']);

        return view('solar-projects.edit', [
            'solarProject' => $solarProject,
            'municipalities' => $this->municipalityOptions(),
            'portfolioUrl' => $this->portfolioUrl($request),
        ]);
    }

    public function update(
        SolarProjectRequest $request,
        SolarProject $solarProject,
        SaveSolarProject $saveSolarProject,
    ): RedirectResponse
    {
        $this->authorizeOwner($request, $solarProject);

        try {
            $saveSolarProject($request->user(), $request->validated(), $solarProject);
        } catch (PriceNotAvailable $exception) {
            return back()->withInput()->withErrors(['municipality_id' => $exception->getMessage()]);
        }

        return redirect()
            ->route('solar-projects.show', $solarProject)
            ->with('status', 'Proyecto solar actualizado correctamente.');
    }

    public function aiRecommendations(
        Request $request,
        SolarProject $solarProject,
        ProjectDashboardService $projectDashboardService,
        NasaWeatherDataService $nasaWeatherDataService,
        SolarProjectAiHistoryService $aiHistoryService,
    ): JsonResponse {
        $this->authorizeOwner($request, $solarProject);

        $validated = $request->validate([
            'ai_focus' => ['required', 'string', 'in:savings,load_shift,risk,maintenance,climate'],
        ]);

        $solarProject->load([
            'technicalParameter',
            'calculationResult',
            'monthlyResults' => fn ($query) => $query->orderBy('month_number'),
        ]);
        $solarProject->setRelation('weatherData', $nasaWeatherDataService->dataForProject($solarProject));
        $this->attachWeatherCounts($solarProject);

        $dashboardPayload = $projectDashboardService->build($solarProject, true, (string) $validated['ai_focus']);
        $executiveSummary = $dashboardPayload['dashboard']['executiveSummary'] ?? [];
        $focusRecommendation = collect($executiveSummary['recommendationPack'] ?? [])
            ->firstWhere('key', (string) $validated['ai_focus']);
        $dailyRecommendation = trim((string) ($executiveSummary['dailyRecommendation'] ?? ''));
        $focusMessage = trim((string) ($focusRecommendation['message'] ?? ''));
        $message = $focusMessage !== '' ? $focusMessage : $dailyRecommendation;

        if ($message === '') {
            $message = 'No se genero una recomendacion utilizable. Reintenta cuando el proyecto tenga calculos y datos climaticos disponibles.';
        }

        $focusLabel = (string) ($focusRecommendation['title'] ?? $this->aiFocusLabel((string) $validated['ai_focus']));
        $source = (string) ($executiveSummary['source'] ?? 'ia');
        $userPrompt = 'Generar enfoque '.$focusLabel;
        $generatedAt = now();

        $aiHistoryService->record($solarProject, [
            'type' => 'recommendation',
            'role' => 'user',
            'focus' => (string) $validated['ai_focus'],
            'focus_label' => $focusLabel,
            'source' => $source,
            'message' => $userPrompt,
            'metadata' => [
                'request' => ['ai_focus' => (string) $validated['ai_focus']],
            ],
            'generated_at' => $generatedAt,
        ]);
        $historyMessage = $aiHistoryService->record($solarProject, [
            'type' => 'recommendation',
            'role' => 'assistant',
            'focus' => (string) $validated['ai_focus'],
            'focus_label' => $focusLabel,
            'source' => $source,
            'title' => $focusLabel,
            'message' => $message,
            'summary' => trim((string) ($executiveSummary['text'] ?? '')),
            'metadata' => [
                'alerts' => array_values(array_filter(
                    $executiveSummary['alerts'] ?? [],
                    fn ($item) => is_string($item) && trim($item) !== ''
                )),
                'error' => $executiveSummary['error'] ?? null,
            ],
            'generated_at' => $generatedAt,
        ]);

        return response()->json([
            'source' => $source,
            'focus' => (string) $validated['ai_focus'],
            'focus_label' => $focusLabel,
            'summary' => trim((string) ($executiveSummary['text'] ?? '')),
            'message' => $message,
            'alerts' => array_values(array_filter(
                $executiveSummary['alerts'] ?? [],
                fn ($item) => is_string($item) && trim($item) !== ''
            )),
            'error' => $executiveSummary['error'] ?? null,
            'generated_at' => $generatedAt->toIso8601String(),
            'history_message' => $aiHistoryService->serializeRecommendationMessage($historyMessage),
        ]);
    }

    public function aiPrediction(
        Request $request,
        SolarProject $solarProject,
        ProjectDashboardService $projectDashboardService,
        NasaWeatherDataService $nasaWeatherDataService,
        AiForecastPredictionService $aiForecastPredictionService,
        SolarProjectAiHistoryService $aiHistoryService,
    ): JsonResponse {
        $this->authorizeOwner($request, $solarProject);

        $solarProject->load([
            'municipality',
            'technicalParameter',
            'calculationResult',
            'monthlyResults' => fn ($query) => $query->orderBy('month_number'),
        ]);
        $solarProject->setRelation('weatherData', $nasaWeatherDataService->dataForProject($solarProject));
        $this->attachWeatherCounts($solarProject);

        $dashboardPayload = $projectDashboardService->build($solarProject, false);
        $futurePredictions = $dashboardPayload['dashboard']['futurePredictions'] ?? [];

        $generatedAt = now();
        $result = [
            ...$aiForecastPredictionService->generate($solarProject, $futurePredictions),
            'generated_at' => $generatedAt->toIso8601String(),
        ];
        $historyMessage = $aiHistoryService->record($solarProject, [
            'type' => 'prediction',
            'role' => 'assistant',
            'source' => (string) ($result['source'] ?? 'ia'),
            'title' => $result['title'] ?? 'Prediccion IA proxima semana',
            'message' => $result['prediction'] ?? null,
            'summary' => $result['prediction'] ?? null,
            'metadata' => [
                'temperature_outlook' => $result['temperature_outlook'] ?? null,
                'solar_window' => $result['solar_window'] ?? null,
                'actions' => $result['actions'] ?? [],
                'confidence' => $result['confidence'] ?? 'media',
                'error' => $result['error'] ?? null,
                'data_window' => $futurePredictions['data_window'] ?? [],
            ],
            'generated_at' => $generatedAt,
        ]);

        return response()->json([
            ...$result,
            'history_message' => $aiHistoryService->serializePredictionMessage($historyMessage),
        ]);
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

    private function aiFocusLabel(string $focus): string
    {
        return match ($focus) {
            'savings' => 'Ahorro economico',
            'load_shift' => 'Traslado de cargas',
            'risk' => 'Riesgo operativo',
            'maintenance' => 'Mantenimiento',
            'climate' => 'Adaptacion climatica',
            default => 'Enfoque operativo',
        };
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
     * Auto-calculate solar metrics choosing the highest-quality data source available
     * (Ambient Weather → centro meteorologico → NASA POWER).
     */
    public function calculate(Request $request, SolarProject $solarProject, CalculateSolarProject $calculateSolarProject): RedirectResponse
    {
        return $this->runCalculation($request, $solarProject, $calculateSolarProject, null);
    }

    public function calculateWithWeatherStation(Request $request, SolarProject $solarProject, CalculateSolarProject $calculateSolarProject): RedirectResponse
    {
        return $this->runCalculation($request, $solarProject, $calculateSolarProject, ClimateSource::LOCAL);
    }

    public function calculateWithAmbientWeather(Request $request, SolarProject $solarProject, CalculateSolarProject $calculateSolarProject): RedirectResponse
    {
        return $this->runCalculation($request, $solarProject, $calculateSolarProject, ClimateSource::AMBIENT);
    }

    public function calculateWithNasaPower(Request $request, SolarProject $solarProject, CalculateSolarProject $calculateSolarProject): RedirectResponse
    {
        return $this->runCalculation($request, $solarProject, $calculateSolarProject, ClimateSource::NASA_POWER);
    }

    /**
     * Messages shown after a calculation, per requested source (`auto` = best available).
     */
    private const CALCULATION_MESSAGES = [
        'auto' => [
            'no_data' => 'No hay datos climaticos disponibles para este proyecto. Sincroniza al menos una fuente: Ambient Weather, centro meteorologico o NASA POWER.',
            'failed' => 'No fue posible ejecutar los calculos solares: ',
        ],
        ClimateSource::LOCAL => [
            'no_data' => 'No hay datos de estacion meteorologica almacenados para procesar en el rango del proyecto.',
            'failed' => 'No fue posible ejecutar los calculos solares con datos de la estacion. Revise los datos del proyecto e intente nuevamente.',
            'success' => 'Calculos solares ejecutados correctamente con datos de la estacion meteorologica.',
        ],
        ClimateSource::AMBIENT => [
            'no_data' => 'No hay datos de Ambient Weather almacenados para el rango del proyecto. Sincroniza primero desde "Datos APIs".',
            'failed' => 'No fue posible ejecutar los calculos solares con datos de Ambient Weather. Revise los datos del proyecto e intente nuevamente.',
            'success' => 'Calculos solares ejecutados correctamente con datos de la estacion Ambient Weather.',
        ],
        ClimateSource::NASA_POWER => [
            'no_data' => 'No hay datos NASA POWER en el rango del proyecto. Sincroniza primero con el boton NASA.',
            'failed' => 'No fue posible ejecutar los calculos solares con NASA POWER. Revise los datos del proyecto e intente nuevamente.',
            'success' => 'Calculos solares ejecutados correctamente con datos NASA POWER.',
        ],
    ];

    private function runCalculation(
        Request $request,
        SolarProject $solarProject,
        CalculateSolarProject $calculateSolarProject,
        ?string $source,
    ): RedirectResponse {
        $this->authorizeOwner($request, $solarProject);
        $messages = self::CALCULATION_MESSAGES[$source ?? 'auto'];

        try {
            $series = $calculateSolarProject($solarProject, $source);
        } catch (MissingTechnicalParameters|MissingConsumption $exception) {
            return back()->withErrors(['solar_calculation' => $exception->getMessage()]);
        } catch (NoClimateData) {
            return back()->withErrors(['solar_calculation' => $messages['no_data']]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'solar_calculation' => $source === null ? $messages['failed'].$exception->getMessage() : $messages['failed'],
            ]);
        }

        $redirect = match ($request->input('then')) {
            'panel' => redirect()->route('solar-projects.show', $solarProject), // From the consumption diary: go see the results.
            'system' => redirect()->route('solar-projects.system', $solarProject), // From the alternative panel (ADR-0014).
            default => back(),
        };

        return $redirect->with('status', $messages['success'] ?? $this->autoCalculationMessage($series));
    }

    private function autoCalculationMessage(ClimateSeries $series): string
    {
        return match ($series->source) {
            ClimateSource::AMBIENT => '✓ Calculos ejecutados con datos de Ambient Weather (prioridad 1). '
                ."Dias procesados: {$series->dayCount()}. "
                .'Correccion termica promedio: '.round(($series->metadata['average_temperature_correction'] ?? 1.0) * 100, 1).'%.',
            ClimateSource::LOCAL => '✓ Calculos ejecutados con datos del centro meteorologico (prioridad 2 — sin datos Ambient en el rango). '
                ."Dias procesados: {$series->dayCount()}.",
            default => '✓ Calculos ejecutados con datos NASA POWER (prioridad 3 — fallback satelital, sin datos locales en el rango del proyecto).',
        };
    }

    private function authorizeOwner(Request $request, SolarProject $solarProject): void
    {
        abort_unless($request->user()->can('manage', $solarProject), 403);
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
