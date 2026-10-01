<?php

namespace App\Domain\Consumption;

/**
 * How the client tells us their consumption: listing appliances (ADR-0002) or with the bill's kWh.
 */
final class ConsumptionMode
{
    public const APPLIANCES = 'appliances';

    public const BILL = 'bill';

    public const ALL = [self::APPLIANCES, self::BILL];
}
