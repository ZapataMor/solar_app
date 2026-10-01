<?php

namespace App\Domain\Solar;

/**
 * Physical description of the photovoltaic system to size.
 */
final readonly class SystemSpecification
{
    /** Defaults offered to clients who do not know their technical parameters (ADR-0007). */
    public const DEFAULT_USABLE_AREA_PERCENTAGE = 80.0;

    public const DEFAULT_PANEL_POWER_W = 550.0;

    public const DEFAULT_PANEL_AREA_M2 = 2.6;

    /** PVWatts default system losses. */
    public const DEFAULT_SYSTEM_LOSSES_PERCENTAGE = 14.0;

    public function __construct(
        public float $availableAreaM2,
        public float $usableAreaPercentage,
        public float $panelAreaM2,
        public float $panelPowerW,
        public float $performanceRatio,
    ) {}
}
