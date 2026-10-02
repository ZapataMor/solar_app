<?php

namespace App\Domain\Reference;

/**
 * What a reference value is and its sensible range (ADR-0015).
 */
final readonly class ReferenceValueDefinition
{
    public function __construct(
        public string $key,
        public string $label,
        public string $unit,
        public string $description,
        /** Used until an administrator records the first value. */
        public float $default,
        public float $min,
        public float $max,
        public int $decimals = 0,
    ) {}
}
