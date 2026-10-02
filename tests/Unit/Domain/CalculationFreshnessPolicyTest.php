<?php

namespace Tests\Unit\Domain;

use App\Domain\Solar\CalculationFreshness;
use App\Domain\Solar\CalculationFreshnessPolicy;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class CalculationFreshnessPolicyTest extends TestCase
{
    private const PRIORITY = ['ambient', 'local', 'nasa_power'];

    private const LABELS = ['ambient' => 'Ambient Weather', 'local' => 'Centro meteorologico', 'nasa_power' => 'NASA POWER'];

    public function test_fresh_when_nothing_changed_after_the_calculation(): void
    {
        $result = $this->evaluate(usedSource: 'nasa_power', changes: ['nasa_power' => '2026-09-30 08:00']);

        $this->assertSame(CalculationFreshness::FRESH, $result->status);
        $this->assertFalse($result->needsRecalculation());
    }

    public function test_stale_when_the_project_inputs_changed(): void
    {
        $result = $this->evaluate(usedSource: 'nasa_power', inputsChangedAt: '2026-10-01 09:00', changes: ['nasa_power' => '2026-09-30 08:00']);

        $this->assertSame(CalculationFreshness::STALE, $result->status);
        $this->assertSame(['Cambiaste datos del proyecto después del último cálculo.'], $result->reasons);
    }

    public function test_stale_when_a_better_source_has_new_data(): void
    {
        $result = $this->evaluate(usedSource: 'nasa_power', changes: ['ambient' => '2026-10-01 08:05', 'nasa_power' => '2026-09-30 08:00']);

        $this->assertTrue($result->needsRecalculation());
        $this->assertSame(['Hay datos de Ambient Weather, una fuente de mejor calidad que la usada.'], $result->reasons);
    }

    public function test_lower_quality_sources_are_ignored(): void
    {
        // Calculated with Ambient: new NASA data would never be chosen, so it does not matter.
        $result = $this->evaluate(usedSource: 'ambient', changes: ['ambient' => '2026-09-30 09:00', 'nasa_power' => '2026-10-05 00:00']);

        $this->assertSame(CalculationFreshness::FRESH, $result->status);
    }

    public function test_new_data_of_the_same_source_waits_a_grace_day(): void
    {
        $sameDay = $this->evaluate(usedSource: 'ambient', changes: ['ambient' => '2026-10-01 18:00']);
        $nextDay = $this->evaluate(usedSource: 'ambient', changes: ['ambient' => '2026-10-02 10:01']);

        $this->assertSame(CalculationFreshness::FRESH, $sameDay->status);
        $this->assertSame(['Llegaron datos nuevos de Ambient Weather.'], $nextDay->reasons);
    }

    public function test_stale_when_the_calculation_filled_the_roof_before_sizing_by_consumption(): void
    {
        $result = $this->evaluate(usedSource: 'nasa_power', changes: ['nasa_power' => '2026-09-30 08:00'], sizedByConsumption: false);

        $this->assertSame(CalculationFreshness::STALE, $result->status);
        $this->assertSame(['Mejoramos el cálculo: ahora se instalan solo los paneles que necesitas.'], $result->reasons);
    }

    public function test_stale_when_the_reference_tariff_it_follows_changed(): void
    {
        $before = $this->evaluate(changes: ['nasa_power' => '2026-09-30 08:00'], referenceTariffChangedAt: '2026-09-15 00:00');
        $after = $this->evaluate(changes: ['nasa_power' => '2026-09-30 08:00'], referenceTariffChangedAt: '2026-10-01 10:00');

        $this->assertSame(CalculationFreshness::FRESH, $before->status);
        $this->assertSame(['Se actualizó la tarifa de referencia del kWh.'], $after->reasons);
    }

    public function test_pending_or_not_ready_when_never_calculated(): void
    {
        $withData = $this->evaluate(calculatedAt: null, changes: ['nasa_power' => '2026-09-30 08:00']);
        $withoutData = $this->evaluate(calculatedAt: null, changes: []);
        $withoutParameters = $this->evaluate(calculatedAt: null, hasTechnicalParameters: false, changes: ['nasa_power' => '2026-09-30 08:00']);

        $this->assertSame(CalculationFreshness::PENDING, $withData->status);
        $this->assertTrue($withData->needsRecalculation());
        $this->assertSame(CalculationFreshness::NOT_READY, $withoutData->status);
        $this->assertSame(CalculationFreshness::NOT_READY, $withoutParameters->status);
        $this->assertFalse($withoutParameters->needsRecalculation());
    }

    /**
     * @param  array<string, string>  $changes
     */
    private function evaluate(
        ?string $calculatedAt = '2026-10-01 08:00',
        ?string $usedSource = 'nasa_power',
        bool $hasTechnicalParameters = true,
        ?string $inputsChangedAt = '2026-09-01 08:00',
        array $changes = [],
        bool $sizedByConsumption = true,
        ?string $referenceTariffChangedAt = null,
    ): CalculationFreshness {
        return (new CalculationFreshnessPolicy)->evaluate(
            calculatedAt: $calculatedAt ? new DateTimeImmutable($calculatedAt) : null,
            usedSource: $usedSource,
            hasTechnicalParameters: $hasTechnicalParameters,
            inputsChangedAt: $inputsChangedAt ? new DateTimeImmutable($inputsChangedAt) : null,
            sourcePriority: self::PRIORITY,
            sourceLabels: self::LABELS,
            sourceChanges: array_map(fn (string $date) => new DateTimeImmutable($date), $changes),
            sizedByConsumption: $sizedByConsumption,
            referenceTariffChangedAt: $referenceTariffChangedAt ? new DateTimeImmutable($referenceTariffChangedAt) : null,
        );
    }
}
