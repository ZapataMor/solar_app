<?php

namespace Tests\Unit;

use App\Models\CalculationResult;
use App\Services\SolarRecommendationService;
use Tests\TestCase;

class SolarRecommendationServiceTest extends TestCase
{
    public function test_it_generates_recommendations_risks_and_opportunities_from_weather_and_energy_analysis(): void
    {
        $service = new SolarRecommendationService();

        $recommendations = $service->recommend(
            [
                'current' => [
                    ['type' => 'warning', 'message' => 'Alta radiacion detectada: el potencial solar y la exposicion UV estan elevados.'],
                    ['type' => 'warning', 'message' => 'Calor extremo detectado: la temperatura actual supera los 35 C.'],
                ],
                'historical' => [
                    ['type' => 'info', 'message' => 'Historico de radiacion favorable: el potencial solar se ha mantenido alto en el periodo analizado.'],
                ],
            ],
            [
                'insights' => [
                    ['level' => 'warning', 'title' => 'Baja cobertura energetica', 'message' => 'La generacion solar cubre menos del 70% del consumo anual estimado.'],
                    ['level' => 'warning', 'title' => 'Dependencia de red', 'message' => 'La generacion anual es menor al consumo y el proyecto seguira dependiendo de la red.'],
                ],
                'monthlyInterpretations' => [
                    ['level' => 'warning', 'title' => 'Meses con baja cobertura', 'message' => 'Los meses mas dependientes de la red son: Febrero.'],
                ],
            ],
            $this->calculationResult([
                'coverage_percentage' => 58,
                'estimated_annual_generation_kwh' => 7000,
                'annual_consumption_kwh' => 12000,
                'estimated_annual_savings_cop' => 3200000,
            ]),
            [
                'averageRadiation' => 620,
            ],
        );

        $this->assertContains([
            'type' => 'recommendation',
            'priority' => 'high',
            'message' => 'Se recomienda operar equipos de alto consumo entre las 11 AM y 2 PM para aprovechar la mayor disponibilidad solar.',
        ], $recommendations['items']);
        $this->assertContains([
            'type' => 'risk',
            'priority' => 'high',
            'message' => 'Existe riesgo de dependencia de red: la cobertura solar proyectada es insuficiente para sostener la mayor parte del consumo.',
        ], $recommendations['items']);
        $this->assertContains([
            'type' => 'alert',
            'priority' => 'medium',
            'message' => 'El calor extremo puede elevar la demanda de climatizacion; conviene preenfriar espacios durante la franja de mayor generacion solar.',
        ], $recommendations['items']);
        $this->assertContains([
            'type' => 'opportunity',
            'priority' => 'medium',
            'message' => 'Conviene aprovechar las horas de alta radiacion para desplazar cargas diurnas y reducir compra de energia a la red.',
        ], $recommendations['items']);
    }

    public function test_it_generates_surplus_opportunities_when_coverage_is_above_hundred(): void
    {
        $service = new SolarRecommendationService();

        $recommendations = $service->recommend(
            [
                'current' => [],
                'historical' => [],
            ],
            [
                'insights' => [
                    ['level' => 'success', 'title' => 'Sobreproduccion solar', 'message' => 'La generacion anual supera el consumo anual del proyecto.'],
                    ['level' => 'success', 'title' => 'Potencial alto de ahorro', 'message' => 'El ahorro anual estimado es suficientemente alto para justificar una estrategia solar agresiva.'],
                ],
                'monthlyInterpretations' => [],
            ],
            $this->calculationResult([
                'coverage_percentage' => 125,
                'estimated_annual_generation_kwh' => 15000,
                'annual_consumption_kwh' => 12000,
                'estimated_annual_savings_cop' => 8100000,
            ]),
            [],
        );

        $this->assertContains([
            'type' => 'opportunity',
            'priority' => 'high',
            'message' => 'La sobreproduccion solar abre oportunidad para concentrar procesos diurnos, almacenamiento o nuevas cargas en horario solar.',
        ], $recommendations['items']);
        $this->assertContains([
            'type' => 'opportunity',
            'priority' => 'medium',
            'message' => 'El potencial de ahorro es alto: vale la pena consolidar una estrategia operativa enfocada en autoconsumo solar.',
        ], $recommendations['items']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function calculationResult(array $attributes): CalculationResult
    {
        $result = new CalculationResult();
        $result->forceFill([
            'estimated_daily_generation_kwh' => 28,
            'estimated_monthly_generation_kwh' => 850,
            'estimated_annual_generation_kwh' => 12000,
            'annual_consumption_kwh' => 12000,
            'coverage_percentage' => 100,
            'estimated_annual_savings_cop' => 4500000,
            ...$attributes,
        ]);

        return $result;
    }
}
