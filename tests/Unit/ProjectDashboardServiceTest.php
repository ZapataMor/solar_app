<?php

namespace Tests\Unit;

use App\Models\CalculationResult;
use App\Models\MonthlyResult;
use App\Models\SolarProject;
use App\Services\DashboardAiWidgetService;
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
            ->with($weatherAnalysis, $energyAnalysis, $solarRecommendations, $calculationResult, $weatherStats)
            ->willReturn($openAiRecommendation);

        $weatherStationAggregationService->expects($this->once())
            ->method('chartData')
            ->with($readings)
            ->willReturn(['labels' => [], 'radiation' => []]);

        $service = new ProjectDashboardService(
            $dashboardAiWidgetService,
            $energyAnalysisService,
            $openAIRecommendationService,
            $solarRecommendationService,
            $weatherAnalysisService,
            $weatherStationAggregationService,
        );

        $result = $service->build($project);

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
}
