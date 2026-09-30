<?php

namespace App\Domain\Solar;

use RuntimeException;

final class MissingTechnicalParameters extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No es posible ejecutar calculos solares sin parametros tecnicos.');
    }
}
