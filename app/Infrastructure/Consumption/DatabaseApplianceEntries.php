<?php

namespace App\Infrastructure\Consumption;

use App\Models\CatalogAppliance;
use Illuminate\Database\QueryException;

/**
 * The appliances administrators added (ADR-0017), shaped as entries of the domain catalog.
 */
final class DatabaseApplianceEntries
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        try {
            return CatalogAppliance::query()
                ->orderBy('label')
                ->get()
                ->mapWithKeys(fn (CatalogAppliance $appliance) => [$appliance->key => $appliance->toCatalogEntry()])
                ->all();
        } catch (QueryException) {
            // Before the migration runs (a fresh install): only the built-in catalog.
            return [];
        }
    }
}
