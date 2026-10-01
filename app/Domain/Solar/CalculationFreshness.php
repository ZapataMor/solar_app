<?php

namespace App\Domain\Solar;

/**
 * Whether a project's stored calculation still reflects its current inputs and climate data.
 */
final readonly class CalculationFreshness
{
    public const FRESH = 'fresh';

    public const STALE = 'stale';

    public const PENDING = 'pending';

    public const NOT_READY = 'not_ready';

    /**
     * @param  list<string>  $reasons  Human-readable, shown to the user.
     */
    public function __construct(
        public string $status,
        public array $reasons = [],
    ) {}

    /**
     * A recalculation would change or produce results (the "!" indicator).
     */
    public function needsRecalculation(): bool
    {
        return in_array($this->status, [self::STALE, self::PENDING], true);
    }
}
