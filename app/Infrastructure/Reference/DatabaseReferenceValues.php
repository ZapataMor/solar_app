<?php

namespace App\Infrastructure\Reference;

use App\Domain\Reference\CurrentReferenceValue;
use App\Domain\Reference\ReferenceValueCatalog;
use App\Domain\Reference\ReferenceValues;
use App\Models\ReferenceValue;
use DateTimeImmutable;

/**
 * Reference values stored in `reference_values`: the latest one whose date already arrived.
 * Remembered for the request and the day (they are read once per project on listing pages).
 */
final class DatabaseReferenceValues implements ReferenceValues
{
    /** @var array<string, CurrentReferenceValue> */
    private array $remembered = [];

    public function current(string $key): CurrentReferenceValue
    {
        // By day too: a long process (or a value scheduled for tomorrow) must not keep yesterday's value.
        return $this->remembered[$key.'@'.now()->toDateString()] ??= $this->load($key);
    }

    /**
     * After recording a value, so the rest of the request sees it.
     */
    public function forget(): void
    {
        $this->remembered = [];
    }

    private function load(string $key): CurrentReferenceValue
    {
        $definition = ReferenceValueCatalog::definition($key);
        $row = ReferenceValue::query()
            ->where('key', $key)
            ->whereDate('valid_from', '<=', now()->toDateString())
            ->orderByDesc('valid_from')
            ->first();

        if ($row === null) {
            return new CurrentReferenceValue($key, $definition->default);
        }

        $validFrom = DateTimeImmutable::createFromInterface($row->valid_from->startOfDay());
        $recordedAt = DateTimeImmutable::createFromInterface($row->created_at);

        return new CurrentReferenceValue(
            key: $key,
            value: (float) $row->value,
            validFrom: $validFrom,
            effectiveSince: max($validFrom, $recordedAt),
            source: $row->source,
        );
    }
}
