<?php

namespace App\Domain\Property;

/**
 * DANE (DIVIPOLA) code of each municipality of La Guajira. It is what matches a row of the app with
 * its shape in public/maps/la_guajira_municipios.geojson, because the names in the file are not
 * always written as the app writes them (accents, "San Juan del Cesar").
 *
 * Used by the map of the project form (one municipality) and by the coverage map of the installer
 * form (ADR-0021, several).
 */
final class MunicipalityBoundaries
{
    public const DANE_CODES = [
        'Riohacha' => '44001',
        'Albania' => '44035',
        'Barrancas' => '44078',
        'Dibulla' => '44090',
        'Distracción' => '44098',
        'El Molino' => '44110',
        'Fonseca' => '44279',
        'Hatonuevo' => '44378',
        'La Jagua del Pilar' => '44420',
        'Maicao' => '44430',
        'Manaure' => '44560',
        'San Juan del Cesar' => '44650',
        'Uribia' => '44847',
        'Urumita' => '44855',
        'Villanueva' => '44874',
    ];

    /**
     * Empty for a municipality the map does not know: the form then matches it by name alone.
     */
    public static function daneCode(?string $name): string
    {
        return self::DANE_CODES[$name] ?? '';
    }
}
