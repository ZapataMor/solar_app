<?php

namespace Tests\Unit;

use App\Models\CalculationResult;
use App\Models\MonthlyResult;
use App\Services\EnergyAnalysisService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class EnergyAnalysisServiceTest extends TestCase
{
    public function test_it_detects_high_coverage_surplus_and_high_savings(): void
    {
        $service = new EnergyAnalysisService();

        $analysis = $service->analyze(
            $this->calculationResult([
                'estimated_annual_generation_kwh' => 15000,
                'annual_consumption_kwh' => 12000,
                'coverage_percentage' => 125,
                'estimated_annual_savings_cop' => 8200000,
            ]),
            new Collection
        );

        $this->assertContains([
            'level' => 'success',
            'title' => 'Cobertura alta',
            'message' => 'El sistema solar cubre la totalidad del consumo anual estimado.',
        ], $analysis['insights']);
        $this->assertContains([
            'level' => 'success',
            'title' => 'Sobreproduccion solar',
            'message' => 'La generacion anual supera el consumo anual del proyecto.',
        ], $analysis['insights']);
        $this->assertContains([
            'level' => 'success',
            'title' => 'Excedentes energeticos',
            'message' => 'La simulacion proyecta energia excedente frente al consumo anual.',
        ], $analysis['insights']);
        $this->assertContains([
            'level' => 'success',
            'title' => 'Potencial alto de ahorro',
            'message' => 'El ahorro anual estimado es suficientemente alto para justificar una estrategia solar agresiva.',
        ], $analysis['insights']);
    }

    public function test_it_detects_low_coverage_and_grid_dependency(): void
    {
        $service = new EnergyAnalysisService();

        $analysis = $service->analyze(
            $this->calculationResult([
                'estimated_annual_generation_kwh' => 6800,
                'annual_consumption_kwh' => 12000,
                'coverage_percentage' => 56.7,
                'estimated_annual_savings_cop' => 2100000,
            ]),
            new Collection
        );

        $this->assertContains([
            'level' => 'warning',
            'title' => 'Baja cobertura energetica',
            'message' => 'La generacion solar cubre menos del 70% del consumo anual estimado.',
        ], $analysis['insights']);
        $this->assertContains([
            'level' => 'warning',
            'title' => 'Dependencia de red',
            'message' => 'La generacion anual es menor al consumo y el proyecto seguira dependiendo de la red.',
        ], $analysis['insights']);
        $this->assertSame(
            'La generacion estimada tendria una cobertura media del consumo anual.',
            app(EnergyAnalysisService::class)->coverageInterpretation(56.7)
        );
    }

    public function test_it_interprets_monthly_patterns_and_detects_best_and_worst_months(): void
    {
        $service = new EnergyAnalysisService();
        $monthlyResults = new Collection([
            $this->monthlyResult([
                'month_number' => 1,
                'month_name' => 'enero',
                'estimated_generation_kwh' => 1500,
                'estimated_consumption_kwh' => 1000,
                'coverage_percentage' => 150,
                'estimated_savings_cop' => 1200000,
            ]),
            $this->monthlyResult([
                'month_number' => 2,
                'month_name' => 'febrero',
                'estimated_generation_kwh' => 620,
                'estimated_consumption_kwh' => 1000,
                'coverage_percentage' => 62,
                'estimated_savings_cop' => 520000,
            ]),
        ]);

        $analysis = $service->analyze($this->calculationResult(), $monthlyResults);

        $this->assertContains([
            'level' => 'success',
            'title' => 'Meses con excedente',
            'message' => 'Se detectan excedentes energeticos en: Enero.',
        ], $analysis['monthlyInterpretations']);
        $this->assertContains([
            'level' => 'warning',
            'title' => 'Meses con baja cobertura',
            'message' => 'Los meses mas dependientes de la red son: Febrero.',
        ], $analysis['monthlyInterpretations']);
        $this->assertSame('enero', $analysis['monthlyHighlights']['highestGeneration']?->month_name);
        $this->assertSame('febrero', $analysis['monthlyHighlights']['lowestCoverage']?->month_name);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function calculationResult(array $attributes = []): CalculationResult
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function monthlyResult(array $attributes): MonthlyResult
    {
        $result = new MonthlyResult();
        $result->forceFill([
            'month_number' => 1,
            'month_name' => 'enero',
            'days_in_month' => 30,
            'average_daily_solar_radiation' => 5.1,
            'estimated_generation_kwh' => 1000,
            'estimated_consumption_kwh' => 1000,
            'coverage_percentage' => 100,
            'estimated_savings_cop' => 800000,
            ...$attributes,
        ]);

        return $result;
    }
}
