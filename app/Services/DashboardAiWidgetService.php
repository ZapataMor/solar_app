<?php

namespace App\Services;

use Illuminate\Support\Collection;

class DashboardAiWidgetService
{
    /**
     * @param  array<string, mixed>  $dashboard
     * @return array{
     *     executive_summary: string,
     *     widgets: array<string, array<string, mixed>>
     * }
     */
    public function build(array $dashboard): array
    {
        $state = is_array($dashboard['state'] ?? null) ? $dashboard['state'] : [];
        $insights = is_array($dashboard['insights'] ?? null) ? $dashboard['insights'] : [];
        $recommendations = is_array($dashboard['recommendations'] ?? null) ? $dashboard['recommendations'] : [];
        $executiveSummary = is_array($dashboard['executiveSummary'] ?? null) ? $dashboard['executiveSummary'] : [];
        $weather = is_array($insights['weather'] ?? null) ? $insights['weather'] : [];
        $recommendationGroups = is_array($recommendations['groups'] ?? null) ? $recommendations['groups'] : [];

        $actionItems = $this->normalizeStructuredItems($recommendationGroups['actions'] ?? []);
        $alertItems = $this->normalizeStructuredItems($recommendationGroups['alerts'] ?? []);
        $riskItems = $this->normalizeStructuredItems($recommendationGroups['risks'] ?? []);
        $opportunityItems = $this->normalizeStructuredItems($recommendationGroups['opportunities'] ?? []);
        $weatherRiskItems = $this->weatherRiskItems($weather);
        $openAiAlerts = $this->normalizeTextItems($executiveSummary['alerts'] ?? [], 'warning', 'media', 'sparkles');

        $allAlerts = $alertItems->merge($openAiAlerts)->take(4)->values();
        $allRisks = $riskItems->merge($weatherRiskItems)->unique('message')->take(4)->values();
        $coverage = isset($state['coveragePercentage']) ? (float) $state['coveragePercentage'] : null;
        $annualBalance = isset($state['annualBalanceKwh']) ? (float) $state['annualBalanceKwh'] : null;
        $annualSavings = isset($state['annualSavingsCop']) ? (float) $state['annualSavingsCop'] : null;

        return [
            'executive_summary' => $this->stringValue($executiveSummary['text'] ?? null)
                ?? 'Sin resumen ejecutivo disponible.',
            'widgets' => [
                'recommendation_of_day' => [
                    'title' => 'Recomendacion del dia',
                    'icon' => 'lightbulb',
                    'state' => $this->coverageState($coverage),
                    'priority' => $this->coveragePriority($coverage),
                    'summary' => $this->stringValue($executiveSummary['dailyRecommendation'] ?? null)
                        ?? $this->stringValue($recommendations['summary'] ?? null)
                        ?? 'Aun no se genero una recomendacion diaria para este proyecto.',
                    'helper' => 'Muestra la accion operativa principal ya consolidada para el dashboard.',
                    'items' => $actionItems->take(3)->values()->all(),
                    'empty' => 'No hay recomendaciones adicionales para hoy.',
                ],
                'intelligent_alerts' => [
                    'title' => 'Alertas inteligentes',
                    'icon' => 'bell',
                    'state' => $this->collectionState($allAlerts),
                    'priority' => $this->collectionPriority($allAlerts),
                    'summary' => $allAlerts->isNotEmpty()
                        ? sprintf('%d alerta(s) priorizadas en la narrativa del dashboard.', $allAlerts->count())
                        : 'Sin alertas energeticas activas de alta prioridad.',
                    'helper' => 'Consolida alertas operativas estructuradas y apoyo textual de IA.',
                    'items' => $allAlerts->all(),
                    'empty' => 'No se detectaron alertas activas.',
                ],
                'energy_status' => [
                    'title' => 'Estado energetico',
                    'icon' => 'bolt',
                    'state' => $this->coverageState($coverage),
                    'priority' => $this->coveragePriority($coverage),
                    'summary' => $this->stringValue($state['summary'] ?? null)
                        ?? 'Aun no hay suficientes datos para determinar el estado energetico.',
                    'helper' => 'Usa el estado general como fuente canonica de cobertura, balance y ahorro.',
                    'metrics' => [
                        [
                            'label' => 'Cobertura',
                            'value' => $coverage !== null ? number_format($coverage, 2, ',', '.').'%' : 'Pendiente',
                        ],
                        [
                            'label' => 'Balance anual',
                            'value' => $annualBalance !== null ? number_format($annualBalance, 2, ',', '.').' kWh' : 'Pendiente',
                        ],
                        [
                            'label' => 'Ahorro anual',
                            'value' => $annualSavings !== null ? '$ '.number_format($annualSavings, 0, ',', '.').' COP' : 'Pendiente',
                        ],
                    ],
                ],
                'detected_risks' => [
                    'title' => 'Riesgos detectados',
                    'icon' => 'shield-alert',
                    'state' => $this->collectionState($allRisks),
                    'priority' => $this->collectionPriority($allRisks),
                    'summary' => $allRisks->isNotEmpty()
                        ? sprintf('%d riesgo(s) requieren seguimiento operativo.', $allRisks->count())
                        : 'No se identifican riesgos criticos en este momento.',
                    'helper' => 'Agrupa riesgos energeticos y meteorologicos sin duplicar mensajes.',
                    'items' => $allRisks->all(),
                    'empty' => 'No hay riesgos operativos destacados.',
                ],
                'savings_opportunities' => [
                    'title' => 'Oportunidades de ahorro',
                    'icon' => 'piggy-bank',
                    'state' => $opportunityItems->isNotEmpty() || ($annualSavings ?? 0) > 0 ? 'success' : 'warning',
                    'priority' => $opportunityItems->isNotEmpty() ? $this->collectionPriority($opportunityItems) : 'media',
                    'summary' => $opportunityItems->isNotEmpty()
                        ? sprintf('%d oportunidad(es) de ahorro y autoconsumo identificadas.', $opportunityItems->count())
                        : 'Aun no se detectan oportunidades claras de ahorro adicionales.',
                    'helper' => 'Toma solo oportunidades canonicas definidas por la capa de recomendaciones.',
                    'items' => $opportunityItems->take(4)->values()->all(),
                    'empty' => 'No se generaron oportunidades de ahorro complementarias.',
                ],
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function normalizeStructuredItems(array $items): Collection
    {
        return collect($items)
            ->filter(fn ($item) => is_array($item) && filled($item['message'] ?? null))
            ->map(function (array $item) {
                $priority = $this->normalizePriority($item['priority'] ?? null);
                $state = $this->priorityToState($priority, $item['type'] ?? null);

                return [
                    'message' => (string) $item['message'],
                    'priority' => $priority,
                    'state' => $state,
                    'icon' => $this->typeToIcon((string) ($item['type'] ?? 'recommendation')),
                    'type' => (string) ($item['type'] ?? 'recommendation'),
                ];
            })
            ->values();
    }

    /**
     * @param  array<int, mixed>  $items
     * @return Collection<int, array<string, mixed>>
     */
    private function normalizeTextItems(array $items, string $state, string $priority, string $icon): Collection
    {
        return collect($items)
            ->filter(fn ($item) => is_string($item) && filled($item))
            ->map(fn (string $item) => [
                'message' => $item,
                'priority' => $this->normalizePriority($priority),
                'state' => $state,
                'icon' => $icon,
                'type' => 'alert',
            ])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $weather
     * @return Collection<int, array<string, mixed>>
     */
    private function weatherRiskItems(array $weather): Collection
    {
        return collect([
            ...($weather['current'] ?? []),
            ...($weather['historical'] ?? []),
        ])
            ->filter(fn ($item) => is_array($item) && in_array($item['type'] ?? 'info', ['warning', 'error'], true))
            ->map(function (array $item) {
                $state = ($item['type'] ?? 'warning') === 'error' ? 'danger' : 'warning';
                $priority = $state === 'danger' ? 'alta' : 'media';

                return [
                    'message' => (string) ($item['message'] ?? ''),
                    'priority' => $priority,
                    'state' => $state,
                    'icon' => $state === 'danger' ? 'triangle-alert' : 'cloud-alert',
                    'type' => 'risk',
                ];
            })
            ->filter(fn (array $item) => filled($item['message']))
            ->unique('message')
            ->values();
    }

    private function coverageState(?float $coverage): string
    {
        return $coverage === null ? 'warning' : ($coverage >= 100 ? 'success' : ($coverage >= 70 ? 'warning' : 'danger'));
    }

    private function coveragePriority(?float $coverage): string
    {
        return $coverage === null ? 'media' : ($coverage >= 100 ? 'baja' : ($coverage >= 70 ? 'media' : 'alta'));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function collectionState(Collection $items): string
    {
        if ($items->contains(fn (array $item) => ($item['state'] ?? null) === 'danger' || ($item['priority'] ?? null) === 'alta')) {
            return 'danger';
        }

        if ($items->contains(fn (array $item) => ($item['state'] ?? null) === 'warning' || ($item['priority'] ?? null) === 'media')) {
            return 'warning';
        }

        return 'success';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     */
    private function collectionPriority(Collection $items): string
    {
        if ($items->contains(fn (array $item) => ($item['priority'] ?? null) === 'alta')) {
            return 'alta';
        }

        if ($items->contains(fn (array $item) => ($item['priority'] ?? null) === 'media')) {
            return 'media';
        }

        return 'baja';
    }

    private function normalizePriority(mixed $priority): string
    {
        return match (strtolower(trim((string) $priority))) {
            'high', 'alta' => 'alta',
            'low', 'baja' => 'baja',
            default => 'media',
        };
    }

    private function priorityToState(string $priority, ?string $type = null): string
    {
        if ($type === 'opportunity') {
            return 'success';
        }

        return match ($priority) {
            'alta' => in_array($type, ['risk', 'alert'], true) ? 'danger' : 'warning',
            'baja' => 'success',
            default => 'warning',
        };
    }

    private function typeToIcon(string $type): string
    {
        return match ($type) {
            'alert' => 'bell',
            'risk' => 'shield-alert',
            'opportunity' => 'piggy-bank',
            default => 'lightbulb',
        };
    }

    private function stringValue(mixed $value): ?string
    {
        return is_string($value) && filled(trim($value)) ? trim($value) : null;
    }
}
