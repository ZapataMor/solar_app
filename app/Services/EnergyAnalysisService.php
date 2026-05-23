<?php

namespace App\Services;

use App\Models\CalculationResult;
use App\Models\MonthlyResult;
use Illuminate\Support\Collection;

class EnergyAnalysisService
{
    /**
     * @param  Collection<int, MonthlyResult>  $monthlyResults
     * @return array{
     *     insights: array<int, array{level: string, title: string, message: string}>,
     *     coverageInterpretation: string|null,
     *     monthlyInterpretations: array<int, array{level: string, title: string, message: string}>,
     *     monthlyHighlights: array<string, MonthlyResult|null>
     * }
     */
    public function analyze(?CalculationResult $calculationResult, Collection $monthlyResults): array
    {
        /** @var Collection<int, MonthlyResult> $orderedMonthlyResults */
        $orderedMonthlyResults = $monthlyResults
            ->filter(fn ($result) => $result instanceof MonthlyResult)
            ->sortBy('month_number')
            ->values();

        return [
            'insights' => $calculationResult ? $this->overallInsights($calculationResult) : [],
            'coverageInterpretation' => $calculationResult
                ? $this->coverageInterpretation((float) $calculationResult->coverage_percentage)
                : null,
            'monthlyInterpretations' => $this->monthlyInterpretations($orderedMonthlyResults),
            'monthlyHighlights' => $this->monthlyHighlights($orderedMonthlyResults),
        ];
    }

    /**
     * @return array<int, array{level: string, title: string, message: string}>
     */
    public function overallInsights(CalculationResult $calculationResult): array
    {
        $coverage = (float) $calculationResult->coverage_percentage;
        $annualGeneration = (float) $calculationResult->estimated_annual_generation_kwh;
        $annualConsumption = (float) $calculationResult->annual_consumption_kwh;
        $annualSavings = (float) $calculationResult->estimated_annual_savings_cop;
        $energyDelta = $annualGeneration - $annualConsumption;

        $insights = [];

        if ($coverage > 100) {
            $insights[] = $this->insight(
                'success',
                'Cobertura alta',
                'El sistema solar cubre la totalidad del consumo anual estimado.'
            );
            $insights[] = $this->insight(
                'success',
                'Sobreproduccion solar',
                'La generacion anual supera el consumo anual del proyecto.'
            );
        } elseif ($coverage >= 70) {
            $insights[] = $this->insight(
                'success',
                'Cobertura alta',
                'El sistema solar cubre la mayor parte del consumo.'
            );
        } else {
            $insights[] = $this->insight(
                'warning',
                'Baja cobertura energetica',
                'La generacion solar cubre menos del 70% del consumo anual estimado.'
            );
        }

        if ($annualGeneration < $annualConsumption) {
            $insights[] = $this->insight(
                'warning',
                'Dependencia de red',
                'La generacion anual es menor al consumo y el proyecto seguira dependiendo de la red.'
            );
        }

        if ($energyDelta > 0) {
            $insights[] = $this->insight(
                'success',
                'Excedentes energeticos',
                'La simulacion proyecta energia excedente frente al consumo anual.'
            );
        }

        if ($annualSavings >= 5000000) {
            $insights[] = $this->insight(
                'success',
                'Potencial alto de ahorro',
                'El ahorro anual estimado es suficientemente alto para justificar una estrategia solar agresiva.'
            );
        }

        return collect($insights)
            ->unique(fn (array $item) => $item['title'].'|'.$item['message'])
            ->values()
            ->all();
    }

    public function coverageInterpretation(float $coveragePercentage): string
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

    /**
     * @param  Collection<int, MonthlyResult>  $monthlyResults
     * @return array<int, array{level: string, title: string, message: string}>
     */
    public function monthlyInterpretations(Collection $monthlyResults): array
    {
        if ($monthlyResults->isEmpty()) {
            return [];
        }

        $interpretations = [];
        $criticalMonths = $monthlyResults->filter(fn (MonthlyResult $result) => (float) $result->coverage_percentage < 70)->values();
        $surplusMonths = $monthlyResults->filter(fn (MonthlyResult $result) => (float) $result->coverage_percentage > 100)->values();
        $bestGenerationMonth = $monthlyResults->sortByDesc(fn (MonthlyResult $result) => (float) $result->estimated_generation_kwh)->first();
        $worstCoverageMonth = $monthlyResults->sortBy(fn (MonthlyResult $result) => (float) $result->coverage_percentage)->first();

        if ($surplusMonths->isNotEmpty()) {
            $monthNames = $surplusMonths->pluck('month_name')->map(fn (string $month) => ucfirst($month))->implode(', ');
            $interpretations[] = $this->insight(
                'success',
                'Meses con excedente',
                "Se detectan excedentes energeticos en: {$monthNames}."
            );
        }

        if ($criticalMonths->isNotEmpty()) {
            $monthNames = $criticalMonths->pluck('month_name')->map(fn (string $month) => ucfirst($month))->implode(', ');
            $interpretations[] = $this->insight(
                'warning',
                'Meses con baja cobertura',
                "Los meses mas dependientes de la red son: {$monthNames}."
            );
        }

        if ($bestGenerationMonth instanceof MonthlyResult) {
            $interpretations[] = $this->insight(
                'info',
                'Mejor mes de generacion',
                sprintf(
                    '%s presenta la mayor generacion estimada con %s.',
                    ucfirst($bestGenerationMonth->month_name),
                    number_format((float) $bestGenerationMonth->estimated_generation_kwh, 2, ',', '.').' kWh'
                )
            );
        }

        if ($worstCoverageMonth instanceof MonthlyResult) {
            $interpretations[] = $this->insight(
                'warning',
                'Mes mas critico',
                sprintf(
                    '%s tiene la menor cobertura energetica con %s.',
                    ucfirst($worstCoverageMonth->month_name),
                    number_format((float) $worstCoverageMonth->coverage_percentage, 2, ',', '.').'%'
                )
            );
        }

        return collect($interpretations)
            ->unique(fn (array $item) => $item['title'].'|'.$item['message'])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, MonthlyResult>  $monthlyResults
     * @return array<string, MonthlyResult|null>
     */
    public function monthlyHighlights(Collection $monthlyResults): array
    {
        return [
            'highestGeneration' => $monthlyResults->sortByDesc(fn (MonthlyResult $result) => (float) $result->estimated_generation_kwh)->first(),
            'lowestGeneration' => $monthlyResults->sortBy(fn (MonthlyResult $result) => (float) $result->estimated_generation_kwh)->first(),
            'highestSavings' => $monthlyResults->sortByDesc(fn (MonthlyResult $result) => (float) $result->estimated_savings_cop)->first(),
            'lowestCoverage' => $monthlyResults->sortBy(fn (MonthlyResult $result) => (float) $result->coverage_percentage)->first(),
        ];
    }

    /**
     * @return array{level: string, title: string, message: string}
     */
    private function insight(string $level, string $title, string $message): array
    {
        return [
            'level' => $level,
            'title' => $title,
            'message' => $message,
        ];
    }
}
