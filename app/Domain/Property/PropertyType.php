<?php

namespace App\Domain\Property;

use App\Domain\Consumption\ApplianceCatalog;

/**
 * The kind of place the solar system is for (ADR-0013). It decides the spaces of the
 * consumption diary and which part of the appliance catalog is offered first.
 */
final class PropertyType
{
    public const HOUSE = 'house';

    public const BUSINESS = 'business';

    public const INSTITUTION = 'institution';

    public const ALL = [self::HOUSE, self::BUSINESS, self::INSTITUTION];

    /** Space every type has; appliances in a space the type does not know end up here. */
    public const OTHER_SPACE = 'other';

    private const TYPES = [
        self::HOUSE => [
            'label' => 'Casa',
            'option' => 'Mi casa',
            // A farmhouse is a home too; a farm that produces goes as a business. A farm is not a kind of its own.
            'hint' => 'Vivienda familiar, en el pueblo o en el campo',
            'segments' => [ApplianceCatalog::SEGMENT_HOME],
            'spaces' => [
                'kitchen' => 'Cocina',
                'living' => 'Sala y comedor',
                'bedrooms' => 'Habitaciones',
                'laundry' => 'Lavandería y patio',
                self::OTHER_SPACE => 'Otros',
            ],
        ],
        self::BUSINESS => [
            'label' => 'Negocio',
            'option' => 'Mi negocio',
            'hint' => 'Tienda, local, oficina o finca productiva',
            'segments' => [ApplianceCatalog::SEGMENT_BUSINESS],
            'spaces' => [
                'sales' => 'Área de atención',
                'office' => 'Oficina',
                'storage' => 'Bodega y cocina',
                self::OTHER_SPACE => 'Otros',
            ],
        ],
        self::INSTITUTION => [
            'label' => 'Institución',
            'option' => 'Mi institución',
            'hint' => 'Colegio, centro de salud o espacio comunitario',
            'segments' => [ApplianceCatalog::SEGMENT_HOME, ApplianceCatalog::SEGMENT_BUSINESS],
            'spaces' => [
                'classrooms' => 'Aulas',
                'offices' => 'Oficinas',
                'kitchen' => 'Cocina y comedor',
                'common' => 'Zonas comunes',
                self::OTHER_SPACE => 'Otros',
            ],
        ],
    ];

    public static function isValid(?string $type): bool
    {
        return $type !== null && isset(self::TYPES[$type]);
    }

    /**
     * Unknown or missing types (older projects) are treated as a house.
     */
    public static function normalize(?string $type): string
    {
        return self::isValid($type) ? $type : self::HOUSE;
    }

    public static function label(?string $type): string
    {
        return self::TYPES[self::normalize($type)]['label'];
    }

    /**
     * How the client picks it ("Mi casa"); also the start of the suggested project name.
     */
    public static function optionLabel(?string $type): string
    {
        return self::TYPES[self::normalize($type)]['option'];
    }

    public static function hint(?string $type): string
    {
        return self::TYPES[self::normalize($type)]['hint'];
    }

    /**
     * @return list<string> Catalog segments offered first for this type.
     */
    public static function applianceSegments(?string $type): array
    {
        return self::TYPES[self::normalize($type)]['segments'];
    }

    /**
     * @return array<string, string> Space key => label, in diary order ("Otros" last).
     */
    public static function spaces(?string $type): array
    {
        return self::TYPES[self::normalize($type)]['spaces'];
    }

    /**
     * @return list<string>
     */
    public static function spaceKeys(?string $type): array
    {
        return array_keys(self::spaces($type));
    }

    /**
     * The diary space an appliance is shown in: its own, or "Otros" when this type has no such space.
     */
    public static function spaceOf(?string $type, ?string $space): string
    {
        return $space !== null && array_key_exists($space, self::spaces($type)) ? $space : self::OTHER_SPACE;
    }

    /**
     * Suggested project name, e.g. "Mi casa en Maicao".
     */
    public static function suggestedProjectName(?string $type, string $municipality): string
    {
        $municipality = trim($municipality);

        return $municipality === '' ? self::optionLabel($type) : self::optionLabel($type).' en '.$municipality;
    }
}
