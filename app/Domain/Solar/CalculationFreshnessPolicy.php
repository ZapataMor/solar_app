<?php

namespace App\Domain\Solar;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Decides if a project should be recalculated, and why (pure: dates in, verdict out).
 *
 * Only changes that can alter the result count:
 *  - the project's own inputs changed after the calculation;
 *  - the reference tariff it follows changed after the calculation (ADR-0015);
 *  - the calculation predates sizing by consumption (ADR-0014: it filled the roof);
 *  - a climate source of better quality than the one used now has data for the period;
 *  - the source used got new data at least one grace period after the calculation.
 *
 * The grace period avoids a permanent "!" on projects whose period includes today: a
 * high-frequency station (Ambient, every 5 minutes) would otherwise always look stale.
 */
final class CalculationFreshnessPolicy
{
    public const SAME_SOURCE_GRACE = 'P1D';

    /**
     * @param  list<string>  $sourcePriority  Climate source keys, best quality first.
     * @param  array<string, string>  $sourceLabels  Key => name shown to the user.
     * @param  array<string, DateTimeInterface|null>  $sourceChanges  Key => last change of its data in the project period.
     * @param  bool  $sizedByConsumption  False for a result stored before ADR-0014 (it has no panels needed).
     * @param  DateTimeInterface|null  $referenceTariffChangedAt  When the reference tariff the project follows last changed (ADR-0015).
     */
    public function evaluate(
        ?DateTimeInterface $calculatedAt,
        ?string $usedSource,
        bool $hasTechnicalParameters,
        ?DateTimeInterface $inputsChangedAt,
        array $sourcePriority,
        array $sourceLabels,
        array $sourceChanges,
        bool $sizedByConsumption = true,
        ?DateTimeInterface $referenceTariffChangedAt = null,
    ): CalculationFreshness {
        if (! $hasTechnicalParameters) {
            return new CalculationFreshness(CalculationFreshness::NOT_READY, ['Faltan los parámetros técnicos del proyecto.']);
        }

        $hasClimateData = array_filter($sourceChanges) !== [];

        if ($calculatedAt === null) {
            return $hasClimateData
                ? new CalculationFreshness(CalculationFreshness::PENDING, ['El proyecto todavía no se ha calculado.'])
                : new CalculationFreshness(CalculationFreshness::NOT_READY, ['Aún no hay datos climáticos para el periodo del proyecto.']);
        }

        $calculatedAt = DateTimeImmutable::createFromInterface($calculatedAt);
        $reasons = [];

        if ($inputsChangedAt !== null && $inputsChangedAt > $calculatedAt) {
            $reasons[] = 'Cambiaste datos del proyecto después del último cálculo.';
        }

        if ($referenceTariffChangedAt !== null && $referenceTariffChangedAt > $calculatedAt) {
            $reasons[] = 'Se actualizó la tarifa de referencia del kWh.';
        }

        if (! $sizedByConsumption) {
            $reasons[] = 'Mejoramos el cálculo: ahora se instalan solo los paneles que necesitas.';
        }

        $usedIndex = array_search($usedSource, $sourcePriority, true);
        $usedIndex = $usedIndex === false ? count($sourcePriority) - 1 : $usedIndex;
        $sameSourceThreshold = $calculatedAt->add(new DateInterval(self::SAME_SOURCE_GRACE));

        foreach ($sourcePriority as $index => $key) {
            $changedAt = $sourceChanges[$key] ?? null;
            $label = $sourceLabels[$key] ?? $key;

            if ($changedAt === null || $index > $usedIndex) {
                continue; // no data, or a lower-quality source than the one used: it would not be chosen.
            }

            if ($index < $usedIndex && $changedAt > $calculatedAt) {
                $reasons[] = "Hay datos de {$label}, una fuente de mejor calidad que la usada.";
            } elseif ($index === $usedIndex && $changedAt > $sameSourceThreshold) {
                $reasons[] = "Llegaron datos nuevos de {$label}.";
            }
        }

        return $reasons === []
            ? new CalculationFreshness(CalculationFreshness::FRESH)
            : new CalculationFreshness(CalculationFreshness::STALE, $reasons);
    }
}
