<?php

namespace App\Services;

use App\Models\CalculationResult;
use App\Models\SolarProject;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ProjectDashboardService
{
    public function __construct(
        private readonly DashboardAiWidgetService $dashboardAiWidgetService,
        private readonly DashboardTimeScaleService $dashboardTimeScaleService,
        private readonly EnergyAnalysisService $energyAnalysisService,
        private readonly OpenAIRecommendationService $openAIRecommendationService,
        private readonly SolarRecommendationService $solarRecommendationService,
        private readonly WeatherAnalysisService $weatherAnalysisService,
        private readonly WeatherStationAggregationService $weatherStationAggregationService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(SolarProject $solarProject, bool $generateAiRecommendations = false): array
    {
        $monthlyResults = $solarProject->monthlyResults;
        $calculationResult = $solarProject->calculationResult;
        $energyAnalysis = $this->energyAnalysisService->analyze($calculationResult, $monthlyResults);
        $weatherStationReadings = $this->weatherStationAggregationService->readingsForProject($solarProject);
        $weatherAnalysis = $this->weatherAnalysisService->analyzeReadings($weatherStationReadings);
        $weatherStationStats = $this->weatherStationAggregationService->stats($weatherStationReadings);
        $projectWeatherData = $solarProject->relationLoaded('weatherData')
            ? collect($solarProject->getRelation('weatherData'))
            : ($solarProject->start_date && $solarProject->end_date ? $solarProject->weatherData()->get() : collect());
        $nasaEstimatedRadiationCount = $projectWeatherData
            ->filter(fn ($row) => ($row->radiation_source ?? 'nasa_real') !== 'nasa_real')
            ->count();
        $nasaTotalRadiationRows = $projectWeatherData
            ->filter(fn ($row) => $row->allsky_sfc_sw_dwn !== null)
            ->count();
        $nasaDataQuality = [
            'totalRows' => $nasaTotalRadiationRows,
            'estimatedRows' => $nasaEstimatedRadiationCount,
            'estimatedRatio' => $nasaTotalRadiationRows > 0
                ? $nasaEstimatedRadiationCount / $nasaTotalRadiationRows
                : 0.0,
        ];
        $weatherAndNasaStats = [
            ...$weatherStationStats,
            'nasaDataQuality' => $nasaDataQuality,
        ];
        $dailyClimateRows = $projectWeatherData->isNotEmpty()
            ? $projectWeatherData
            : ($weatherStationReadings->isNotEmpty()
                ? $this->weatherStationAggregationService->dailyRows($weatherStationReadings)
                : collect());
        $solarRecommendations = $this->solarRecommendationService->recommend(
            $weatherAnalysis,
            $energyAnalysis,
            $calculationResult,
            $weatherStationStats,
        );
        $openAIRecommendation = $generateAiRecommendations
            ? $this->openAIRecommendationService->generate(
                $weatherAnalysis,
                $energyAnalysis,
                $solarRecommendations,
                $calculationResult,
                $weatherAndNasaStats,
            )
            : [
                'enabled' => false,
                'source' => 'manual_trigger_required',
                'executive_summary' => null,
                'daily_recommendation' => null,
                'energy_alerts' => [],
                'error' => 'Pulsa "Generar recomendaciones con IA" para solicitar el reporte inteligente.',
            ];

        $dashboard = $this->buildDashboardNarrative(
            $energyAnalysis,
            $weatherAnalysis,
            $solarRecommendations,
            $openAIRecommendation,
            $calculationResult,
            $weatherStationStats,
            $nasaDataQuality,
        );
        $dashboard['widgets'] = $this->dashboardAiWidgetService->build($dashboard);
        $dashboard['futurePredictions'] = $this->buildFuturePredictions(
            $projectWeatherData,
            $weatherStationReadings,
            $solarProject
        );
        $timeScales = $this->dashboardTimeScaleService->build(
            $solarProject,
            $calculationResult,
            $monthlyResults,
            $dailyClimateRows,
            $weatherAnalysis,
            $energyAnalysis,
            $solarRecommendations,
        );

        return [
            'dashboard' => $dashboard,
            'aiWidgets' => $dashboard['widgets'],
            'chartData' => $timeScales['chartPayloads']['monthly'] ?? [
                'labels' => [],
                'generation' => [],
                'consumption' => [],
                'savings' => [],
                'coverage' => [],
            ],
            'coverageInterpretation' => $dashboard['state']['summary'],
            'energyAnalysis' => [
                'insights' => $dashboard['insights']['energy']['items'],
                'coverageInterpretation' => $dashboard['state']['summary'],
                'monthlyInterpretations' => $dashboard['insights']['energy']['monthly'],
                'monthlyHighlights' => $dashboard['insights']['energy']['highlights'],
            ],
            'monthlyHighlights' => $dashboard['insights']['energy']['highlights'],
            'monthlyTotals' => [
                'generation' => $monthlyResults->sum('estimated_generation_kwh'),
                'consumption' => $monthlyResults->sum('estimated_consumption_kwh'),
                'savings' => $monthlyResults->sum('estimated_savings_cop'),
            ],
            'weatherStationStats' => $weatherStationStats,
            'nasaDataQuality' => $nasaDataQuality,
            'recentWeatherStationReadings' => $weatherStationReadings
                ->sortByDesc('measured_at')
                ->take(60)
                ->values(),
            'openAIRecommendation' => $dashboard['executiveSummary']['ai'],
            'weatherAnalysis' => [
                'current' => $dashboard['insights']['weather']['current'],
                'historical' => $dashboard['insights']['weather']['historical'],
            ],
            'solarRecommendations' => [
                'items' => $dashboard['recommendations']['items'],
                'recommendations' => $dashboard['recommendations']['groups']['actions'],
                'alerts' => $dashboard['recommendations']['groups']['alerts'],
                'risks' => $dashboard['recommendations']['groups']['risks'],
                'opportunities' => $dashboard['recommendations']['groups']['opportunities'],
            ],
            'weatherStationChartData' => $this->weatherStationAggregationService->chartData($weatherStationReadings),
            'timeScales' => $timeScales,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $projectWeatherData
     * @param  Collection<int, mixed>  $weatherStationReadings
     * @return array<string, mixed>
     */
    private function buildFuturePredictions(Collection $projectWeatherData, Collection $weatherStationReadings, SolarProject $solarProject): array
    {
        $nasaTempSeries = $projectWeatherData
            ->filter(fn ($row) => isset($row->date_time) && $row->t2m !== null)
            ->sortBy('date_time')
            ->values();

        $latestTemp = $nasaTempSeries->last();
        $last7Start = Carbon::now()->subDays(7);
        $prev7Start = Carbon::now()->subDays(14);

        $last7Avg = $nasaTempSeries
            ->filter(fn ($row) => Carbon::parse($row->date_time)->greaterThanOrEqualTo($last7Start))
            ->avg(fn ($row) => (float) $row->t2m);
        $prev7Avg = $nasaTempSeries
            ->filter(fn ($row) => Carbon::parse($row->date_time)->betweenIncluded($prev7Start, $last7Start))
            ->avg(fn ($row) => (float) $row->t2m);

        $tempDelta = ($last7Avg !== null && $prev7Avg !== null) ? ($last7Avg - $prev7Avg) : null;
        $nextWeekTemp = ($latestTemp && $tempDelta !== null) ? ((float) $latestTemp->t2m + $tempDelta) : null;

        $nasaRadiationRows = $projectWeatherData
            ->filter(fn ($row) => isset($row->date_time) && $row->allsky_sfc_sw_dwn !== null)
            ->filter(fn ($row) => Carbon::parse($row->date_time)->gte(Carbon::now()->subDays(30)));

        $hourlyNasa = $nasaRadiationRows
            ->groupBy(fn ($row) => Carbon::parse($row->date_time)->format('H'))
            ->map(fn ($rows, $hour) => [
                'hour' => (int) $hour,
                'avg_radiation' => collect($rows)->avg(fn ($row) => (float) $row->allsky_sfc_sw_dwn),
            ])
            ->filter(fn (array $row) => $row['hour'] >= 6 && $row['hour'] <= 18)
            ->sortByDesc('avg_radiation')
            ->values();

        $topHours = $hourlyNasa->take(3)->sortBy('hour')->values();
        $windowStart = $topHours->first()['hour'] ?? null;
        $windowEnd = $topHours->last()['hour'] ?? null;

        $recommendedLoadShift = ($windowStart !== null && $windowEnd !== null)
            ? "Se proyecta mejor captacion solar entre {$windowStart}:00 y {$windowEnd}:59. Conviene mover cargas altas (bombeo, climatizacion, procesos pesados) a esa ventana."
            : 'No hay suficiente historial horario para definir una ventana solar optima.';

        $tempMessage = match (true) {
            $nextWeekTemp === null => 'No hay suficiente informacion para proyectar la temperatura de la proxima semana.',
            $tempDelta !== null && $tempDelta >= 1.5 => 'Se proyecta incremento termico para la proxima semana. Recomendado reforzar ventilacion y anticipar leve perdida de eficiencia en paneles por calor.',
            $tempDelta !== null && $tempDelta <= -1.5 => 'Se proyecta descenso termico para la proxima semana. Puede mejorar ligeramente la eficiencia del sistema.',
            default => 'Se proyecta estabilidad termica para la proxima semana, sin cambios fuertes esperados.',
        };

        return [
            'temperature' => [
                'projected_next_week_c' => $nextWeekTemp !== null ? round((float) $nextWeekTemp, 2) : null,
                'delta_c' => $tempDelta !== null ? round((float) $tempDelta, 2) : null,
                'message' => $tempMessage,
            ],
            'radiation_window' => [
                'start_hour' => $windowStart,
                'end_hour' => $windowEnd,
                'message' => $recommendedLoadShift,
            ],
            'operational_recommendations' => [
                $recommendedLoadShift,
                'Programar recarga de baterias y tareas flexibles dentro de la ventana solar dominante para reducir compra de red.',
                'Monitorear tendencia semanal de temperatura para ajustar estrategia de consumo en horas de mayor estres termico.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $energyAnalysis
     * @param  array<string, mixed>  $weatherAnalysis
     * @param  array<string, mixed>  $solarRecommendations
     * @param  array<string, mixed>  $openAIRecommendation
     * @param  array<string, mixed>  $weatherStationStats
     * @param  array<string, mixed>  $nasaDataQuality
     * @return array<string, mixed>
     */
    private function buildDashboardNarrative(
        array $energyAnalysis,
        array $weatherAnalysis,
        array $solarRecommendations,
        array $openAIRecommendation,
        ?CalculationResult $calculationResult,
        array $weatherStationStats,
        array $nasaDataQuality,
    ): array {
        $coverage = $calculationResult ? (float) $calculationResult->coverage_percentage : null;
        $annualSavings = $calculationResult ? (float) $calculationResult->estimated_annual_savings_cop : null;
        $annualBalance = $calculationResult
            ? (float) $calculationResult->estimated_annual_generation_kwh - (float) $calculationResult->annual_consumption_kwh
            : null;

        $energyItems = $this->normalizeEnergyInsights($energyAnalysis['insights'] ?? []);
        $monthlyEnergyItems = $this->normalizeEnergyInsights($energyAnalysis['monthlyInterpretations'] ?? []);
        $weatherCurrent = $this->normalizeWeatherInsights($weatherAnalysis['current'] ?? [], 'current');
        $weatherHistorical = $this->normalizeWeatherInsights($weatherAnalysis['historical'] ?? [], 'historical');
        $recommendationItems = $this->normalizeRecommendationItems($solarRecommendations['items'] ?? []);

        $recommendationGroups = [
            'actions' => array_values(array_filter($recommendationItems, fn (array $item) => $item['type'] === 'recommendation')),
            'alerts' => array_values(array_filter($recommendationItems, fn (array $item) => $item['type'] === 'alert')),
            'risks' => array_values(array_filter($recommendationItems, fn (array $item) => $item['type'] === 'risk')),
            'opportunities' => array_values(array_filter($recommendationItems, fn (array $item) => $item['type'] === 'opportunity')),
        ];

        $stateSummary = $energyAnalysis['coverageInterpretation']
            ?? 'Aun no hay suficientes datos para determinar el estado energetico general.';

        $executiveSummaryText = $this->stringValue($openAIRecommendation['executive_summary'] ?? null)
            ?? $this->fallbackExecutiveSummary(
                $stateSummary,
                $recommendationItems,
                $weatherCurrent,
                $annualSavings,
            );

        return [
            'state' => [
                'level' => $this->coverageLevel($coverage),
                'title' => $this->stateTitle($coverage),
                'summary' => $stateSummary,
                'coveragePercentage' => $coverage,
                'annualSavingsCop' => $annualSavings,
                'annualBalanceKwh' => $annualBalance,
            ],
            'insights' => [
                'energy' => [
                    'summary' => $this->firstDistinctMessage(
                        $energyItems,
                        $stateSummary,
                        'Aun no hay conclusiones energeticas automaticas.'
                    ),
                    'items' => $energyItems,
                    'monthly' => $monthlyEnergyItems,
                    'highlights' => $energyAnalysis['monthlyHighlights'] ?? [],
                ],
                'weather' => [
                    'currentSummary' => $weatherCurrent[0]['message'] ?? 'Sin alertas actuales relevantes.',
                    'historicalSummary' => $weatherHistorical[0]['message'] ?? 'Sin tendencias historicas destacadas.',
                    'current' => $weatherCurrent,
                    'historical' => $weatherHistorical,
                    'readingCount' => (int) ($weatherStationStats['total'] ?? 0),
                ],
            ],
            'recommendations' => [
                'summary' => $this->stringValue($openAIRecommendation['daily_recommendation'] ?? null)
                    ?? $recommendationGroups['actions'][0]['message']
                    ?? $recommendationItems[0]['message']
                    ?? 'Sin recomendaciones automaticas disponibles.',
                'items' => $recommendationItems,
                'groups' => $recommendationGroups,
            ],
            'executiveSummary' => [
                'text' => $executiveSummaryText,
                'source' => $this->stringValue($openAIRecommendation['executive_summary'] ?? null)
                    ? ($openAIRecommendation['source'] ?? 'openai')
                    : 'rule_based',
                'dailyRecommendation' => $this->stringValue($openAIRecommendation['daily_recommendation'] ?? null),
                'alerts' => array_values(array_filter(
                    $openAIRecommendation['energy_alerts'] ?? [],
                    fn ($item) => is_string($item) && filled(trim($item))
                )),
                'dataQuality' => $nasaDataQuality,
                'error' => $openAIRecommendation['error'] ?? null,
                'enabled' => (bool) ($openAIRecommendation['enabled'] ?? false),
                'ai' => $openAIRecommendation,
            ],
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array{level: string, title: string, message: string}>
     */
    private function normalizeEnergyInsights(array $items): array
    {
        return array_values(array_filter(array_map(function ($item) {
            if (! is_array($item) || ! filled($item['message'] ?? null)) {
                return null;
            }

            return [
                'level' => (string) ($item['level'] ?? 'info'),
                'title' => (string) ($item['title'] ?? 'Insight energetico'),
                'message' => (string) $item['message'],
            ];
        }, $items)));
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array{type: string, title: string, message: string}>
     */
    private function normalizeWeatherInsights(array $items, string $scope): array
    {
        return array_values(array_filter(array_map(function ($item) use ($scope) {
            if (! is_array($item) || ! filled($item['message'] ?? null)) {
                return null;
            }

            return [
                'type' => (string) ($item['type'] ?? 'info'),
                'title' => $scope === 'current' ? 'Estado actual' : 'Comportamiento historico',
                'message' => (string) $item['message'],
            ];
        }, $items)));
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array{type: string, priority: string, message: string}>
     */
    private function normalizeRecommendationItems(array $items): array
    {
        return array_values(array_filter(array_map(function ($item) {
            if (! is_array($item) || ! filled($item['message'] ?? null)) {
                return null;
            }

            return [
                'type' => (string) ($item['type'] ?? 'recommendation'),
                'priority' => $this->normalizePriority($item['priority'] ?? null),
                'message' => (string) $item['message'],
            ];
        }, $items)));
    }

    /**
     * @param  array<int, array{level?: string, title?: string, message: string}>  $items
     */
    private function firstDistinctMessage(array $items, ?string $ignoredMessage, string $fallback): string
    {
        foreach ($items as $item) {
            $title = strtolower((string) ($item['title'] ?? ''));
            $message = (string) ($item['message'] ?? '');

            if ($message !== $ignoredMessage && ! str_contains($title, 'cobertura')) {
                return $message;
            }
        }

        foreach ($items as $item) {
            if (($item['message'] ?? null) !== $ignoredMessage) {
                return (string) $item['message'];
            }
        }

        return $ignoredMessage ?: $fallback;
    }

    /**
     * @param  array<int, array{type: string, priority: string, message: string}>  $recommendationItems
     * @param  array<int, array{type: string, title: string, message: string}>  $weatherCurrent
     */
    private function fallbackExecutiveSummary(
        string $stateSummary,
        array $recommendationItems,
        array $weatherCurrent,
        ?float $annualSavings,
    ): string {
        $supportingMessage = $recommendationItems[0]['message']
            ?? $weatherCurrent[0]['message']
            ?? null;
        $savingsText = $annualSavings !== null
            ? ' El ahorro anual estimado es de $ '.number_format($annualSavings, 0, ',', '.').' COP.'
            : '';

        return trim($stateSummary.$savingsText.' '.($supportingMessage ?? ''));
    }

    private function coverageLevel(?float $coverage): string
    {
        if ($coverage === null) {
            return 'info';
        }

        if ($coverage >= 100) {
            return 'success';
        }

        if ($coverage >= 70) {
            return 'warning';
        }

        return 'danger';
    }

    private function stateTitle(?float $coverage): string
    {
        if ($coverage === null) {
            return 'Estado pendiente';
        }

        if ($coverage >= 100) {
            return 'Cobertura favorable';
        }

        if ($coverage >= 70) {
            return 'Cobertura funcional';
        }

        return 'Cobertura limitada';
    }

    private function normalizePriority(mixed $priority): string
    {
        return match (strtolower(trim((string) $priority))) {
            'high', 'alta' => 'alta',
            'low', 'baja' => 'baja',
            default => 'media',
        };
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && filled(trim($value)) ? trim($value) : null;
    }
}
