<?php

namespace App\Domain\Consumption;

/**
 * How a project's consumption is given (ADR-0020): added up from its appliances (ADR-0013), or the
 * kWh per month the client reads on the electricity bill. The client picks one when creating the
 * project; the other stays available by changing it.
 */
final class ConsumptionMode
{
    public const APPLIANCES = 'appliances';

    public const BILL = 'bill';

    public const ALL = [self::APPLIANCES, self::BILL];

    /** A bill never shows less than this, and more than this is a typo (a large institution uses tens of thousands). */
    public const MIN_BILL_KWH = 1;

    public const MAX_BILL_KWH = 1_000_000;

    public static function normalize(?string $mode): string
    {
        return in_array($mode, self::ALL, true) ? $mode : self::APPLIANCES;
    }

    public static function label(?string $mode): string
    {
        return self::normalize($mode) === self::BILL ? 'Con mi recibo de luz' : 'Con mis equipos';
    }

    public static function hint(string $mode): string
    {
        return $mode === self::BILL
            ? 'Escribes los kWh al mes que aparecen en tu recibo. Es lo más rápido.'
            : 'Agregas tus equipos, espacio por espacio, y sumamos su consumo. Sirve si aún no tienes recibo, o para ver qué gasta más.';
    }
}
