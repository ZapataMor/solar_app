<?php

namespace App\Domain\Consumption;

use InvalidArgumentException;

/**
 * Reference catalog of household and business appliances (ADR-0002).
 *
 * `watts` is the average power while the appliance is in use (compressor cycling already
 * included for fridges, freezers and coolers). Values are field references for La Guajira
 * and must be validated by an installer before being treated as definitive.
 *
 * Variants: an appliance has 0, 1 or 2 option groups. The variant key joins the chosen
 * option keys with "." in group order (e.g. "12000.inverter"); with no groups it is "default".
 *
 * Usage: "day" (hours per day), "week" (hours per week, stored per day) or "always" (24 h).
 */
final class ApplianceCatalog
{
    public const DAYS_PER_MONTH = 30;

    public const SEGMENT_HOME = 'home';

    public const SEGMENT_BUSINESS = 'business';

    private const APPLIANCES = [
        'air_conditioner' => [
            'label' => 'Aire acondicionado',
            'icon' => 'air-conditioner',
            'segments' => ['home', 'business'],
            'usage' => 'day',
            'default_hours' => 8,
            'default_quantity' => 1,
            'hint' => 'En la etiqueta del equipo aparecen los BTU y si es "Inverter".',
            'groups' => [
                ['key' => 'size', 'label' => 'Capacidad', 'choices' => [
                    ['key' => '9000', 'label' => '9.000 BTU', 'scale' => 1],
                    ['key' => '12000', 'label' => '12.000 BTU', 'scale' => 2],
                    ['key' => '18000', 'label' => '18.000 BTU', 'scale' => 3],
                    ['key' => '24000', 'label' => '24.000 BTU', 'scale' => 4],
                ]],
                ['key' => 'tech', 'label' => 'Tecnología', 'choices' => [
                    ['key' => 'conventional', 'label' => 'Convencional'],
                    ['key' => 'inverter', 'label' => 'Inverter'],
                ]],
            ],
            'default_variant' => '12000.conventional',
            'watts' => [
                '9000.conventional' => 900, '9000.inverter' => 600,
                '12000.conventional' => 1200, '12000.inverter' => 800,
                '18000.conventional' => 1800, '18000.inverter' => 1200,
                '24000.conventional' => 2400, '24000.inverter' => 1600,
            ],
        ],
        'fridge' => [
            'label' => 'Nevera',
            'icon' => 'fridge',
            'segments' => ['home', 'business'],
            'usage' => 'always',
            'default_hours' => 24,
            'default_quantity' => 1,
            'hint' => 'Funciona todo el día; el compresor se prende y apaga solo.',
            'groups' => [
                ['key' => 'size', 'label' => 'Tamaño', 'choices' => [
                    ['key' => 'small', 'label' => 'Pequeña · hasta 200 L', 'scale' => 1],
                    ['key' => 'medium', 'label' => 'Mediana · 200–350 L', 'scale' => 2],
                    ['key' => 'large', 'label' => 'Grande · 350–500 L', 'scale' => 3],
                    ['key' => 'side_by_side', 'label' => 'Dos puertas · +500 L', 'icon' => 'fridge-double', 'scale' => 4],
                ]],
                ['key' => 'tech', 'label' => 'Tecnología', 'choices' => [
                    ['key' => 'conventional', 'label' => 'Convencional'],
                    ['key' => 'inverter', 'label' => 'Inverter'],
                ]],
            ],
            'default_variant' => 'medium.conventional',
            'watts' => [
                'small.conventional' => 45, 'small.inverter' => 32,
                'medium.conventional' => 60, 'medium.inverter' => 42,
                'large.conventional' => 80, 'large.inverter' => 56,
                'side_by_side.conventional' => 110, 'side_by_side.inverter' => 77,
            ],
        ],
        'freezer' => [
            'label' => 'Congelador',
            'icon' => 'freezer-chest',
            'segments' => ['home', 'business'],
            'usage' => 'always',
            'default_hours' => 24,
            'default_quantity' => 1,
            'hint' => null,
            'groups' => [
                ['key' => 'type', 'label' => 'Tipo', 'choices' => [
                    ['key' => 'chest_small', 'label' => 'Horizontal · hasta 200 L', 'icon' => 'freezer-chest', 'scale' => 1],
                    ['key' => 'chest_large', 'label' => 'Horizontal · +300 L', 'icon' => 'freezer-chest', 'scale' => 3],
                    ['key' => 'upright', 'label' => 'Vertical', 'icon' => 'freezer-upright', 'scale' => 2],
                ]],
            ],
            'default_variant' => 'chest_small',
            'watts' => ['chest_small' => 55, 'chest_large' => 95, 'upright' => 80],
        ],
        'beverage_cooler' => [
            'label' => 'Enfriador de bebidas',
            'icon' => 'cooler',
            'segments' => ['business'],
            'usage' => 'always',
            'default_hours' => 24,
            'default_quantity' => 1,
            'hint' => 'Nevera vertical con puerta de vidrio, típica de tiendas.',
            'groups' => [
                ['key' => 'doors', 'label' => 'Puertas', 'choices' => [
                    ['key' => 'one_door', 'label' => '1 puerta', 'icon' => 'cooler'],
                    ['key' => 'two_doors', 'label' => '2 puertas', 'icon' => 'cooler-double'],
                ]],
            ],
            'default_variant' => 'one_door',
            'watts' => ['one_door' => 160, 'two_doors' => 260],
        ],
        'display_case' => [
            'label' => 'Vitrina refrigerada',
            'icon' => 'display-case',
            'segments' => ['business'],
            'usage' => 'always',
            'default_hours' => 24,
            'default_quantity' => 1,
            'hint' => 'Para carnes, lácteos o postres.',
            'groups' => [],
            'default_variant' => 'default',
            'watts' => ['default' => 180],
        ],
        'fan' => [
            'label' => 'Abanico',
            'icon' => 'fan',
            'segments' => ['home', 'business'],
            'usage' => 'day',
            'default_hours' => 8,
            'default_quantity' => 1,
            'hint' => null,
            'groups' => [
                ['key' => 'type', 'label' => 'Tipo', 'choices' => [
                    ['key' => 'table', 'label' => 'De mesa', 'icon' => 'fan-table'],
                    ['key' => 'stand', 'label' => 'De pie', 'icon' => 'fan'],
                    ['key' => 'ceiling', 'label' => 'De techo', 'icon' => 'fan-ceiling'],
                ]],
            ],
            'default_variant' => 'stand',
            'watts' => ['table' => 40, 'stand' => 55, 'ceiling' => 65],
        ],
        'lighting' => [
            'label' => 'Bombillos',
            'icon' => 'lightbulb',
            'segments' => ['home', 'business'],
            'usage' => 'day',
            'default_hours' => 6,
            'default_quantity' => 6,
            'hint' => 'Cuenta todos los bombillos que se prenden de noche.',
            'groups' => [
                ['key' => 'type', 'label' => 'Tipo', 'choices' => [
                    ['key' => 'led', 'label' => 'LED'],
                    ['key' => 'cfl', 'label' => 'Ahorrador'],
                    ['key' => 'incandescent', 'label' => 'Incandescente'],
                ]],
            ],
            'default_variant' => 'led',
            'watts' => ['led' => 9, 'cfl' => 20, 'incandescent' => 60],
        ],
        'tv' => [
            'label' => 'Televisor',
            'icon' => 'tv',
            'segments' => ['home', 'business'],
            'usage' => 'day',
            'default_hours' => 5,
            'default_quantity' => 1,
            'hint' => null,
            'groups' => [
                ['key' => 'size', 'label' => 'Tamaño', 'choices' => [
                    ['key' => '32', 'label' => '32"', 'scale' => 1],
                    ['key' => '43', 'label' => '43"', 'scale' => 2],
                    ['key' => '55', 'label' => '55"', 'scale' => 3],
                    ['key' => '65', 'label' => '65"', 'scale' => 4],
                ]],
            ],
            'default_variant' => '43',
            'watts' => ['32' => 45, '43' => 70, '55' => 100, '65' => 140],
        ],
        'computer' => [
            'label' => 'Computador',
            'icon' => 'laptop',
            'segments' => ['home', 'business'],
            'usage' => 'day',
            'default_hours' => 6,
            'default_quantity' => 1,
            'hint' => null,
            'groups' => [
                ['key' => 'type', 'label' => 'Tipo', 'choices' => [
                    ['key' => 'laptop', 'label' => 'Portátil', 'icon' => 'laptop'],
                    ['key' => 'desktop', 'label' => 'De escritorio', 'icon' => 'desktop'],
                ]],
            ],
            'default_variant' => 'laptop',
            'watts' => ['laptop' => 60, 'desktop' => 200],
        ],
        'router' => [
            'label' => 'Internet (módem)',
            'icon' => 'router',
            'segments' => ['home', 'business'],
            'usage' => 'always',
            'default_hours' => 24,
            'default_quantity' => 1,
            'hint' => null,
            'groups' => [],
            'default_variant' => 'default',
            'watts' => ['default' => 10],
        ],
        'washing_machine' => [
            'label' => 'Lavadora',
            'icon' => 'washing-machine',
            'segments' => ['home'],
            'usage' => 'week',
            'default_hours' => 4,
            'default_quantity' => 1,
            'hint' => 'Horas de lavado a la semana.',
            'groups' => [
                ['key' => 'capacity', 'label' => 'Capacidad', 'choices' => [
                    ['key' => 'up_to_12', 'label' => 'Hasta 12 kg', 'scale' => 1],
                    ['key' => 'over_12', 'label' => 'Más de 12 kg', 'scale' => 2],
                ]],
            ],
            'default_variant' => 'up_to_12',
            'watts' => ['up_to_12' => 450, 'over_12' => 600],
        ],
        'water_pump' => [
            'label' => 'Bomba de agua',
            'icon' => 'water-pump',
            'segments' => ['home', 'business'],
            'usage' => 'day',
            'default_hours' => 1,
            'default_quantity' => 1,
            'hint' => 'La que sube el agua al tanque.',
            'groups' => [
                ['key' => 'power', 'label' => 'Potencia', 'choices' => [
                    ['key' => 'half_hp', 'label' => '½ HP', 'scale' => 1],
                    ['key' => 'one_hp', 'label' => '1 HP', 'scale' => 2],
                ]],
            ],
            'default_variant' => 'half_hp',
            'watts' => ['half_hp' => 450, 'one_hp' => 850],
        ],
        'microwave' => [
            'label' => 'Microondas',
            'icon' => 'microwave',
            'segments' => ['home', 'business'],
            'usage' => 'week',
            'default_hours' => 2,
            'default_quantity' => 1,
            'hint' => 'Horas de uso a la semana.',
            'groups' => [],
            'default_variant' => 'default',
            'watts' => ['default' => 1100],
        ],
        'blender' => [
            'label' => 'Licuadora',
            'icon' => 'blender',
            'segments' => ['home', 'business'],
            'usage' => 'week',
            'default_hours' => 1,
            'default_quantity' => 1,
            'hint' => 'Horas de uso a la semana.',
            'groups' => [],
            'default_variant' => 'default',
            'watts' => ['default' => 450],
        ],
        'iron' => [
            'label' => 'Plancha',
            'icon' => 'iron',
            'segments' => ['home'],
            'usage' => 'week',
            'default_hours' => 2,
            'default_quantity' => 1,
            'hint' => 'Horas de uso a la semana.',
            'groups' => [],
            'default_variant' => 'default',
            'watts' => ['default' => 1100],
        ],
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return self::APPLIANCES;
    }

    public function has(string $key): bool
    {
        return isset(self::APPLIANCES[$key]);
    }

    public function hasVariant(string $key, string $variant): bool
    {
        return isset(self::APPLIANCES[$key]['watts'][$variant]);
    }

    public function watts(string $key, string $variant): float
    {
        if (! $this->hasVariant($key, $variant)) {
            throw new InvalidArgumentException("Equipo o variante desconocida: {$key} / {$variant}.");
        }

        return (float) self::APPLIANCES[$key]['watts'][$variant];
    }

    public function isAlwaysOn(string $key): bool
    {
        return (self::APPLIANCES[$key]['usage'] ?? null) === 'always';
    }

    public function label(string $key): string
    {
        return self::APPLIANCES[$key]['label'] ?? $key;
    }
}
