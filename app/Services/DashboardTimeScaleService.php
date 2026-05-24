<?php

namespace App\Services;

use App\Models\CalculationResult;
use App\Models\MonthlyResult;
use App\Models\SolarProject;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardTimeScaleService
{
    /**
     * @param  Collection<int, MonthlyResult>  $monthlyResults
     * @param  Collection<int, mixed>  $dailyClimateRows
     * @param  array<string, mixed>  $weatherAnalysis
     * @param  array<string, mixed>  $energyAnalysis
     * @param  array<string, mixed>  $solarRecommendations
     * @return array{defaultScale: string, scales: array<string, array<string, mixed>>, chartPayloads: array<string, array<string, mixed>>, activeScale: array<string, mixed>|null}
     */
    public function build(
        SolarProject $solarProject,
        ?CalculationResult $calculationResult,
        Collection $monthlyResults,
        Collection $dailyClimateRows,
        array $weatherAnalysis,
        array $energyAnalysis,
        array $solarRecommendations,
    ): array {
        $orderedMonthlyResults = $monthlyResults
            ->filter(fn ($result) => $result instanceof MonthlyResult)
            ->sortBy('month_number')
            ->values();

        if ($calculationResult === null && $orderedMonthlyResults->isEmpty()) {
            return [
                'defaultScale' => 'monthly',
                'scales' => [],
                'chartPayloads' => [],
                'activeScale' => null,
            ];
        }

        $dailyBreakdown = $this->buildDailyBreakdown($solarProject, $orderedMonthlyResults, $dailyClimateRows);
        $monthlyWindow = $this->latestMonthWindow($dailyBreakdown);
        $dailyWindow = $dailyBreakdown->take(-7)->values();

        $scales = [
            'daily' => $this->dailyScale(
                $solarProject,
                $calculationResult,
                $dailyWindow,
                $weatherAnalysis,
                $solarRecommendations,
            ),
            'monthly' => $this->monthlyScale(
                $solarProject,
                $calculationResult,
                $orderedMonthlyResults,
                $monthlyWindow,
                $dailyBreakdown,
                $weatherAnalysis,
                $solarRecommendations,
            ),
            'annual' => $this->annualScale(
                $solarProject,
                $calculationResult,
                $orderedMonthlyResults,
                $monthlyWindow,
                $dailyBreakdown,
                $energyAnalysis,
                $solarRecommendations,
            ),
        ];

        return [
            'defaultScale' => 'monthly',
            'scales' => $scales,
            'chartPayloads' => collect($scales)
                ->mapWithKeys(fn (array $scale, string $key) => [$key => $scale['chart']])
                ->all(),
            'activeScale' => $scales['monthly'],
        ];
    }

    /**
     * @param  Collection<int, MonthlyResult>  $monthlyResults
     * @param  Collection<int, mixed>  $dailyClimateRows
     * @return Collection<int, array<string, mixed>>
     */
    private function buildDailyBreakdown(
        SolarProject $solarProject,
        Collection $monthlyResults,
        Collection $dailyClimateRows,
    ): Collection {
        if ($dailyClimateRows->isEmpty() || $monthlyResults->isEmpty()) {
            return collect();
        }

        $monthlyByNumber = $monthlyResults->keyBy('month_number');

        return $dailyClimateRows
            ->map(function ($row) {
                $date = data_get($row, 'date_time');
                $radiation = data_get($row, 'allsky_sfc_sw_dwn');

                if ($date === null || $radiation === null) {
                    return null;
                }

                $parsedDate = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);

                return [
                    'date' => $parsedDate->startOfDay(),
                    'month_number' => (int) $parsedDate->month,
                    'radiation_hsp' => (float) $radiation * 24 / 1000,
                ];
            })
            ->filter()
            ->groupBy('month_number')
            ->flatMap(function (Collection $rows, int $monthNumber) use ($monthlyByNumber, $solarProject) {
                /** @var MonthlyResult|null $monthlyResult */
                $monthlyResult = $monthlyByNumber->get($monthNumber);

                if (! $monthlyResult instanceof MonthlyResult) {
                    return [];
                }

                $monthlyGeneration = (float) $monthlyResult->estimated_generation_kwh;
                $monthlyConsumption = (float) $monthlyResult->estimated_consumption_kwh;
                $monthlySavings = (float) $monthlyResult->estimated_savings_cop;
                $radiationTotal = max(0.0001, (float) $rows->sum('radiation_hsp'));
                $daysInMonth = max(1, $rows->count());

                return $rows->map(function (array $row) use (
                    $solarProject,
                    $monthlyGeneration,
                    $monthlyConsumption,
                    $monthlySavings,
                    $radiationTotal,
                    $daysInMonth,
                ) {
                    $weight = $row['radiation_hsp'] > 0
                        ? $row['radiation_hsp'] / $radiationTotal
                        : 1 / $daysInMonth;
                    $generation = $monthlyGeneration * $weight;
                    $consumption = $monthlyConsumption / $daysInMonth;
                    $savings = $monthlySavings * $weight;

                    return [
                        'date' => $row['date'],
                        'label' => $row['date']->format('Y-m-d'),
                        'short_label' => ucfirst($row['date']->locale('es')->translatedFormat('d M')),
                        'radiation_hsp' => $row['radiation_hsp'],
                        'generation_kwh' => $generation,
                        'consumption_kwh' => $consumption,
                        'coverage_percentage' => $this->coverage($generation, $consumption),
                        'savings_cop' => $savings > 0 ? $savings : $generation * (float) $solarProject->energy_rate_cop_kwh,
                    ];
                });
            })
            ->sortBy('date')
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $dailyBreakdown
     * @return Collection<int, array<string, mixed>>
     */
    private function latestMonthWindow(Collection $dailyBreakdown): Collection
    {
        if ($dailyBreakdown->isEmpty()) {
            return collect();
        }

        $latest = $dailyBreakdown->last()['date'];
        $latestMonthKey = $latest instanceof Carbon ? $latest->format('Y-m') : null;

        return $dailyBreakdown
            ->filter(fn (array $row) => $row['date']->format('Y-m') === $latestMonthKey)
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $dailyWindow
     * @param  array<string, mixed>  $weatherAnalysis
     * @param  array<string, mixed>  $solarRecommendations
     * @return array<string, mixed>
     */
    private function dailyScale(
        SolarProject $solarProject,
        ?CalculationResult $calculationResult,
        Collection $dailyWindow,
        array $weatherAnalysis,
        array $solarRecommendations,
    ): array {
        $latestDay = $dailyWindow->last();
        $generation = $latestDay !== null
            ? (float) $latestDay['generation_kwh']
            : ($calculationResult ? (float) $calculationResult->estimated_daily_generation_kwh : 0.0);
        $consumption = $solarProject->dailyConsumption();
        $savings = $generation * (float) $solarProject->energy_rate_cop_kwh;
        $coverage = $this->coverage($generation, $consumption);
        $balance = $generation - $consumption;
        $radiation = $latestDay['radiation_hsp'] ?? null;
        $avgRecentCoverage = $dailyWindow->avg('coverage_percentage');
        $avgRecentRadiation = $dailyWindow->avg('radiation_hsp');

        $insights = [];

        if ($radiation !== null) {
            if ($radiation >= 5) {
                $insights[] = $this->insight('success', 'Radiacion solar del dia', 'Hoy la radiacion disponible favorece el autoconsumo y el desplazamiento de cargas diurnas.');
            } elseif ($radiation <= 3.2) {
                $insights[] = $this->insight('warning', 'Radiacion solar del dia', 'Hoy la radiacion es baja y la dependencia de red puede aumentar fuera de la franja solar.');
            }
        }

        if ($avgRecentCoverage !== null && $avgRecentCoverage < 70) {
            $insights[] = $this->insight('warning', 'Cobertura reciente', 'La cobertura diaria reciente viene por debajo del nivel operativo deseado.');
        }

        if (filled(data_get($weatherAnalysis, 'current.0.message'))) {
            $insights[] = $this->insight('info', 'Lectura operativa actual', (string) data_get($weatherAnalysis, 'current.0.message'));
        }

        $recommendations = [];

        if ($radiation !== null && $avgRecentRadiation !== null && $radiation >= $avgRecentRadiation) {
            $recommendations[] = $this->recommendation('recommendation', 'alta', 'Hoy conviene operar cargas intensivas entre las 11 AM y 2 PM para capturar la mejor ventana solar.');
        } elseif ($radiation !== null) {
            $recommendations[] = $this->recommendation('risk', 'media', 'Hoy la radiacion es mas limitada; prioriza consumo esencial y evita desplazar cargas al final de la tarde.');
        }

        if ($coverage < 70) {
            $recommendations[] = $this->recommendation('alert', 'alta', 'La cobertura diaria es limitada; conviene moderar cargas no criticas y concentrar el consumo en la franja solar.');
        }

        $recommendations = $this->mergeRecommendationMessages($recommendations, $solarRecommendations['recommendations'] ?? [], 3);

        return [
            'key' => 'daily',
            'label' => 'Diario',
            'rangeLabel' => $latestDay ? 'Ultimo dia disponible del periodo reciente' : 'Sin datos diarios',
            'summary' => $this->dailySummary($coverage, $savings),
            'stateTitle' => $this->coverageTitle($coverage),
            'stateTone' => $this->coverageTone($coverage),
            'risk' => $recommendations[1]['message'] ?? 'No se identifican riesgos diarios criticos.',
            'primaryRecommendation' => $recommendations[0]['message'] ?? 'Sin recomendacion diaria disponible.',
            'kpis' => $this->kpis(
                'diaria',
                $coverage,
                $savings,
                $generation,
                $consumption,
                $balance,
                $calculationResult,
            ),
            'insights' => $insights,
            'recommendations' => $recommendations,
            'chart' => $this->chartPayload(
                $dailyWindow,
                'short_label',
                'generation_kwh',
                'consumption_kwh',
                'savings_cop',
                'coverage_percentage',
                'Ultimos 7 dias',
                'Generacion diaria reciente',
                'Consumo vs generacion diaria',
                'Ahorro diario reciente',
                'Cobertura diaria reciente',
            ),
            'table' => [
                'title' => 'Operacion diaria reciente',
                'subtitle' => 'Seguimiento de los ultimos dias con foco operativo. Los KPIs usan el ultimo dia disponible o la estimacion diaria derivada.',
                'headers' => ['Periodo', 'Radiacion', 'Generacion', 'Consumo', 'Cobertura', 'Ahorro'],
                'rows' => $this->tableRows($dailyWindow),
                'footer' => $this->tableFooter('Total ultimos 7 dias', $dailyWindow),
            ],
            'highlights' => [
                $this->highlight('Dia activo', $latestDay ? $latestDay['short_label'].' - '.number_format((float) $generation, 2, ',', '.').' kWh' : 'Pendiente'),
                $this->highlight('Cobertura promedio', $avgRecentCoverage !== null ? number_format((float) $avgRecentCoverage, 2, ',', '.').'%' : 'Pendiente'),
                $this->highlight('Consumo base diario', number_format($solarProject->dailyConsumption(), 2, ',', '.').' kWh'),
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $monthlyWindow
     * @param  Collection<int, array<string, mixed>>  $dailyBreakdown
     * @param  array<string, mixed>  $weatherAnalysis
     * @param  array<string, mixed>  $solarRecommendations
     * @return array<string, mixed>
     */
    private function monthlyScale(
        SolarProject $solarProject,
        ?CalculationResult $calculationResult,
        Collection $monthlyResults,
        Collection $monthlyWindow,
        Collection $dailyBreakdown,
        array $weatherAnalysis,
        array $solarRecommendations,
    ): array {
        $observedDays = max(1, $monthlyWindow->count());
        $latestObservedDate = $monthlyWindow->last()['date'] ?? null;
        $targetMonthDays = $latestObservedDate instanceof Carbon ? $latestObservedDate->daysInMonth : 30;
        $observedGeneration = (float) $monthlyWindow->sum('generation_kwh');
        $averageDailyGeneration = $monthlyWindow->isNotEmpty()
            ? (float) $monthlyWindow->avg('generation_kwh')
            : ($calculationResult ? (float) $calculationResult->estimated_monthly_generation_kwh / 30 : 0.0);
        $projectedGeneration = $averageDailyGeneration * $targetMonthDays;
        $latestMonthlyResult = $monthlyResults->last();

        $generation = $latestMonthlyResult instanceof MonthlyResult
            ? (float) $latestMonthlyResult->estimated_generation_kwh
            : ($monthlyWindow->isNotEmpty()
                ? $projectedGeneration
                : ($calculationResult ? (float) $calculationResult->estimated_monthly_generation_kwh : 0.0));
        $consumption = $solarProject->monthlyConsumption();
        $savings = $generation * (float) $solarProject->energy_rate_cop_kwh;
        $coverage = $this->coverage($generation, $consumption);
        $balance = $generation - $consumption;

        $groupedMonths = $dailyBreakdown
            ->groupBy(fn (array $row) => $row['date']->format('Y-m'))
            ->values();
        $latestMonth = $groupedMonths->last();
        $previousMonth = $groupedMonths->slice(-2, 1)->first();
        $previousCoverage = $previousMonth ? $this->coverage((float) $previousMonth->sum('generation_kwh'), (float) $previousMonth->sum('consumption_kwh')) : null;
        $coverageDelta = $previousCoverage !== null ? $coverage - $previousCoverage : null;

        $insights = [];

        if ($coverageDelta !== null) {
            $insights[] = $coverageDelta >= 0
                ? $this->insight('success', 'Evolucion mensual', 'Este mes la cobertura mejoro frente al mes anterior y el sistema responde mejor al consumo.')
                : $this->insight('warning', 'Evolucion mensual', 'Este mes aumento la dependencia de red frente al periodo mensual anterior.');
        }

        if ($coverage < 70) {
            $insights[] = $this->insight('warning', 'Cobertura mensual', 'La cobertura mensual sigue siendo limitada y conviene reforzar la gestion de cargas.');
        } elseif ($coverage >= 100) {
            $insights[] = $this->insight('success', 'Cobertura mensual', 'La generacion del mes cubre de forma solida la demanda del proyecto.');
        }

        if (filled(data_get($weatherAnalysis, 'historical.0.message'))) {
            $insights[] = $this->insight('info', 'Tendencia climatica reciente', (string) data_get($weatherAnalysis, 'historical.0.message'));
        }

        $recommendations = [];

        if ($coverage < 70) {
            $recommendations[] = $this->recommendation('recommendation', 'alta', 'Este mes conviene desplazar cargas flexibles a horas solares y revisar picos de consumo fuera del mediodia.');
            $recommendations[] = $this->recommendation('risk', 'alta', 'Este mes aumento la dependencia de red y puede reducir el ahorro operativo esperado.');
        } else {
            $recommendations[] = $this->recommendation('opportunity', 'media', 'Este mes existe margen para consolidar autoconsumo diurno y reducir compras de energia en horario pico.');
        }

        $recommendations = $this->mergeRecommendationMessages($recommendations, $solarRecommendations['items'] ?? [], 4);

        $latestLabel = $latestMonth && $latestMonth->isNotEmpty()
            ? ucfirst($latestMonth->last()['date']->locale('es')->translatedFormat('F Y'))
            : 'Mes reciente';

        return [
            'key' => 'monthly',
            'label' => 'Mensual',
            'rangeLabel' => $monthlyWindow->isNotEmpty()
                ? "Mes activo con {$observedDays} dias de datos disponibles"
                : $latestLabel,
            'summary' => $monthlyWindow->isNotEmpty()
                ? 'La vista mensual compara la generacion del mes activo contra el consumo mensual base. Los datos diarios quedan como referencia operativa.'
                : $this->monthlySummary($coverage, $savings),
            'stateTitle' => $this->coverageTitle($coverage),
            'stateTone' => $this->coverageTone($coverage),
            'risk' => $recommendations[1]['message'] ?? 'No se identifican riesgos mensuales criticos.',
            'primaryRecommendation' => $recommendations[0]['message'] ?? 'Sin recomendacion mensual disponible.',
            'kpis' => $this->kpis(
                'mensual',
                $coverage,
                $savings,
                $generation,
                $consumption,
                $balance,
                $calculationResult,
            ),
            'insights' => $insights,
            'recommendations' => $recommendations,
            'chart' => $this->chartPayload(
                $monthlyWindow,
                'short_label',
                'generation_kwh',
                'consumption_kwh',
                'savings_cop',
                'coverage_percentage',
                $latestLabel,
                'Generacion del mes por dia',
                'Consumo vs generacion del mes',
                'Ahorro diario acumulado del mes',
                'Cobertura diaria del mes',
            ),
            'table' => [
                'title' => 'Desglose mensual operativo',
                'subtitle' => $monthlyWindow->isNotEmpty()
                    ? 'Comportamiento diario disponible del ultimo mes. Los KPIs muestran la escala mensual completa del proyecto.'
                    : 'Comportamiento diario del ultimo mes disponible.',
                'headers' => ['Dia', 'Radiacion', 'Generacion', 'Consumo', 'Cobertura', 'Ahorro'],
                'rows' => $this->tableRows($monthlyWindow),
                'footer' => $this->tableFooter(
                    $monthlyWindow->isNotEmpty() ? 'Total disponible del mes' : 'Total del mes',
                    $monthlyWindow
                ),
            ],
            'highlights' => [
                $this->highlight('Mes activo', $latestLabel),
                $this->highlight('Generacion mensual estimada', number_format($generation, 2, ',', '.').' kWh'),
                $this->highlight('Generacion disponible del mes', number_format($observedGeneration, 2, ',', '.').' kWh'),
                $this->highlight('Proyeccion al cierre del mes', number_format($projectedGeneration, 2, ',', '.').' kWh'),
                $this->highlight('Ahorro mensual estimado', '$ '.number_format($savings, 0, ',', '.').' COP'),
                $this->highlight('Consumo base mensual', number_format($solarProject->monthlyConsumption(), 2, ',', '.').' kWh'),
            ],
        ];
    }

    /**
     * @param  Collection<int, MonthlyResult>  $monthlyResults
     * @param  Collection<int, array<string, mixed>>  $dailyBreakdown
     * @param  array<string, mixed>  $energyAnalysis
     * @param  array<string, mixed>  $solarRecommendations
     * @return array<string, mixed>
     */
    private function annualScale(
        SolarProject $solarProject,
        ?CalculationResult $calculationResult,
        Collection $monthlyResults,
        Collection $monthlyWindow,
        Collection $dailyBreakdown,
        array $energyAnalysis,
        array $solarRecommendations,
    ): array {
        $observedDays = max(1, $dailyBreakdown->count());
        $observedGeneration = (float) $dailyBreakdown->sum('generation_kwh');
        $projectedMonthlyGeneration = $monthlyWindow->isNotEmpty()
            ? ((float) $monthlyWindow->avg('generation_kwh')) * (($monthlyWindow->last()['date'] ?? null) instanceof Carbon ? $monthlyWindow->last()['date']->daysInMonth : 30)
            : ($calculationResult ? (float) $calculationResult->estimated_monthly_generation_kwh : 0.0);
        $projectedAnnualGeneration = $projectedMonthlyGeneration * 12;

        $generation = $calculationResult
            ? (float) $calculationResult->estimated_annual_generation_kwh
            : ($dailyBreakdown->isNotEmpty() ? $observedGeneration : (float) $monthlyResults->sum('estimated_generation_kwh'));
        $consumption = $solarProject->annualConsumption();
        $savings = $calculationResult
            ? (float) $calculationResult->estimated_annual_savings_cop
            : ($generation * (float) $solarProject->energy_rate_cop_kwh);
        $coverage = $this->coverage($generation, $consumption);
        $balance = $generation - $consumption;

        return [
            'key' => 'annual',
            'label' => 'Anual',
            'rangeLabel' => $dailyBreakdown->isNotEmpty()
                ? "Proyeccion anual con {$observedDays} dias de contexto disponible"
                : 'Vision anual proyectada',
            'summary' => $dailyBreakdown->isNotEmpty()
                ? 'La lectura anual muestra la proyeccion completa del proyecto. Los datos disponibles quedan como contexto secundario.'
                : (string) ($energyAnalysis['coverageInterpretation'] ?? 'No hay resumen anual disponible.'),
            'stateTitle' => $this->coverageTitle($coverage),
            'stateTone' => $this->coverageTone($coverage),
            'risk' => data_get($solarRecommendations, 'alerts.0.message')
                ?? data_get($solarRecommendations, 'risks.0.message')
                ?? 'No se identifican riesgos anuales criticos.',
            'primaryRecommendation' => data_get($solarRecommendations, 'recommendations.0.message')
                ?? data_get($solarRecommendations, 'items.0.message')
                ?? 'Sin recomendacion anual disponible.',
            'kpis' => $this->kpis(
                'anual',
                $coverage,
                $savings,
                $generation,
                $consumption,
                $balance,
                $calculationResult,
            ),
            'insights' => collect($energyAnalysis['insights'] ?? [])
                ->map(fn (array $item) => $this->insight(
                    (string) ($item['level'] ?? 'info'),
                    (string) ($item['title'] ?? 'Insight anual'),
                    (string) ($item['message'] ?? '')
                ))
                ->filter(fn (array $item) => filled($item['message']))
                ->values()
                ->all(),
            'recommendations' => collect($solarRecommendations['items'] ?? [])
                ->take(4)
                ->values()
                ->all(),
            'chart' => [
                'labels' => $monthlyResults->pluck('month_name')->map(fn (string $label) => ucfirst($label))->values()->all(),
                'generation' => $monthlyResults->pluck('estimated_generation_kwh')->map(fn ($value) => (float) $value)->values()->all(),
                'consumption' => $monthlyResults->pluck('estimated_consumption_kwh')->map(fn ($value) => (float) $value)->values()->all(),
                'savings' => $monthlyResults->pluck('estimated_savings_cop')->map(fn ($value) => (float) $value)->values()->all(),
                'coverage' => $monthlyResults->pluck('coverage_percentage')->map(fn ($value) => (float) $value)->values()->all(),
                'rangeLabel' => 'Meses del periodo',
                'generationTitle' => 'Generacion anual por mes',
                'comparisonTitle' => 'Consumo vs generacion anual',
                'savingsTitle' => 'Ahorro anual por mes',
                'coverageTitle' => 'Cobertura anual por mes',
            ],
            'table' => [
                'title' => 'Desglose anual',
                'subtitle' => $dailyBreakdown->isNotEmpty()
                    ? 'Resumen mensual disponible y KPI anual proyectado desde el consumo mensual base.'
                    : 'Resumen mensual consolidado del proyecto.',
                'headers' => ['Mes', 'Dias', 'Radiacion', 'Generacion', 'Consumo', 'Cobertura', 'Ahorro'],
                'rows' => $monthlyResults->map(fn (MonthlyResult $monthlyResult) => [
                    'period' => ucfirst((string) $monthlyResult->month_name),
                    'days' => (int) $monthlyResult->days_in_month,
                    'radiation' => (float) $monthlyResult->average_daily_solar_radiation,
                    'generation' => (float) $monthlyResult->estimated_generation_kwh,
                    'consumption' => (float) $monthlyResult->estimated_consumption_kwh,
                    'coverage' => (float) $monthlyResult->coverage_percentage,
                    'savings' => (float) $monthlyResult->estimated_savings_cop,
                ])->values()->all(),
                'footer' => [
                    'label' => 'Total anual proyectado',
                    'generation' => $generation,
                    'consumption' => $consumption,
                    'savings' => $savings,
                ],
            ],
            'highlights' => [
                $this->highlight('Consumo anual base', number_format($solarProject->annualConsumption(), 2, ',', '.').' kWh'),
                $this->highlight('Generacion anual estimada', number_format($generation, 2, ',', '.').' kWh'),
                $this->highlight('Generacion disponible como contexto', number_format($observedGeneration, 2, ',', '.').' kWh'),
                $this->highlight('Proyeccion al cierre del ano', number_format($projectedAnnualGeneration, 2, ',', '.').' kWh'),
                $this->highlight('Ahorro anual estimado', '$ '.number_format($savings, 0, ',', '.').' COP'),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function kpis(
        string $scopeLabel,
        float $coverage,
        float $savings,
        float $generation,
        float $consumption,
        float $balance,
        ?CalculationResult $calculationResult,
    ): array {
        $scopeType = match ($scopeLabel) {
            'anual' => 'proyectado',
            'mensual' => 'estimado',
            default => 'operativo',
        };
        $consumptionDescription = match ($scopeLabel) {
            'mensual' => 'Consumo mensual base registrado en el proyecto.',
            'anual' => 'Consumo anual base derivado del consumo mensual del proyecto.',
            default => "Demanda {$scopeType} usada para la lectura {$scopeLabel}.",
        };

        return [
            [
                'label' => "Consumo {$scopeLabel}",
                'value' => $consumption,
                'type' => 'kwh',
                'tone' => 'text-[color:var(--solar-text)]',
                'description' => $consumptionDescription,
            ],
            [
                'label' => "Generacion {$scopeLabel}",
                'value' => $generation,
                'type' => 'kwh',
                'tone' => 'text-[color:var(--solar-text)]',
                'description' => match ($scopeLabel) {
                    'diaria' => 'Energia solar del ultimo dia disponible o estimacion diaria derivada.',
                    'anual' => 'Energia solar anual proyectada para dimensionamiento e impacto.',
                    default => "Energia solar {$scopeType} para la escala {$scopeLabel}.",
                },
            ],
            [
                'label' => "Cobertura {$scopeLabel}",
                'value' => $coverage,
                'type' => 'percent',
                'tone' => $this->coverageTone($coverage),
                'description' => "Porcentaje del consumo {$scopeType} {$scopeLabel} cubierto por la generacion solar.",
            ],
            [
                'label' => "Ahorro {$scopeLabel}",
                'value' => $savings,
                'type' => 'money',
                'tone' => 'text-[color:var(--solar-text)]',
                'description' => "Impacto economico {$scopeType} en escala {$scopeLabel}.",
            ],
            [
                'label' => "Balance {$scopeLabel}",
                'value' => $balance,
                'type' => 'kwh',
                'tone' => 'text-[color:var(--solar-text)]',
                'description' => "Generacion menos consumo {$scopeType} en escala {$scopeLabel}.",
            ],
            [
                'label' => 'Capacidad instalada',
                'value' => $calculationResult ? (float) $calculationResult->installed_capacity_kwp : null,
                'type' => 'kwp',
                'tone' => 'text-[color:var(--solar-text)]',
                'description' => $calculationResult
                    ? number_format((int) $calculationResult->number_of_panels, 0, ',', '.').' paneles instalables.'
                    : 'Sin calculo disponible.',
            ],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function chartPayload(
        Collection $rows,
        string $labelKey,
        string $generationKey,
        string $consumptionKey,
        string $savingsKey,
        string $coverageKey,
        string $rangeLabel,
        string $generationTitle,
        string $comparisonTitle,
        string $savingsTitle,
        string $coverageTitle,
    ): array {
        return [
            'labels' => $rows->pluck($labelKey)->values()->all(),
            'generation' => $rows->pluck($generationKey)->map(fn ($value) => (float) $value)->values()->all(),
            'consumption' => $rows->pluck($consumptionKey)->map(fn ($value) => (float) $value)->values()->all(),
            'savings' => $rows->pluck($savingsKey)->map(fn ($value) => (float) $value)->values()->all(),
            'coverage' => $rows->pluck($coverageKey)->map(fn ($value) => (float) $value)->values()->all(),
            'rangeLabel' => $rangeLabel,
            'generationTitle' => $generationTitle,
            'comparisonTitle' => $comparisonTitle,
            'savingsTitle' => $savingsTitle,
            'coverageTitle' => $coverageTitle,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function tableRows(Collection $rows): array
    {
        return $rows->map(fn (array $row) => [
            'period' => $row['short_label'],
            'days' => 1,
            'radiation' => (float) $row['radiation_hsp'],
            'generation' => (float) $row['generation_kwh'],
            'consumption' => (float) $row['consumption_kwh'],
            'coverage' => (float) $row['coverage_percentage'],
            'savings' => (float) $row['savings_cop'],
        ])->values()->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function tableFooter(string $label, Collection $rows): array
    {
        return [
            'label' => $label,
            'generation' => (float) $rows->sum('generation_kwh'),
            'consumption' => (float) $rows->sum('consumption_kwh'),
            'savings' => (float) $rows->sum('savings_cop'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $baseItems
     * @param  array<int, array<string, mixed>>  $extraItems
     * @return array<int, array<string, mixed>>
     */
    private function mergeRecommendationMessages(array $baseItems, array $extraItems, int $limit): array
    {
        return collect($baseItems)
            ->merge($extraItems)
            ->filter(fn ($item) => is_array($item) && filled($item['message'] ?? null))
            ->unique('message')
            ->take($limit)
            ->values()
            ->all();
    }

    private function coverage(float $generation, float $consumption): float
    {
        if ($consumption <= 0) {
            return 0;
        }

        return ($generation / $consumption) * 100;
    }

    private function coverageTitle(float $coverage): string
    {
        if ($coverage >= 100) {
            return 'Cobertura favorable';
        }

        if ($coverage >= 70) {
            return 'Cobertura funcional';
        }

        return 'Cobertura limitada';
    }

    private function coverageTone(float $coverage): string
    {
        if ($coverage >= 100) {
            return 'text-emerald-600 dark:text-emerald-400';
        }

        if ($coverage >= 70) {
            return 'text-sky-600 dark:text-sky-400';
        }

        return 'text-amber-600 dark:text-amber-400';
    }

    private function dailySummary(float $coverage, float $savings): string
    {
        if ($coverage >= 100) {
            return 'Hoy el sistema puede cubrir la mayor parte del consumo y abrir una ventana clara de autoconsumo.';
        }

        if ($coverage >= 70) {
            return 'Hoy el sistema sostiene una operacion solar funcional con ahorro diario estimado de $ '.number_format($savings, 0, ',', '.').' COP.';
        }

        return 'Hoy la operacion solar es mas limitada y conviene gestionar cargas para proteger el ahorro diario.';
    }

    private function monthlySummary(float $coverage, float $savings): string
    {
        if ($coverage >= 100) {
            return 'El comportamiento del mes muestra una cobertura solar robusta y espacio para consolidar autoconsumo.';
        }

        if ($coverage >= 70) {
            return 'Este mes el proyecto mantiene una cobertura solar funcional con ahorro estimado de $ '.number_format($savings, 0, ',', '.').' COP.';
        }

        return 'Este mes la dependencia de red sigue alta y el dashboard recomienda enfocarse en operacion de corto plazo.';
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

    /**
     * @return array{type: string, priority: string, message: string}
     */
    private function recommendation(string $type, string $priority, string $message): array
    {
        return [
            'type' => $type,
            'priority' => $priority,
            'message' => $message,
        ];
    }

    /**
     * @return array{label: string, value: string}
     */
    private function highlight(string $label, string $value): array
    {
        return [
            'label' => $label,
            'value' => $value,
        ];
    }
}
