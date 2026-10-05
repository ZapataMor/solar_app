<?php

namespace App\Domain\Installers;

use RuntimeException;

/**
 * The project cannot ask this installer for a quote (ADR-0021). The directory already hides these
 * cases; this guards the request that arrives anyway.
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
}
