<?php

namespace App\Domain\Installers;

use RuntimeException;

/**
 * A quote request cannot be made (ADR-0022) or answered (ADR-0023) the way it was asked. The screens
 * already hide these cases; this guards the request that arrives anyway.
 */
final class QuoteNotPossible extends RuntimeException
{
    public static function withoutConsumption(): self
    {
        return new self('Primero define el consumo del proyecto: el instalador cotiza sobre esa estimación.');
    }

    public static function outsideCoverage(): self
    {
        return new self('Ese instalador no cubre el municipio del proyecto.');
    }

    public static function notAvailable(): self
    {
        return new self('Ese instalador ya no está disponible.');
    }

    public static function unknownAnswer(): self
    {
        return new self('Esa no es una respuesta válida para la solicitud.');
    }

    public static function withoutContractValue(): self
    {
        return new self('Escribe en cuánto se cerró el negocio.');
    }
}
