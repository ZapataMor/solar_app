<?php

namespace App\Actions\ReferenceValues;

use App\Domain\Reference\ReferenceValueCatalog;
use App\Infrastructure\Reference\DatabaseReferenceValues;
use App\Models\ReferenceValue;
use App\Models\User;

/**
 * Use case: an administrator records what a reference value is worth from a date on (ADR-0015).
 *
 * The earlier values stay as history. Recording again for the same date corrects that entry.
 * Projects that follow the value get the "!" to recalculate once it applies.
 */
final class RecordReferenceValue
{
    public function __construct(
        private readonly DatabaseReferenceValues $referenceValues,
    ) {}

    public function __invoke(string $key, float $value, string $validFrom, ?string $source, ?string $notes, ?User $recordedBy): ReferenceValue
    {
        ReferenceValueCatalog::definition($key);

        // By day: the date column is stored with a time ("2026-08-01 00:00:00").
        $recorded = ReferenceValue::query()
            ->where('key', $key)
            ->whereDate('valid_from', $validFrom)
            ->firstOrNew();

        $recorded->fill([
            'key' => $key,
            'value' => $value,
            'valid_from' => $validFrom,
            'source' => $source,
            'notes' => $notes,
            'user_id' => $recordedBy?->id,
        ])->save();

        $this->referenceValues->forget();

        return $recorded;
    }
}
