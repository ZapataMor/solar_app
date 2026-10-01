<?php

namespace App\Domain\Solar;

use RuntimeException;

/**
 * The project has no consumption yet: appliances are the base of the calculation (ADR-0013).
 */
final class MissingConsumption extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Agrega tus equipos en la pestaña Consumo para poder calcular tu sistema.');
    }
}
