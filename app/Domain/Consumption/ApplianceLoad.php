<?php

namespace App\Domain\Consumption;

/**
 * One kind of appliance the client wants to power: which variant, how many and how long per day.
 */
final readonly class ApplianceLoad
{
    public function __construct(
        public string $key,
        public string $variant,
        public int $quantity,
        public float $hoursPerDay,
    ) {}
}
