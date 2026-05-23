<?php

namespace App\Services;

use App\Models\CalculationResult;
use Illuminate\Support\Collection;

class SolarRecommendationService
{
    /**
     * @param  array<string, mixed>  $weatherAnalysis
     * @param  array<string, mixed>  $energyAnalysis
     * @param  array<string, mixed>  $weatherStationStats
     * @return array{
     *     items: array<int, array{type: string, priority: string, message: string}>,
     *     recommendations: array<int, array{type: string, priority: string, message: string}>,
     *     alerts: array<int, array{type: string, priority: string, message: string}>,
     *     risks: array<int, array{type: string, priority: string, message: string}>,
     *     opportunities: array<int, array{type: string, priority: string, message: string}>
     * }
     */
    public function recommend(
        array $weatherAnalysis,
        array $energyAnalysis,
        ?CalculationResult $calculationResult,
        array $weatherStationStats = [],
    ): array {
        $items = [
            ...$this->radiationRecommendations($weatherAnalysis, $weatherStationStats),
            ...$this->coverageRecommendations($energyAnalysis, $calculationResult),
            ...$this->consumptionRecommendations($energyAnalysis, $calculationResult),
            ...$this->climateRecommendations($weatherAnalysis),
        ];

        $items = collect($items)
            ->unique(fn (array $item) => $item['type'].'|'.$item['priority'].'|'.$item['message'])
            ->values();

        return [
            'items' => $items->all(),
            'recommendations' => $items->where('type', 'recommendation')->values()->all(),
            'alerts' => $items->where('type', 'alert')->values()->all(),
            'risks' => $items->where('type', 'risk')->values()->all(),
            'opportunities' => $items->where('type', 'opportunity')->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $weatherAnalysis
     * @param  array<string, mixed>  $weatherStationStats
     * @return array<int, array{type: string, priority: string, message: string}>
     */
    private function radiationRecommendations(array $weatherAnalysis, array $weatherStationStats): array
    {
        $items = [];
        $currentMessages = collect($weatherAnalysis['current'] ?? [])->pluck('message');
        $historicalMessages = collect($weatherAnalysis['historical'] ?? [])->pluck('message');
        $averageRadiation = isset($weatherStationStats['averageRadiation']) ? (float) $weatherStationStats['averageRadiation'] : null;

        if ($this->contains($currentMessages, 'Alta radiacion detectada') || ($averageRadiation !== null && $averageRadiation >= 550)) {
            $items[] = $this->item(
                'recommendation',
                'high',
                'Se recomienda operar equipos de alto consumo entre las 11 AM y 2 PM para aprovechar la mayor disponibilidad solar.'
            );
            $items[] = $this->item(
                'opportunity',
                'medium',
                'Conviene aprovechar las horas de alta radiacion para desplazar cargas diurnas y reducir compra de energia a la red.'
            );
        }

        if ($this->contains($currentMessages, 'Baja radiacion detectada') || $this->contains($historicalMessages, 'Historico de baja radiacion')) {
            $items[] = $this->item(
                'risk',
                'medium',
                'La baja radiacion reduce la ventana de generacion solar util y puede aumentar la necesidad de respaldo de red.'
            );
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $energyAnalysis
     * @return array<int, array{type: string, priority: string, message: string}>
     */
    private function coverageRecommendations(array $energyAnalysis, ?CalculationResult $calculationResult): array
    {
        if ($calculationResult === null) {
            return [];
        }

        $items = [];
        $coverage = (float) $calculationResult->coverage_percentage;
        $insightTitles = collect($energyAnalysis['insights'] ?? [])->pluck('title');

        if ($coverage < 70 || $this->contains($insightTitles, 'Baja cobertura energetica')) {
            $items[] = $this->item(
                'risk',
                'high',
                'Existe riesgo de dependencia de red: la cobertura solar proyectada es insuficiente para sostener la mayor parte del consumo.'
            );
            $items[] = $this->item(
                'recommendation',
                'high',
                'Se recomienda reducir consumo nocturno y trasladar cargas flexibles a horas solares para disminuir la dependencia de red.'
            );
        }

        if ($coverage > 100 || $this->contains($insightTitles, 'Sobreproduccion solar')) {
            $items[] = $this->item(
                'opportunity',
                'high',
                'La sobreproduccion solar abre oportunidad para concentrar procesos diurnos, almacenamiento o nuevas cargas en horario solar.'
            );
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $energyAnalysis
     * @return array<int, array{type: string, priority: string, message: string}>
     */
    private function consumptionRecommendations(array $energyAnalysis, ?CalculationResult $calculationResult): array
    {
        if ($calculationResult === null) {
            return [];
        }

        $items = [];
        $annualSavings = (float) $calculationResult->estimated_annual_savings_cop;
        $annualGeneration = (float) $calculationResult->estimated_annual_generation_kwh;
        $annualConsumption = (float) $calculationResult->annual_consumption_kwh;
        $insightTitles = collect($energyAnalysis['insights'] ?? [])->pluck('title');

        if ($annualGeneration < $annualConsumption || $this->contains($insightTitles, 'Dependencia de red')) {
            $items[] = $this->item(
                'alert',
                'high',
                'La generacion anual es menor al consumo proyectado; conviene priorizar eficiencia y programacion de cargas para horas solares.'
            );
        }

        if ($annualSavings >= 5000000 || $this->contains($insightTitles, 'Potencial alto de ahorro')) {
            $items[] = $this->item(
                'opportunity',
                'medium',
                'El potencial de ahorro es alto: vale la pena consolidar una estrategia operativa enfocada en autoconsumo solar.'
            );
        }

        if (collect($energyAnalysis['monthlyInterpretations'] ?? [])->pluck('title')->contains('Meses con baja cobertura')) {
            $items[] = $this->item(
                'recommendation',
                'medium',
                'En los meses criticos se recomienda planificar mantenimientos, cargas no esenciales y consumo intensivo fuera del horario nocturno.'
            );
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $weatherAnalysis
     * @return array<int, array{type: string, priority: string, message: string}>
     */
    private function climateRecommendations(array $weatherAnalysis): array
    {
        $items = [];
        $currentMessages = collect($weatherAnalysis['current'] ?? [])->pluck('message');

        if ($this->contains($currentMessages, 'Calor extremo detectado') || $this->contains($currentMessages, 'Sensacion termica muy alta')) {
            $items[] = $this->item(
                'alert',
                'medium',
                'El calor extremo puede elevar la demanda de climatizacion; conviene preenfriar espacios durante la franja de mayor generacion solar.'
            );
        }

        if ($this->contains($currentMessages, 'Combinacion de temperatura y humedad elevada')) {
            $items[] = $this->item(
                'recommendation',
                'medium',
                'Se recomienda anticipar cargas de ventilacion y enfriamiento en horario solar para evitar picos de demanda posteriores.'
            );
        }

        return $items;
    }

    /**
     * @param  Collection<int, mixed>  $items
     */
    private function contains(Collection $items, string $needle): bool
    {
        return $items->contains(fn ($item) => is_string($item) && str_contains($item, $needle));
    }

    /**
     * @return array{type: string, priority: string, message: string}
     */
    private function item(string $type, string $priority, string $message): array
    {
        return [
            'type' => $type,
            'priority' => $priority,
            'message' => $message,
        ];
    }
}
