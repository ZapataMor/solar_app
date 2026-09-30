<?php

namespace App\Infrastructure\Climate;

use App\Domain\Climate\DailyIrradiance;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Maps the legacy climate rows (arrays or models exposing `date_time` and
 * `allsky_sfc_sw_dwn` as a 24h-average W/m²) to domain {@see DailyIrradiance}.
 */
final class IrradianceRows
{
    /**
     * @param  iterable<array<string, mixed>|object>  $rows
     * @return list<DailyIrradiance>
     */
    public static function toDays(iterable $rows): array
    {
        $days = [];

        foreach ($rows as $row) {
            $radiation = data_get($row, 'allsky_sfc_sw_dwn');

            if ($radiation === null) {
                continue;
            }

            $days[] = new DailyIrradiance(self::date(data_get($row, 'date_time')), (float) $radiation);
        }

        return $days;
    }

    private static function date(mixed $dateTime): string
    {
        return $dateTime instanceof DateTimeInterface
            ? $dateTime->format('Y-m-d')
            : Carbon::parse((string) $dateTime)->toDateString();
    }
}
