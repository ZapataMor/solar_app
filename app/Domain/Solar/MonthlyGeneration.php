<?php

namespace App\Domain\Solar;

final readonly class MonthlyGeneration
{
    public function __construct(
        public int $monthNumber,
        public string $monthName,
        public int $days,
        public float $averageDailyPeakSunHours,
        public float $generationKwh,
        public float $consumptionKwh,
        public float $coveragePercentage,
        public float $savingsCop,
    ) {}

    /**
     * @return array<string, float|int|string>
     */
    public function toArray(): array
    {
        return [
            'month_number' => $this->monthNumber,
            'month_name' => $this->monthName,
            'days_in_month' => $this->days,
            'average_daily_solar_radiation' => $this->averageDailyPeakSunHours,
            'estimated_generation_kwh' => $this->generationKwh,
            'estimated_consumption_kwh' => $this->consumptionKwh,
            'coverage_percentage' => $this->coveragePercentage,
            'estimated_savings_cop' => $this->savingsCop,
        ];
    }
}
