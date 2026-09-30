<?php

namespace App\Domain\Pricing;

use RuntimeException;

final class PriceNotAvailable extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No hay precio disponible para esa ubicacion.');
    }
}
