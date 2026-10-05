<?php

namespace App\Actions\Pricing;

use App\Models\Municipality;
use App\Models\MunicipalitySolarPrice;

/**
 * Use case: an administrator sets the price per kW of a municipality for a kind of location
 * (ADR-0024).
 *
 * It saves the price and nothing else. Projects already quoted keep the figure they were quoted
 * with, and so do the requests the clients already sent: the new price rules what gets quoted from
 * here on. That is the whole point of the decision, so do not add a re-quote here.
 */
final class SaveMunicipalityPrice
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Municipality $municipality, string $locationType, array $data): MunicipalitySolarPrice
    {
        $price = MunicipalitySolarPrice::query()->firstOrNew(
            ['municipality_id' => $municipality->id, 'location_type' => $locationType],
        );

        // The zone only on a new row: it names the price ("Alta Guajira rural dispersa") and other
        // things key on it, so an edit of the price has no business rewriting it.
        $price->fill([
            'zone_name' => $price->zone_name ?? $municipality->zone,
            'base_price_per_kw' => $data['base_price_per_kw'],
            'logistic_factor' => $data['logistic_factor'],
            'notes' => $data['notes'] ?? null,
            'active' => (bool) ($data['active'] ?? false),
        ])->save();

        return $price;
    }
}
