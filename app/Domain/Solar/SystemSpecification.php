<?php

namespace App\Domain\Solar;

/**
 * Physical description of the photovoltaic system to size.
 */
final readonly class SystemSpecification
{
    public function __construct(
        public float $availableAreaM2,
        public float $usableAreaPercentage,
        public float $panelAreaM2,
        public float $panelPowerW,
        public float $performanceRatio,
    ) {}
}
