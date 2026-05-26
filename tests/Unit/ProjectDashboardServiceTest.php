<?php

namespace Tests\Unit;

use App\Models\CalculationResult;
use App\Models\MonthlyResult;
use App\Models\SolarProject;
use App\Models\WeatherStationReading;
use App\Services\AmbientWeatherAggregationService;
use App\Services\ClimateSourceFallbackService;
use App\Services\DashboardAiWidgetService;
use App\Services\DashboardTimeScaleService;
use App\Services\EnergyAnalysisService;
use App\Services\OpenAIRecommendationService;
use App\Services\ProjectDashboardService;
use App\Services\SolarRecommendationService;
use App\Services\WeatherAnalysisService;
use App\Services\WeatherStationAggregationService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ProjectDashboardServiceTest extends TestCase
{
    public function test_it_builds_a_canonical_dashboard_narrative_and_keeps_legacy_aliases(): void
    {
        $energyAnalysis = [
            'insights' => [
                ['level' => 'success', 'title' => 'Cobertura alta', 'message' => 'El sistema solar cubre la mayor parte del consumo.'],
                ['level' => 'success', 'title' => 'Potencial alto de ahorro', 'message' => 'El ahorro anual estimado es suficientemente alto para justificar una estrategia solar agresiva.'],
            ],
            'coverageInterpretation' => 'La generacion estimada tendria una cobertura alta del consumo anual.',
            'monthlyInterpretations' => [
                ['level' => 'warning', 'title' => 'Meses con baja cobertura', 'message' => 'Los meses mas dependientes de la red son: Febrero.'],
            ],
            'monthlyHighlights' => [
                'highestGeneration' => new MonthlyResult(['month_name' => 'enero']),
            ],
        ];
        $weatherAnalysis = [
            'current' => [
                ['type' => 'warning', 'message' => 'Alta radiacion detectada: el potencial solar y la exposicion UV estan elevados.'],
            ],
            'historical' => [
                ['type' => 'info', 'message' => 'Historico de radiacion favorable: el potencial solar se ha mantenido alto en el periodo analizado.'],
            ],
        ];
        $solarRecommendations = [
            'items' => [
                ['type' => 'recommendation', 'priority' => 'high', 'message' => 'Se recomienda operar equipos de alto consumo entre las 11 AM y 2 PM para aprovechar la mayor disponibilidad solar.'],
                ['type' => 'opportunity', 'priority' => 'medium', 'message' => 'Conviene aprovechar las horas de alta radiacion para desplazar cargas diurnas y reducir compra de energia a la red.'],
            ],
        ];
        $openAiRecommendation = [
            'enabled' => true,
            'source' => 'openai',
            'executive_summary' => 'Hoy se espera una produccion solar alta y una oportunidad clara de ahorro.',
            'daily_recommendation' => 'Hoy se recomienda desplazar cargas de alto consumo al mediodia para maximizar el ahorro energetico.',
            'energy_alerts' => ['La cobertura disminuye fuera de la franja solar.'],
            'error' => null,
        ];
        $weatherStats = [
            'total' => 24,
            'averageRadiation' => 620,
        ];

        $energyAnalysisService = $this->createMock(EnergyAnalysisService::class);
        $weatherAnalysisService = $this->createMock(WeatherAnalysisService::class);
        $solarRecommendationService = $this->createMock(SolarRecommendationService::class);
        $openAIRecommendationService = $this->createMock(OpenAIRecommendationService::class);
        $weatherStationAggregationService = $this->createMock(WeatherStationAggregationService::class);
        $ambientWeatherAggregationService = $this->createMock(AmbientWeatherAggregationService::class);
        $climateSourceFallbackService = $this->createMock(ClimateSourceFallbackService::class);
        $dashboardAiWidgetService = new DashboardAiWidgetService();
        $readings = collect();

        $calculationResult = $this->calculationResult();
        $monthlyResults = new Collection([
            new MonthlyResult([
                'month_name' => 'enero',
                'estimated_generation_kwh' => 1100,
                'estimated_consumption_kwh' => 1000,
                'estimated_savings_cop' => 902000,
                'coverage_percentage' => 110,
            ]),
        ]);

        $project = new SolarProject();
        $project->setRelation('monthlyResults', $monthlyResults);
        $project->setRelation('calculationResult', $calculationResult);

        $energyAnalysisService->expects($this->once())
            ->method('analyze')
            ->with($calculationResult, $monthlyResults)
            ->willReturn($energyAnalysis);

        $weatherStationAggregationService->expects($this->once())
            ->method('readingsForProject')
            ->with($project)
            ->willReturn($readings);

        $weatherAnalysisService->expects($this->once())
            ->method('analyzeReadings')
            ->with($readings)
            ->willReturn($weatherAnalysis);

        $weatherStationAggregationService->expects($this->once())
            ->method('stats')
            ->with($readings)
            ->willReturn($weatherStats);

        $solarRecommendationService->expects($this->once())
            ->method('recommend')
            ->with($weatherAnalysis, $energyAnalysis, $calculationResult, $weatherStats)
            ->willReturn($solarRecommendations);

        $openAIRecommendationService->expects($this->once())
            ->method('generate')
            ->with(
                $weatherAnalysis,
                $energyAnalysis,
                $solarRecommendations,
                $calculationResult,
                $this->callback(fn (array $stats) => ($stats['total'] ?? null) === 24),
                null,
                $this->isType('array')
            )
            ->willReturn($openAiRecommendation);

        $weatherStationAggregationService->method('dailyRows')
            ->willReturn(collect());

        $ambientWeatherAggregationService->expects($this->once())
            ->method('readingsForProject')
            ->with($project)
            ->willReturn(collect());

        $ambientWeatherAggregationService->expects($this->once())
            ->method('stats')
            ->with(collect())
            ->willReturn(['total' => 0]);

        $ambientWeatherAggregationService->expects($this->once())
            ->method('chartData')
            ->with(collect())
            ->willReturn(['labels' => [], 'radiation' => []]);

        $climateSourceFallbackService->expects($this->once())
            ->method('resolveActiveSourceDescriptor')
            ->willReturn(['source' => 'nasa_power', 'label' => 'NASA POWER']);

        $climateSourceFallbackService->expects($this->once())
            ->method('selectDailyClimateRows')
            ->willReturn(['rows' => collect(), 'source' => 'nasa_power']);

        $weatherStationAggregationService->expects($this->once())
            ->method('chartData')
            ->with($readings)
            ->willReturn(['labels' => [], 'radiation' => []]);

        $service = new ProjectDashboardService(
            $dashboardAiWidgetService,
            new DashboardTimeScaleService(),
            $energyAnalysisService,
            $openAIRecommendationService,
            $solarRecommendationService,
            $weatherAnalysisService,
            $weatherStationAggregationService,
            $ambientWeatherAggregationService,
            $climateSourceFallbackService,
        );

        $result = $service->build($project, true);

        $this->assertSame(
            'La generacion estimada tendria una cobertura alta del consumo anual.',
            $result['dashboard']['state']['summary']
        );
        $this->assertSame(
            'El ahorro anual estimado es suficientemente alto para justificar una estrategia solar agresiva.',
            $result['dashboard']['insights']['energy']['summary']
        );
        $this->assertSame(
            'Alta radiacion detectada: el potencial solar y la exposicion UV estan elevados.',
            $result['dashboard']['insights']['weather']['currentSummary']
        );
        $this->assertSame(
            'Hoy se recomienda desplazar cargas de alto consumo al mediodia para maximizar el ahorro energetico.',
            $result['dashboard']['recommendations']['summary']
        );
        $this->assertSame(
            'Hoy se espera una produccion solar alta y una oportunidad clara de ahorro.',
            $result['dashboard']['executiveSummary']['text']
        );
        $this->assertSame(
            $result['dashboard']['widgets'],
            $result['aiWidgets']
        );
        $this->assertSame(
            $result['dashboard']['recommendations']['groups']['actions'],
            $result['solarRecommendations']['recommendations']
        );
        $this->assertSame(
            $result['dashboard']['insights']['weather']['historical'],
            $result['weatherAnalysis']['historical']
        );
    }

    public function test_future_predictions_use_recent_week_and_month_records(): void
    {
        $service = new ProjectDashboardService(
            new DashboardAiWidgetService(),
            new DashboardTimeScaleService(),
            $this->createMock(EnergyAnalysisService::class),
            $this->createMock(OpenAIRecommendationService::class),
            $this->createMock(SolarRecommendationService::class),
            $this->createMock(WeatherAnalysisService::class),
            $this->createMock(WeatherStationAggregationService::class),
            $this->createMock(AmbientWeatherAggregationService::class),
            $this->createMock(ClimateSourceFallbackService::class),
        );

        $readings = collect();
        foreach (range(8, 13) as $daysAgo) {
            foreach ([9, 10, 11, 12, 14] as $hour) {
                $readings->push($this->weatherReading($daysAgo, $hour, 28.0, $hour >= 10 && $hour <= 12 ? 650 : 300));
            }
        }
        foreach (range(1, 6) as $daysAgo) {
            foreach ([9, 10, 11, 12, 14] as $hour) {
                $readings->push($this->weatherReading($daysAgo, $hour, 30.0, $hour >= 10 && $hour <= 12 ? 820 : 360));
            }
        }

        $method = new \ReflectionMethod(ProjectDashboardService::class, 'buildFuturePredictions');
        $method->setAccessible(true);

        $result = $method->invoke(
            $service,
            collect(),
            $readings,
            collect(),
            ClimateSourceFallbackService::SOURCE_LOCAL,
            new SolarProject()
        );

        $this->assertSame(32.0, $result['temperature']['projected_next_week_c']);
        $this->assertSame(2.0, $result['temperature']['delta_c']);
        $this->assertSame(10, $result['radiation_window']['start_hour']);
        $this->assertSame(12, $result['radiation_window']['end_hour']);
        $this->assertSame('Centro meteorologico', $result['data_window']['source']);
        $this->assertStringContainsString('ultimos 7 dias vs semana previa', $result['temperature']['message']);
    }

    public function test_future_predictions_follow_selected_analysis_source(): void
    {
        $service = new ProjectDashboardService(
            new DashboardAiWidgetService(),
            new DashboardTimeScaleService(),
            $this->createMock(EnergyAnalysisService::class),
            $this->createMock(OpenAIRecommendationService::class),
            $this->createMock(SolarRecommendationService::class),
            $this->createMock(WeatherAnalysisService::class),
            $this->createMock(WeatherStationAggregationService::class),
            $this->createMock(AmbientWeatherAggregationService::class),
            $this->createMock(ClimateSourceFallbackService::class),
        );

        $localReadings = collect([
            $this->weatherReading(1, 14, 39.0, 900),
            $this->weatherReading(2, 14, 39.0, 900),
        ]);

        $nasaRows = collect();
        foreach (range(1, 8) as $daysAgo) {
            $nasaRows->push((object) [
                'date_time' => now()->subDays($daysAgo)->setTime(12, 0),
                't2m' => 29.0,
                'allsky_sfc_sw_dwn' => 520.0,
            ]);
        }

        $method = new \ReflectionMethod(ProjectDashboardService::class, 'buildFuturePredictions');
        $method->setAccessible(true);

        $result = $method->invoke(
            $service,
            $nasaRows,
            $localReadings,
            collect(),
            ClimateSourceFallbackService::SOURCE_NASA,
            new SolarProject()
        );

        $this->assertSame('NASA POWER', $result['data_window']['source']);
        $this->assertSame(8, $result['data_window']['sample_count']);
        $this->assertSame(29.0, $result['temperature']['last_7_avg_c']);
    }

    private function calculationResult(): CalculationResult
    {
        $result = new CalculationResult();
        $result->forceFill([
            'usable_area_m2' => 102,
            'number_of_panels' => 40,
            'installed_capacity_kwp' => 22,
            'estimated_daily_generation_kwh' => 28,
            'estimated_monthly_generation_kwh' => 850,
            'estimated_annual_generation_kwh' => 13200,
            'annual_consumption_kwh' => 12000,
            'coverage_percentage' => 110,
            'estimated_annual_savings_cop' => 8364000,
        ]);

        return $result;
    }

    private function weatherReading(int $daysAgo, int $hour, float $temperature, float $radiation): WeatherStationReading
    {
        $reading = new WeatherStationReading();
        $reading->forceFill([
            'measured_at' => now()->subDays($daysAgo)->setTime($hour, 0),
            'temperature' => $temperature,
            'solar_radiation' => $radiation,
        ]);

        return $reading;
    }
}
