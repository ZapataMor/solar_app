<?php

namespace App\Domain\Reference;

use DateTimeImmutable;

/**
 * The value that applies today, and since when.
 */
final readonly class CurrentReferenceValue
{
    public function __construct(
        public string $key,
        public float $value,
        /** Null while no administrator has recorded it: the catalog default applies. */
        public ?DateTimeImmutable $validFrom = null,
        /** When it started to apply: its valid-from date, or when it was recorded if that was later. */
        public ?DateTimeImmutable $effectiveSince = null,
        public ?string $source = null,
    ) {}

    public function isDefault(): bool
    {
        return $this->validFrom === null;
    }
}
